<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentStatisticsInterface;
use Drupal\comment\CommentStorageInterface;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\Component\Utility\Number;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Moves a public Forum comment and its complete reply subtree. */
final class ForumCommentMover {

  public const COMMENT_TYPE = 'aculta_discussion_post';

  public const FIELD_NAME = 'field_aculta_discussion';

  private const MAX_SUBTREE_SIZE = 500;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ForumDiscussionWriteGuard $discussionWriteGuard,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly CommentStatisticsInterface $commentStatistics,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    #[Autowire(service: 'logger.channel.aculta_forum')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns access to the Forum-only move operation for one Comment. */
  public function access(CommentInterface $comment, AccountInterface $account): AccessResultInterface {
    $source = $comment->getCommentedEntity();
    if (!$source instanceof NodeInterface || !$this->isMovableTopic($source)
      || $comment->bundle() !== self::COMMENT_TYPE
      || $comment->getFieldName() !== self::FIELD_NAME
      || $comment->getCommentedEntityTypeId() !== 'node') {
      $result = AccessResult::forbidden()->addCacheableDependency($comment);
      if ($source instanceof NodeInterface) {
        $result->addCacheableDependency($source);
      }
      return $result;
    }

    $forum_domain = $this->domainPurposeManager->getDomain('forum');
    $allowed = $this->configFactory->get('aculta_forum.settings')->get('comment_move_enabled') === TRUE
      && $forum_domain !== NULL
      && $account->hasPermission('move aculta forum comments')
      && (string) ($source->get('field_domain_source')->target_id ?? '') === (string) $forum_domain->id();
    $result = ($allowed ? AccessResult::allowed() : AccessResult::forbidden())
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($comment)
      ->addCacheableDependency($source);
    if ($forum_domain !== NULL) {
      $result->addCacheableDependency($forum_domain);
    }

    if ($allowed) {
      $node_access = $this->domainPurposeManager->runInPurpose(
        'forum',
        fn () => $source->access('view', $account, TRUE),
      );
      $comment_access = $this->domainPurposeManager->runInPurpose(
        'forum',
        fn () => $comment->access('view', $account, TRUE),
      );
      $result = $result->andIf($node_access instanceof AccessResultInterface
        ? $node_access
        : AccessResult::forbidden());
      $result = $result->andIf($comment_access instanceof AccessResultInterface
        ? $comment_access
        : AccessResult::forbidden());
    }

    return $result;
  }

  /**
   * Returns the number of Comments that confirmation will move. */
  public function subtreeCount(CommentInterface $comment): int {
    $this->assertMovableComment($comment);
    return count($this->loadSubtree($comment));
  }

  /**
   * Returns accessible, open public topics eligible as destinations. */
  public function destinations(CommentInterface $comment, AccountInterface $account): array {
    if (!$this->access($comment, $account)->isAllowed()) {
      return [];
    }
    $source = $comment->getCommentedEntity();
    $domain = $this->domainPurposeManager->getDomain('forum');
    if (!$source instanceof NodeInterface || $domain === NULL) {
      return [];
    }

    $ids = $this->domainPurposeManager->runInPurpose('forum', function () use ($domain, $source): array {
      return array_values($this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'aculta_forum_topic')
        ->condition('status', 1)
        ->condition('field_domain_source.target_id', $domain->id())
        ->condition('nid', $source->id(), '<>')
        ->sort('title')
        ->range(0, 500)
        ->execute());
    });

