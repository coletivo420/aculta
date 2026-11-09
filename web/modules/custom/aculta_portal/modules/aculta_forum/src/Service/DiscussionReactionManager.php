<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\comment\CommentInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Manages discussion reactions, aggregations, and user reaction counters.
 */
class DiscussionReactionManager {

  public const TABLE_REACTIONS = 'aculta_discussion_reaction';
  public const TABLE_STATS = 'aculta_discussion_reaction_user_stats';

  public function __construct(
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DiscussionContextResolver $contextResolver,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Retrieves the catalog of allowed reactions from configuration.
   *
   * @return array<string, array{id: string, icon: string, label: string, enabled: bool}>
   */
  public function getAllowedReactions(): array {
    $config = $this->configFactory->get('aculta_forum.settings');
    $raw = $config->get('allowed_reactions') ?: [];
    $allowed = [];
    foreach ($raw as $item) {
      if (!empty($item['enabled'])) {
        $allowed[$item['id']] = $item;
      }
    }
    return $allowed;
  }

  /**
   * Returns default reaction ID.
   */
  public function getDefaultReaction(): string {
    return (string) ($this->configFactory->get('aculta_forum.settings')->get('default_reaction') ?: 'heart');
  }

  /**
   * Loads aggregated reaction summary for a target entity.
   *
   * @param string $entityType
   *   The entity type (comment or aculta_forum_post).
   * @param int $entityId
   *   The entity ID.
   * @param int|null $currentUid
   *   Optional current user ID to determine user's active reaction.
   *
   * @return array{
   *   total_count: int,
   *   user_reaction: ?string,
   *   reactions: array<string, array{id: string, count: int, user_reacted: bool}>
   * }
   */
  public function loadReactions(string $entityType, int $entityId, ?int $currentUid = NULL): array {
    $allowed = $this->getAllowedReactions();
    $result = [
      'total_count' => 0,
      'user_reaction' => NULL,
      'reactions' => [],
    ];

    if (empty($allowed)) {
      return $result;
    }

    $query = $this->database->select(self::TABLE_REACTIONS, 'r')
      ->fields('r', ['reaction'])
      ->condition('entity_type', $entityType)
      ->condition('entity_id', $entityId);
    $query->addExpression('COUNT(id)', 'reaction_count');
    $query->groupBy('reaction');

    $records = $query->execute()->fetchAllKeyed();

    if ($currentUid !== NULL && $currentUid > 0) {
      $userRec = $this->database->select(self::TABLE_REACTIONS, 'r')
        ->fields('r', ['reaction'])
        ->condition('entity_type', $entityType)
        ->condition('entity_id', $entityId)
        ->condition('uid', $currentUid)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($userRec) {
        $result['user_reaction'] = (string) $userRec;
      }
    }

    foreach ($allowed as $id => $item) {
      $count = isset($records[$id]) ? (int) $records[$id] : 0;
      $isUserReacted = ($result['user_reaction'] === $id);
      if ($count > 0 || $isUserReacted) {
        $result['reactions'][$id] = [
          'id' => $id,
          'count' => $count,
          'user_reacted' => $isUserReacted,
        ];
        $result['total_count'] += $count;
      }
    }

    return $result;
  }

  /**
   * Toggles, changes, or removes a user's reaction.
   *
   * @param EntityInterface $entity
   *   The entity reacted to (Comment or ForumPost).
   * @param int $uid
   *   The user reacting.
   * @param string $reactionId
   *   The reaction machine name.
   *
   * @return array{
   *   action: string,
   *   previous_reaction: ?string,
   *   current_reaction: ?string,
   * }
   */
  public function toggleReaction(EntityInterface $entity, int $uid, string $reactionId): array {
    $allowed = $this->getAllowedReactions();
    if (!isset($allowed[$reactionId])) {
      throw new \InvalidArgumentException(sprintf('Reaction "%s" is not allowed.', $reactionId));
    }

    $entityType = $entity->getEntityTypeId();
    $entityId = (int) $entity->id();
    $authorUid = $entity instanceof EntityOwnerInterface ? (int) $entity->getOwnerId() : 0;

    // Resolve context & public status.
    $host = $this->resolveHost($entity);
    $contextId = 'unknown';
    $isPublic = 1;

    if ($host !== NULL) {
      $account = $this->entityTypeManager->getStorage('user')->load($uid);
      if ($account) {
        $context = $this->contextResolver->resolve($host, $account);
        if ($context !== NULL) {
          $contextId = $context->getType();
          $isPublic = ($context->getVisibilityPolicy() === 'group_members_only') ? 0 : 1;
        }
      }
    }

    $existing = $this->database->select(self::TABLE_REACTIONS, 'r')
      ->fields('r', ['id', 'reaction'])
      ->condition('entity_type', $entityType)
      ->condition('entity_id', $entityId)
      ->condition('uid', $uid)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $now = \Drupal::time()->getRequestTime();
    $transaction = $this->database->startTransaction();

    try {
      if ($existing) {
        $prevReaction = (string) $existing['reaction'];
        if ($prevReaction === $reactionId) {
          // Same emoji: remove reaction.
          $this->database->delete(self::TABLE_REACTIONS)
            ->condition('id', $existing['id'])
            ->execute();

          if ($isPublic) {
            $this->decrementStats($uid, $authorUid);
          }
          $this->invalidateEntityCache($entity, $uid, $authorUid);

          return [
            'action' => 'removed',
            'previous_reaction' => $prevReaction,
            'current_reaction' => NULL,
          ];
        }

        // Different emoji: update reaction.
        $this->database->update(self::TABLE_REACTIONS)
          ->fields([
            'reaction' => $reactionId,
            'changed' => $now,
          ])
          ->condition('id', $existing['id'])
          ->execute();

        $this->invalidateEntityCache($entity, $uid, $authorUid);

        return [
          'action' => 'updated',
          'previous_reaction' => $prevReaction,
          'current_reaction' => $reactionId,
        ];
      }

      // Insert new reaction.
      $hostEntityType = $host ? $host->getEntityTypeId() : '';
      $hostEntityId = $host ? (int) $host->id() : 0;

      $this->database->insert(self::TABLE_REACTIONS)
        ->fields([
          'entity_type' => $entityType,
          'entity_id' => $entityId,
          'host_entity_type' => $hostEntityType,
          'host_entity_id' => $hostEntityId,
          'context_id' => $contextId,
          'is_public_context' => $isPublic,
          'uid' => $uid,
          'author_uid' => $authorUid,
          'reaction' => $reactionId,
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();

      if ($isPublic) {
        $this->incrementStats($uid, $authorUid);
      }
      $this->invalidateEntityCache($entity, $uid, $authorUid);

      return [
        'action' => 'added',
        'previous_reaction' => NULL,
        'current_reaction' => $reactionId,
      ];
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Resolves the host discussion entity for a Comment, ForumPost, or Topic Node.
   */
  public function resolveHost(EntityInterface $entity): ?EntityInterface {
    if ($entity instanceof CommentInterface) {
      return $entity->getCommentedEntity();
    }
    if ($entity instanceof ForumPost) {
      return $entity->get('topic')->entity;
    }
    if ($entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic') {
      return $entity;
    }
    return NULL;
  }

  /**
   * Request-local cache of public post counts per user.
   *
   * @var array<int, int>
   */
  private array $authorPostCounts = [];

  /**
   * Retrieves the total public post/comment count for an author in discussions.
   */
  public function getAuthorPublicPostCount(int $uid): int {
    if ($uid <= 0) {
      return 0;
    }
    if (isset($this->authorPostCounts[$uid])) {
      return $this->authorPostCounts[$uid];
    }

    $count = 0;
    try {
      if ($this->database->schema()->tableExists('comment_field_data')) {
        $commentQuery = $this->database->select('comment_field_data', 'c');
        $commentQuery->condition('c.uid', $uid);
        $commentQuery->condition('c.status', 1);
        $commentQuery->condition('c.comment_type', 'aculta_discussion_post');
        $commentQuery->condition('c.default_langcode', 1);
        $count += (int) $commentQuery->countQuery()->execute()->fetchField();
      }
      if ($this->database->schema()->tableExists('aculta_forum_post_field_data')) {
        $postQuery = $this->database->select('aculta_forum_post_field_data', 'p');
        $postQuery->condition('p.uid', $uid);
        $postQuery->condition('p.status', 1);
        $postQuery->condition('p.default_langcode', 1);
        $count += (int) $postQuery->countQuery()->execute()->fetchField();
      }
      if ($this->database->schema()->tableExists('node_field_data')) {
        $topicQuery = $this->database->select('node_field_data', 'n');
        $topicQuery->condition('n.uid', $uid);
        $topicQuery->condition('n.status', 1);
        $topicQuery->condition('n.type', 'aculta_forum_topic');
        $topicQuery->condition('n.default_langcode', 1);
        $count += (int) $topicQuery->countQuery()->execute()->fetchField();
      }
    }
    catch (\Throwable) {
      // Fallback safely if tables do not exist in minimal test environments.
    }

    $this->authorPostCounts[$uid] = $count;
    return $count;
  }

  /**
   * Retrieves aggregated reaction statistics for a user.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return array{reactions_received: int, reactions_given: int}
   */
  public function getUserStats(int $uid): array {
    if ($uid <= 0) {
      return ['reactions_received' => 0, 'reactions_given' => 0];
    }

    $record = $this->database->select(self::TABLE_STATS, 's')
      ->fields('s', ['reactions_received', 'reactions_given'])
      ->condition('uid', $uid)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if ($record) {
      return [
        'reactions_received' => (int) $record['reactions_received'],
        'reactions_given' => (int) $record['reactions_given'],
      ];
    }

    return ['reactions_received' => 0, 'reactions_given' => 0];
  }

  /**
   * Increments stats for giver and receiver.
   */
  protected function incrementStats(int $giverUid, int $authorUid): void {
    $now = \Drupal::time()->getRequestTime();
    if ($giverUid > 0) {
      $this->database->merge(self::TABLE_STATS)
        ->key('uid', $giverUid)
        ->fields(['updated' => $now])
        ->expression('reactions_given', 'reactions_given + 1')
        ->insertFields([
          'uid' => $giverUid,
          'reactions_given' => 1,
          'reactions_received' => 0,
          'updated' => $now,
        ])
        ->execute();
    }
    if ($authorUid > 0 && $authorUid !== $giverUid) {
      $this->database->merge(self::TABLE_STATS)
        ->key('uid', $authorUid)
        ->fields(['updated' => $now])
        ->expression('reactions_received', 'reactions_received + 1')
        ->insertFields([
          'uid' => $authorUid,
          'reactions_given' => 0,
          'reactions_received' => 1,
          'updated' => $now,
        ])
        ->execute();
    }
  }

  /**
   * Decrements stats for giver and receiver.
   */
  protected function decrementStats(int $giverUid, int $authorUid): void {
    $now = \Drupal::time()->getRequestTime();
    if ($giverUid > 0) {
      $this->database->update(self::TABLE_STATS)
        ->expression('reactions_given', 'GREATEST(0, CAST(reactions_given AS SIGNED) - 1)')
        ->fields(['updated' => $now])
        ->condition('uid', $giverUid)
        ->execute();
    }
    if ($authorUid > 0 && $authorUid !== $giverUid) {
      $this->database->update(self::TABLE_STATS)
        ->expression('reactions_received', 'GREATEST(0, CAST(reactions_received AS SIGNED) - 1)')
        ->fields(['updated' => $now])
        ->condition('uid', $authorUid)
        ->execute();
    }
  }

  /**
   * Invalidates render cache for the reacted entity.
   */
  protected function invalidateEntityCache(EntityInterface $entity, int $giverUid = 0, int $authorUid = 0): void {
    $tags = $entity->getCacheTags();
    $tags[] = 'aculta_discussion_reactions:' . $entity->getEntityTypeId() . ':' . $entity->id();
    if ($giverUid > 0) {
      $tags[] = 'aculta_user_reactions:' . $giverUid;
    }
    if ($authorUid > 0) {
      $tags[] = 'aculta_user_reactions:' . $authorUid;
    }
    Cache::invalidateTags($tags);
  }

}
