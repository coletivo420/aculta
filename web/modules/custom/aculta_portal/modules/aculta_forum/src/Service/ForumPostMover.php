<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Moves revisionable ForumPosts (aculta_forum_post) between public Forum topics.
 *
 * Implements Discourse-style post split/move operations preserving revisions,
 * recalculating thread_position and invalidating cache tags in an atomic transaction.
 */
final class ForumPostMover {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_portal.domain_site')]
    private readonly DomainSiteManager $siteManager,
    private readonly ForumDiscussionWriteGuard $discussionWriteGuard,
    private readonly Connection $database,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly TimeInterface $time,
    #[Autowire(service: 'logger.channel.aculta_forum')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Checks whether a user can move a ForumPost.
   */
  public function access(ForumPost $post, AccountInterface $account): AccessResultInterface {
    $topic = $post->get('topic')->entity;
    if (!$topic instanceof NodeInterface || !$this->isMovableTopic($topic)) {
      return AccessResult::forbidden()->addCacheableDependency($post);
    }

    $forumDomain = $this->siteManager->getDomain(SiteKey::Forum);
    $hasPermission = $account->hasPermission('moderate aculta forum posts')
      || $account->hasPermission('reorganize aculta forum topics')
      || $account->hasPermission('administer aculta forum posts');

    $isForumSource = $forumDomain !== NULL
      && (string) ($topic->get('field_domain_source')->target_id ?? '') === (string) $forumDomain->id();

    $allowed = $hasPermission && $isForumSource;
    $result = ($allowed ? AccessResult::allowed() : AccessResult::forbidden())
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($post)
      ->addCacheableDependency($topic);

    if ($forumDomain !== NULL) {
      $result->addCacheableDependency($forumDomain);
    }

    if ($allowed) {
      $nodeAccess = $this->siteManager->runInSite(
        'forum',
        fn (): AccessResultInterface => $topic->access('view', $account, TRUE),
      );
      $postAccess = $this->siteManager->runInSite(
        'forum',
        fn (): AccessResultInterface => $post->access('view', $account, TRUE),
      );
      $result = $result->andIf($nodeAccess instanceof AccessResultInterface ? $nodeAccess : AccessResult::forbidden());
      $result = $result->andIf($postAccess instanceof AccessResultInterface ? $postAccess : AccessResult::forbidden());
    }

    return $result;
  }

  /**
   * Loads accessible open destination topics.
   *
   * @return array<int, string>
   *   Destination topic titles keyed by NID.
   */
  public function destinations(ForumPost $post, AccountInterface $account): array {
    if (!$this->access($post, $account)->isAllowed()) {
      return [];
    }
    $source = $post->get('topic')->entity;
    $domain = $this->siteManager->getDomain(SiteKey::Forum);
    if (!$source instanceof NodeInterface || !$domain instanceof DomainInterface) {
      return [];
    }

    $rawIds = $this->siteManager->runInSite('forum', function () use ($domain, $source): array {
      return array_values($this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'aculta_forum_topic')
        ->condition('status', 1)
        ->condition('field_domain_source.target_id', $domain->id())
        ->condition('nid', $source->id(), '<>')
        ->sort('title')
        ->range(0, 200)
        ->execute());
    });

    /** @var array<int|string> $ids */
    $ids = is_array($rawIds) ? $rawIds : [];