    $destinations = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($ids) as $node) {
      if ($node instanceof NodeInterface && $this->isOpenTopic($node)) {
        $access = $this->domainPurposeManager->runInPurpose(
          'forum',
          fn () => $node->access('view', $account, TRUE),
        );
        if ($access instanceof AccessResultInterface && $access->isAllowed()) {
          $destinations[(int) $node->id()] = $node->label();
        }
      }
    }
    return $destinations;
  }

  /**
   * Moves the selected Comment and all descendants to a new topic root. */
  public function move(int $commentId, int $destinationId, AccountInterface $account): int {
    $comment = $this->entityTypeManager->getStorage('comment')->load($commentId);
    $destination = $this->entityTypeManager->getStorage('node')->load($destinationId);
    if (!$comment instanceof CommentInterface || !$destination instanceof NodeInterface) {
      throw new \InvalidArgumentException('The Forum comment move endpoints are unavailable.');
    }
    $source_candidate = $comment->getCommentedEntity();
    if (!$source_candidate instanceof NodeInterface) {
      throw new \InvalidArgumentException('The source is not a public Forum topic.');
    }
    $source_id = (int) $source_candidate->id();
    $destination_id = (int) $destination->id();
    $this->assertMoveEndpoints($comment, $destination, $account);
    $comment_storage = $this->entityTypeManager->getStorage('comment');
    if (!$comment_storage instanceof CommentStorageInterface) {
      throw new \LogicException('Drupal Comment storage does not support thread allocation.');
    }

    // Comment::preSave() acquires its thread lock before comment_presave. Keep
    // the same order: reserve the destination root before discussion locks so
    // a normal reply save cannot deadlock with this move operation.
    [$new_root_thread, $thread_lock] = $this->allocateRootThread(
      $comment_storage,
      $this->newHostProbe($destination),
      $destination_id,
    );
    try {
      $topic_locks = $this->discussionWriteGuard->acquireTopics([$source_id, $destination_id]);
      try {
        $node_storage = $this->entityTypeManager->getStorage('node');
        $comment_storage->resetCache([$commentId]);
        $node_storage->resetCache([$source_id, $destination_id]);
        $comment = $comment_storage->load($commentId);
        $destination = $node_storage->load($destination_id);
        if (!$comment instanceof CommentInterface || !$destination instanceof NodeInterface) {
          throw new \InvalidArgumentException('The Forum comment move endpoints changed while the operation was starting.');
        }
        $source = $this->assertMoveEndpoints($comment, $destination, $account);
        $subtree = $this->loadSubtree($comment);
        foreach ($subtree as $member) {
          if (!$this->domainPurposeManager->runInPurpose(
            'forum',
            fn () => $member->access('view', $account, TRUE)->isAllowed(),
          )) {
            throw new \InvalidArgumentException('A reply in this subtree is not accessible.');
          }
        }

        $old_root_thread = rtrim((string) $comment->getThread(), '/');
        if ($old_root_thread === '') {
          throw new \InvalidArgumentException('The source reply has no valid thread position.');
        }

        $transaction = NULL;
        try {
          $transaction = $this->database->startTransaction();
          $this->discussionWriteGuard->beginMove(array_keys($subtree));
          foreach ($subtree as $cid => $member) {
            $this->discussionWriteGuard->refresh($topic_locks);
            $old_thread = (string) $member->getThread();
            if ((int) $cid === $commentId) {
              $member->set('pid', NULL);
              $member->setThread($new_root_thread);
            }
            else {
              if (!str_starts_with($old_thread, $old_root_thread . '.')) {
                throw new \InvalidArgumentException('The reply subtree has inconsistent thread positions.');
              }
              $suffix = substr(rtrim($old_thread, '/'), strlen($old_root_thread));
              $member->setThread(rtrim($new_root_thread, '/') . $suffix . '/');
            }
            $member->set('entity_id', $destination_id);
            $member->save();
          }

          $this->commentStatistics->update($this->newHostProbe($source));
          $this->cacheTagsInvalidator->invalidateTags($source->getCacheTagsToInvalidate());
          $this->cacheTagsInvalidator->invalidateTags($destination->getCacheTagsToInvalidate());
          $transaction->commitOrRelease();
        }
        catch (\Throwable $exception) {
          $transaction?->rollBack();
          $this->logger->error('Forum comment subtree move failed for comment @root from topic @source to @destination by uid @uid.', [
            '@root' => $commentId,
            '@source' => $source->id(),
            '@destination' => $destination_id,
            '@uid' => $account->id(),
          ]);
          throw $exception;
        }
        finally {
          $this->discussionWriteGuard->endMove();
        }
      }
      finally {
        $this->discussionWriteGuard->release($topic_locks);
      }

      $this->logger->notice('Forum comment subtree @root moved from topic @source to @destination by uid @uid; @count comments.', [
        '@root' => $commentId,
        '@source' => $source->id(),
        '@destination' => $destination_id,
        '@uid' => $account->id(),
        '@count' => count($subtree),
      ]);

      return count($subtree);
    }
    finally {
      $this->lock->release($thread_lock);
    }
  }

  /**
   * Revalidates both endpoints while their discussion-write locks are held. */
  private function assertMoveEndpoints(CommentInterface $comment, NodeInterface $destination, AccountInterface $account): NodeInterface {
    $this->assertMovableComment($comment);
    if (!$this->access($comment, $account)->isAllowed()
      || !$this->isOpenTopic($destination)
      || (int) $comment->getCommentedEntityId() === (int) $destination->id()) {
      throw new \InvalidArgumentException('The Forum comment move is not authorized for these endpoints.');
    }

    $forum_domain = $this->domainPurposeManager->getDomain('forum');
    if ($forum_domain === NULL
      || (string) ($destination->get('field_domain_source')->target_id ?? '') !== (string) $forum_domain->id()) {
      throw new \InvalidArgumentException('The destination is outside the public Forum purpose.');
    }
    $destination_access = $this->domainPurposeManager->runInPurpose(
      'forum',
      fn () => $destination->access('view', $account, TRUE),
    );
    if (!$destination_access instanceof AccessResultInterface || !$destination_access->isAllowed()) {
      throw new \InvalidArgumentException('The destination is not accessible in the public Forum.');
    }

    $source = $comment->getCommentedEntity();
    if (!$source instanceof NodeInterface) {
      throw new \InvalidArgumentException('The source is not a public Forum topic.');
    }
    return $source;
  }

  /**
   * Loads and validates a complete descendant tree in stable thread order. */
  private function loadSubtree(CommentInterface $root): array {
    $this->assertMovableComment($root);
    $source_id = (int) $root->getCommentedEntityId();
    $subtree = [(int) $root->id() => $root];
    $frontier = [$root];
    $storage = $this->entityTypeManager->getStorage('comment');
    while ($frontier !== []) {
      $parents = [];
      foreach ($frontier as $parent) {
        $parents[(int) $parent->id()] = $parent;
      }
      $child_ids = $storage->getChildCids($parents);
      $frontier = [];
      foreach ($storage->loadMultiple($child_ids) as $child) {
        if (!$child instanceof CommentInterface
          || (int) $child->getCommentedEntityId() !== $source_id
          || $child->getCommentedEntityTypeId() !== 'node'
          || $child->getFieldName() !== self::FIELD_NAME
          || $child->bundle() !== self::COMMENT_TYPE) {
          throw new \InvalidArgumentException('A descendant belongs to an incompatible discussion context.');
        }
        $cid = (int) $child->id();
        if (isset($subtree[$cid])) {
          throw new \InvalidArgumentException('A cycle was found in the reply tree.');
        }
        $subtree[$cid] = $child;
        $frontier[] = $child;
        if (count($subtree) > self::MAX_SUBTREE_SIZE) {
          throw new \InvalidArgumentException('This reply subtree exceeds the safe movement limit.');
        }
      }
    }

    uasort($subtree, static fn (CommentInterface $left, CommentInterface $right): int => strcmp(
      rtrim((string) $left->getThread(), '/'),
      rtrim((string) $right->getThread(), '/'),
    ));
    if ((int) array_key_first($subtree) !== (int) $root->id()) {
      throw new \InvalidArgumentException('The selected Comment is not first in its reply subtree.');
    }
    return $subtree;
  }

  /**
   * Validates a Comment's public Forum host and immutability boundary. */
  private function assertMovableComment(CommentInterface $comment): void {
    $source = $comment->getCommentedEntity();
    if (!$source instanceof NodeInterface || !$this->isMovableTopic($source)
      || $comment->bundle() !== self::COMMENT_TYPE
      || $comment->getCommentedEntityTypeId() !== 'node'
      || $comment->getFieldName() !== self::FIELD_NAME
      || (int) $comment->getCommentedEntityId() !== (int) $source->id()) {
      throw new \InvalidArgumentException('Only shared discussion Comments on public ACULTA Forum topics can move.');
    }
  }

  /**
   * Whether the topic is public, active and uses the shared Comment field. */
  private function isMovableTopic(NodeInterface $topic): bool {
    return $topic->bundle() === 'aculta_forum_topic'
      && $topic->isPublished()
      && $topic->hasField(self::FIELD_NAME)
      && $topic->hasField('field_domain_source')
      && $topic->hasField('field_autor_legado')
      && $topic->get('field_autor_legado')->isEmpty()
      && $topic->getFieldDefinition(self::FIELD_NAME)->getFieldStorageDefinition()->getSetting('comment_type') === self::COMMENT_TYPE;
  }

  /**
   * Whether a topic is a valid open destination. */
  private function isOpenTopic(NodeInterface $topic): bool {
    return $this->isMovableTopic($topic)
      && (int) $topic->get(self::FIELD_NAME)->status === CommentingStatus::Open->value;
  }

  /**
   * Builds an unsaved Comment to address Comment Core's per-host APIs. */
  private function newHostProbe(NodeInterface $host): CommentInterface {
    return Comment::create([
      'entity_type' => 'node',
      'entity_id' => (int) $host->id(),
      'field_name' => self::FIELD_NAME,
      'comment_type' => self::COMMENT_TYPE,
    ]);
  }

  /**
   * Allocates a Core-compatible thread slot under the Comment Core lock. */
  private function allocateRootThread(CommentStorageInterface $storage, CommentInterface $probe, int $destinationId): array {
    for ($attempt = 0; $attempt < 500; $attempt++) {
      // Match Comment Core's lock key so normal comment creation and moves
      // coordinate on the same root thread slot.
      $max = rtrim((string) $storage->getMaxThread($probe), '/');
      $parts = explode('.', $max);
      $next = $parts[0] !== '' ? Number::alphadecimalToInt($parts[0]) : 0;
      $thread = Number::intToAlphadecimal(++$next) . '/';
      $lock_name = "comment:$destinationId:$thread";
      if ($this->lock->acquire($lock_name, 60.0)) {
        // The lock may have been held by a Core comment insert between our
        // MAX query and acquire(). Re-read after acquiring it; never reuse a
        // slot that has since become durable.
        $current_max = rtrim((string) $storage->getMaxThread($probe), '/');
        $current_parts = explode('.', $current_max);
        $current_next = $current_parts[0] !== '' ? Number::alphadecimalToInt($current_parts[0]) : 0;
        if ($current_next < $next) {
          return [$thread, $lock_name];
        }
        $this->lock->release($lock_name);
      }
    }
    throw new \RuntimeException('Could not reserve a thread position in the destination topic.');
  }

}
