<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\comment\CommentInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\node\NodeInterface;

/**
 * Serializes public Forum comment writes with comment-tree moves. */
final class ForumDiscussionWriteGuard {

  private const LOCK_PREFIX = 'aculta_forum:topic-write:';

  private const LOCK_TTL = 300.0;

  private const LOCK_WAIT = 10;

  /**
   * Locks held for Comment saves until their transaction ends.
   *
   * @var \WeakMap<\Drupal\comment\CommentInterface, string[]> */
  private \WeakMap $pendingCommentLocks;

  /**
   * Comments intentionally rehosted by the active move operation.
   *
   * @var array<int, bool> */
  private array $movingCommentIds = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
  ) {
    $this->pendingCommentLocks = new \WeakMap();
  }

  /**
   * Acquires public Forum host locks in stable order. */
  public function acquireTopics(array $topicIds): array {
    $topicIds = array_values(array_unique(array_filter(array_map('intval', $topicIds), static fn (int $id): bool => $id > 0)));
    sort($topicIds, SORT_NUMERIC);
    $lock_names = array_map(static fn (int $id): string => self::LOCK_PREFIX . $id, $topicIds);
    return $this->acquireLocks($lock_names);
  }

  /**
   * Extends move locks before each entity write. */
  public function refresh(array $lockNames): void {
    foreach ($lockNames as $lock_name) {
      if (!$this->lock->acquire($lock_name, self::LOCK_TTL)) {
        throw new \RuntimeException('The Forum discussion changed while its comments were being moved.');
      }
    }
  }

  /**
   * Releases a set of public Forum host locks. */
  public function release(array $lockNames): void {
    foreach ($lockNames as $lock_name) {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Marks the Comment IDs intentionally rehosted by the current move. */
  public function beginMove(array $commentIds): void {
    foreach ($commentIds as $comment_id) {
      $this->movingCommentIds[(int) $comment_id] = TRUE;
    }
  }

  /**
   * Clears the intentional rehost markers after the move attempt. */
  public function endMove(): void {
    $this->movingCommentIds = [];
  }

  /**
   * Locks a Forum Comment write and rejects stale or cross-host parent links. */
  public function beforeCommentSave(CommentInterface $comment): void {
    if ($comment->bundle() !== ForumCommentMover::COMMENT_TYPE) {
      return;
    }

    $host_ids = [];
    $current_host_id = (int) $comment->getCommentedEntityId();
    if ($comment->getCommentedEntityTypeId() === 'node' && $this->isForumTopic($current_host_id)) {
      $host_ids[] = $current_host_id;
    }
    $original = $comment->getOriginal();
    if ($original instanceof CommentInterface
      && $original->getCommentedEntityTypeId() === 'node'
      && $this->isForumTopic((int) $original->getCommentedEntityId())) {
      $host_ids[] = (int) $original->getCommentedEntityId();
    }
    if ($host_ids === []) {
      return;
    }

    if ($comment->getCommentedEntityTypeId() !== 'node'
      || $comment->getFieldName() !== ForumCommentMover::FIELD_NAME) {
      throw new \InvalidArgumentException('Public Forum comments cannot change discussion fields or host types.');
    }

    $lock_names = array_map(static fn (int $id): string => self::LOCK_PREFIX . $id, array_values(array_unique($host_ids)));
    sort($lock_names, SORT_STRING);
    $acquired = $this->acquireLocks($lock_names);
    try {
      if (!$this->isIntentionalMove($comment)) {
        $this->assertCurrentRelations($comment);
      }
      $existing = $this->pendingCommentLocks[$comment] ?? [];
      $this->pendingCommentLocks[$comment] = array_values(array_unique([...$existing, ...$acquired]));
    }
    catch (\Throwable $exception) {
      $this->release($acquired);
      throw $exception;
    }
  }

  /**
   * Locks a Forum Comment deletion and rejects stale delete forms. */
  public function beforeCommentDelete(CommentInterface $comment): void {
    if ($comment->bundle() !== ForumCommentMover::COMMENT_TYPE
      || $comment->getCommentedEntityTypeId() !== 'node'
      || !$this->isForumTopic((int) $comment->getCommentedEntityId())) {
      return;
    }

    $lock_names = [self::LOCK_PREFIX . (int) $comment->getCommentedEntityId()];
    $acquired = $this->acquireLocks($lock_names);
    try {
      $persisted = $this->entityTypeManager->getStorage('comment')->loadUnchanged($comment->id());
      if (!$persisted instanceof CommentInterface
        || (int) $persisted->getCommentedEntityId() !== (int) $comment->getCommentedEntityId()
        || (string) $persisted->getThread() !== (string) $comment->getThread()) {
        throw new \InvalidArgumentException('This comment moved while the delete form was open. Reload the discussion and retry.');
      }
      $existing = $this->pendingCommentLocks[$comment] ?? [];
      $this->pendingCommentLocks[$comment] = array_values(array_unique([...$existing, ...$acquired]));
    }
    catch (\Throwable $exception) {
      $this->release($acquired);
      throw $exception;
    }
  }

  /**
   * Releases write locks only after the outer database transaction ends. */
  public function afterCommentSave(CommentInterface $comment): void {
    $lock_names = $this->pendingCommentLocks[$comment] ?? [];
    if ($lock_names === []) {
      return;
    }
    unset($this->pendingCommentLocks[$comment]);

    try {
      $this->database->transactionManager()->addPostTransactionCallback(
        fn (bool $success) => $this->release($lock_names),
      );
    }
    catch (\LogicException) {
      // Some custom storage paths do not wrap Comment saves in a transaction.
      $this->release($lock_names);
    }
  }

  /**
   * Gets every comment-thread write lock or releases the partial set. */
  private function acquireLocks(array $lockNames): array {
    $acquired = [];
    try {
      foreach ($lockNames as $lock_name) {
        if (!$this->lock->acquire($lock_name, self::LOCK_TTL)) {
          $this->lock->wait($lock_name, self::LOCK_WAIT);
          if (!$this->lock->acquire($lock_name, self::LOCK_TTL)) {
            throw new \RuntimeException('A Forum discussion is being updated. Retry the comment operation.');
          }
        }
        $acquired[] = $lock_name;
      }
      return $acquired;
    }
    catch (\Throwable $exception) {
      $this->release($acquired);
      throw $exception;
    }
  }

  /**
   * Validates stored host/thread relations after acquiring the host lock. */
  private function assertCurrentRelations(CommentInterface $comment): void {
    if (!$comment->isNew()) {
      $persisted = $this->entityTypeManager->getStorage('comment')->loadUnchanged($comment->id());
      if (!$persisted instanceof CommentInterface
        || (int) $persisted->getCommentedEntityId() !== (int) $comment->getCommentedEntityId()
        || $persisted->getCommentedEntityTypeId() !== $comment->getCommentedEntityTypeId()
        || $persisted->getFieldName() !== $comment->getFieldName()
        || (string) $persisted->getThread() !== (string) $comment->getThread()) {
        throw new \InvalidArgumentException('This comment changed discussion while the form was open. Reload it and retry.');
      }
    }

    $parent_id = (int) ($comment->get('pid')->target_id ?? 0);
    if ($parent_id === 0) {
      return;
    }
    $parent = $this->entityTypeManager->getStorage('comment')->loadUnchanged($parent_id);
    if (!$parent instanceof CommentInterface
      || $parent->getCommentedEntityTypeId() !== 'node'
      || (int) $parent->getCommentedEntityId() !== (int) $comment->getCommentedEntityId()
      || $parent->getFieldName() !== ForumCommentMover::FIELD_NAME
      || $parent->bundle() !== ForumCommentMover::COMMENT_TYPE) {
      throw new \InvalidArgumentException('A Forum reply cannot be saved under a comment from another topic. Reload the discussion and retry.');
    }
  }

  /**
   * Whether an ID is part of the current explicitly authorized tree move. */
  private function isIntentionalMove(CommentInterface $comment): bool {
    return !$comment->isNew() && isset($this->movingCommentIds[(int) $comment->id()]);
  }

  /**
   * Whether an entity ID identifies a current public Forum topic. */
  private function isForumTopic(int $nodeId): bool {
    $topic = $this->entityTypeManager->getStorage('node')->loadUnchanged($nodeId);
    return $topic instanceof NodeInterface
      && $topic->bundle() === 'aculta_forum_topic'
      && $topic->hasField(ForumCommentMover::FIELD_NAME);
  }

}