    $destinations = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($ids) as $node) {
      if ($this->isMovableTopic($node)) {
        $access = $this->siteManager->runInSite(
          'forum',
          fn (): AccessResultInterface => $node->access('view', $account, TRUE),
        );
        if ($access instanceof AccessResultInterface && $access->isAllowed()) {
          $destinations[(int) $node->id()] = (string) $node->label();
        }
      }
    }

    return $destinations;
  }

  /**
   * Moves a ForumPost and its child replies to a destination topic.
   *
   * @return int
   *   Number of posts moved.
   */
  public function move(int $postId, int $destinationTopicId, AccountInterface $account): int {
    $postStorage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    $post = $postStorage->load($postId);
    $destination = $nodeStorage->load($destinationTopicId);

    if (!$post instanceof ForumPost || !$destination instanceof NodeInterface) {
      throw new \InvalidArgumentException('Source post or destination topic does not exist.');
    }

    $sourceTopic = $post->get('topic')->entity;
    if (!$sourceTopic instanceof NodeInterface) {
      throw new \InvalidArgumentException('Source post is not attached to a valid topic.');
    }

    $sourceId = (int) $sourceTopic->id();
    $destinationId = (int) $destination->id();

    if ($sourceId === $destinationId) {
      throw new \InvalidArgumentException('Source and destination topics must be different.');
    }

    if (!$this->access($post, $account)->isAllowed()) {
      throw new \InvalidArgumentException('User is not authorized to move this Forum post.');
    }

    if (!$this->isMovableTopic($destination)) {
      throw new \InvalidArgumentException('Destination topic is not open or not a valid Forum topic.');
    }

    $topicLocks = $this->discussionWriteGuard->acquireTopics([$sourceId, $destinationId]);
    try {
      $subtree = $this->loadPostSubtree($post);

      // Verify view access for all subtree posts.
      foreach ($subtree as $member) {
        $viewAccess = $this->siteManager->runInSite(
          'forum',
          fn (): AccessResultInterface => $member->access('view', $account, TRUE),
        );
        if (!$viewAccess instanceof AccessResultInterface || !$viewAccess->isAllowed()) {
          throw new \InvalidArgumentException('A reply in this post subtree is not accessible.');
        }
      }

      // Next thread position in destination topic.
      $posQuery = $this->database->select('aculta_forum_post_field_data', 'p')
        ->condition('topic', $destinationId);
      $posQuery->addExpression('MAX(thread_position)', 'max_pos');
      $posResult = $posQuery->execute();
      $maxPosition = 0;
      if ($posResult !== NULL) {
        $val = $posResult->fetchField();
        $maxPosition = is_numeric($val) ? (int) $val : 0;
      }

      $nextPosition = $maxPosition + 1;

      $transaction = $this->database->startTransaction();
      try {
        $movedCount = 0;
        foreach ($subtree as $member) {
          $isRoot = ((int) $member->id() === $postId);
          $member->set('topic', $destinationId);
          if ($isRoot) {
            $member->set('parent', NULL);
          }
          $member->set('thread_position', $nextPosition++);
          $member->setNewRevision(TRUE);
          $member->setRevisionCreationTime($this->time->getRequestTime());
          $member->setRevisionUserId((int) $account->id());
          $member->setRevisionLogMessage("Moved to topic #{$destinationId} by moderator uid " . $account->id());
          $member->save();
          $movedCount++;
        }

        $this->cacheTagsInvalidator->invalidateTags([
          'node:' . $sourceId,
          'node:' . $destinationId,
          'aculta_forum_post_list',
        ]);

        $this->logger->notice('Moved @count ForumPost(s) from topic #@source to topic #@dest by uid @uid.', [
          '@count' => (string) $movedCount,
          '@source' => (string) $sourceId,
          '@dest' => (string) $destinationId,
          '@uid' => (string) $account->id(),
        ]);

        return $movedCount;
      }
      catch (\Throwable $e) {
        $transaction->rollBack();
        throw $e;
      }
    }
    finally {
      $this->discussionWriteGuard->release($topicLocks);
    }
  }

  /**
   * Loads a post and all its recursive descendant replies.
   *
   * @return list<ForumPost>
   */
  private function loadPostSubtree(ForumPost $rootPost): array {
    $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $collected = [$rootPost];
    $parentIds = [(int) $rootPost->id()];

    while (!empty($parentIds)) {
      $childIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('parent', $parentIds, 'IN')
        ->sort('thread_position', 'ASC')
        ->execute();

      if (empty($childIds)) {
        break;
      }

      $children = $storage->loadMultiple($childIds);
      $parentIds = [];
      foreach ($children as $child) {
        if ($child instanceof ForumPost) {
          $collected[] = $child;
          $parentIds[] = (int) $child->id();
        }
      }
    }

    return $collected;
  }

  /**
   * Checks whether a topic is an active, open public Forum topic.
   */
  private function isMovableTopic(NodeInterface $topic): bool {
    if ($topic->bundle() !== 'aculta_forum_topic' || !$topic->isPublished()) {
      return FALSE;
    }
    return TRUE;
  }

}
