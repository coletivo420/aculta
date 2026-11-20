<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Analytics\ForumAnalyticsScope;
use Drupal\aculta_forum\Analytics\ForumAnalyticsScopeResolver;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Event\ForumReactionEvent;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Manages discussion reactions, aggregations, and user reaction counters.
 */
class DiscussionReactionManager {

  public const TABLE_REACTIONS = 'aculta_discussion_reaction';
  public const TABLE_STATS = 'aculta_discussion_reaction_user_stats';

  /**
   * Reserved negative reaction, stored in the existing one-vote-per-user row.
   */
  public const DISLIKE_REACTION = ReactionScore::DISLIKE;

  /**
   * An explicit action to remove the current vote from the positive popover.
   *
   * This is NOT a reaction type and must never be stored in the reaction table
   * or shown among the positive emoji options.
   */
  public const UNDO_ACTION = 'undo';

  /**
   * Fixed quick-like emoji. The positive popup remains configurable.
   */
  public const DEFAULT_REACTION = 'broccoli';

  public function __construct(
    protected readonly Connection $database,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ForumAnalyticsScopeResolver $scopeResolver,
    protected readonly EventDispatcherInterface $dispatcher,
    protected readonly TimeInterface $time,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DiscussionOriginResolverInterface $originResolver,
    protected readonly ?LockBackendInterface $lock = NULL,
  ) {}

  /**
   * Retrieves the catalog of allowed reactions from configuration.
   *
   * @return array<string, array{id: string, icon: string, label: string, enabled: bool}>
   *   Enabled reaction definitions keyed by reaction ID.
   */
  public function getAllowedReactions(): array {
    $config = $this->configFactory->get('aculta_forum.settings');
    $raw = $config->get('allowed_reactions');
    $allowed = [];
    if (is_iterable($raw)) {
      foreach ($raw as $item) {
        if (is_array($item) && !empty($item['enabled']) && isset($item['id'], $item['icon'], $item['label']) && is_string($item['id']) && is_string($item['icon']) && is_string($item['label']) && !in_array($item['id'], [self::DISLIKE_REACTION, self::UNDO_ACTION, 'thumbs-down', 'thumbs-down-medium', 'thumbs_down', 'thumbs_down_medium'], TRUE) && !in_array($item['icon'], ['thumbs-down', 'thumbs-down-medium'], TRUE)) {
          $allowed[$item['id']] = [
            'id' => $item['id'],
            'icon' => $item['icon'],
            'label' => $item['label'],
            'enabled' => (bool) $item['enabled'],
          ];
        }
      }
    }
    // The primary Like control is always broccoli even if an old sync import
    // disabled or removed it. Reactions as a whole remain independently gated
    // by reactions_enabled and the permission/access service.
    $broccoli = [
      'id' => self::DEFAULT_REACTION,
      'icon' => self::DEFAULT_REACTION,
      'label' => 'Curtir',
      'enabled' => TRUE,
    ];
    return [self::DEFAULT_REACTION => $broccoli] + array_diff_key($allowed, [self::DEFAULT_REACTION => TRUE]);
  }

  /**
   * Returns the immutable primary quick-like reaction ID.
   */
  public function getDefaultReaction(): string {
    return self::DEFAULT_REACTION;
  }

  /**
   * Validates a positive configured reaction or the separate dislike action.
   */
  public function isAllowedReaction(string $reactionId): bool {
    return $reactionId === self::DISLIKE_REACTION
      || isset($this->getAllowedReactions()[$reactionId]);
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
   *   positive_count: int,
   *   dislike_count: int,
   *   user_reaction: ?string,
   *   reactions: array<
   *     string,
   *     array{id: string, count: int, user_reacted: bool}
   *   >
   *   }
   */
  public function loadReactions(string $entityType, int $entityId, ?int $currentUid = NULL): array {
    $target = $this->loadReactionTarget($entityType, $entityId);
    if ($target === NULL) {
      return $this->emptyReactionSummary();
    }
    $origin = $this->originResolver->classify($target);
    if (!in_array($origin->origin, [DiscussionOrigin::CURRENT, DiscussionOrigin::CONTEXTUAL], TRUE)) {
      return $this->emptyReactionSummary();
    }

    $allowed = $this->getAllowedReactions();
    $result = $this->emptyReactionSummary();

    // Count every persisted positive reaction, including retired emoji IDs.
    // Disabled palette entries must not make historical likes disappear.
    $query = $this->database->select(self::TABLE_REACTIONS, 'r')
      ->fields('r', ['reaction'])
      ->condition('entity_type', $entityType)
      ->condition('entity_id', $entityId);
    $query->addExpression('COUNT(id)', 'reaction_count');
    $query->groupBy('reaction');

    $recordsRaw = $query->execute()?->fetchAllKeyed();
    $records = is_array($recordsRaw) ? $recordsRaw : [];
    $score = ReactionScore::summarize($records);
    $result['positive_count'] = $score['positive_count'];
    $result['dislike_count'] = $score['dislike_count'];
    $result['total_count'] = $score['total_count'];

    if ($currentUid !== NULL && $currentUid > 0) {
      $userRec = $this->database->select(self::TABLE_REACTIONS, 'r')
        ->fields('r', ['reaction'])
        ->condition('entity_type', $entityType)
        ->condition('entity_id', $entityId)
        ->condition('uid', $currentUid)
        ->range(0, 1)
        ->execute()
        ?->fetchField();
      if (is_scalar($userRec) && (string) $userRec !== '') {
        $result['user_reaction'] = (string) $userRec;
      }
    }

    foreach ($allowed as $id => $item) {
      $count = isset($records[$id]) && is_numeric($records[$id]) ? (int) $records[$id] : 0;
      $isUserReacted = ($result['user_reaction'] === $id);
      if ($count > 0 || $isUserReacted) {
        $result['reactions'][$id] = [
          'id' => $id,
          'count' => $count,
          'user_reacted' => $isUserReacted,
        ];
      }
    }

    return $result;
  }

  /**
   * Loads only supported entity types before exposing their reaction summary.
   */
  private function loadReactionTarget(string $entityType, int $entityId): ?EntityInterface {
    if ($entityId <= 0 || !in_array($entityType, ['comment', 'aculta_forum_post', 'node'], TRUE)) {
      return NULL;
    }
    $target = $this->entityTypeManager->getStorage($entityType)->load($entityId);
    return $target instanceof EntityInterface ? $target : NULL;
  }

  /**
   * Returns an empty summary without leaking rows from an unsupported origin.
   *
   * @return array{total_count: int, positive_count: int, dislike_count: int, user_reaction: ?string, reactions: array<string, array{id: string, count: int, user_reacted: bool}>}
   *   Empty reaction summary.
   */
  private function emptyReactionSummary(): array {
    return [
      'total_count' => 0,
      'positive_count' => 0,
      'dislike_count' => 0,
      'user_reaction' => NULL,
      'reactions' => [],
    ];
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
   *   }
   */
  public function toggleReaction(EntityInterface $entity, int $uid, string $reactionId): array {
    if ($reactionId !== self::UNDO_ACTION && !$this->isAllowedReaction($reactionId)) {
      throw new \InvalidArgumentException(sprintf('Reaction "%s" is not allowed.', $reactionId));
    }

    $entityType = $entity->getEntityTypeId();
    $entityId = (int) $entity->id();
    $authorUid = $entity instanceof EntityOwnerInterface ? (int) $entity->getOwnerId() : 0;

    // O escopo é propriedade do conteúdo, não da conta que reage. Um
    // administrador lendo um tópico restrito não torna a reação pública.
    $scope = $this->scopeResolver->resolve($entity);
    $host = $scope->host ?? $this->resolveHost($entity);
    $contextId = $scope->scope;
    // Reações da Revista contam no total de recebidas, como no fórum público.
    $isPublic = $scope->isPublicForum() || $scope->scope === ForumAnalyticsScope::PUBLICATION;

    // Serialize the one-reaction-per-user decision BEFORE reading a prior
    // vote. A unique database key is still the last line of defense.
    $lockName = sprintf('aculta_forum:reaction:%s:%d:%d', $entityType, $entityId, $uid);
    $locked = FALSE;
    if ($this->lock !== NULL) {
      $locked = $this->lock->acquire($lockName, 30.0);
      if (!$locked) {
        throw new ConflictHttpException('Reação em andamento. Tente novamente.');
      }
    }

    try {
      $existing = $this->database->select(self::TABLE_REACTIONS, 'r')
        ->fields('r', ['id', 'reaction', 'is_public_context'])
        ->condition('entity_type', $entityType)
        ->condition('entity_id', $entityId)
        ->condition('uid', $uid)
        ->range(0, 1)
        ->execute()
        ?->fetchAssoc();

      $now = $this->time->getRequestTime();
      $transaction = $this->database->startTransaction();

      try {
      if (is_array($existing) && !empty($existing['id'])) {
        $existingId = is_numeric($existing['id']) ? (int) $existing['id'] : (is_scalar($existing['id']) ? (string) $existing['id'] : 0);
        $prevReaction = isset($existing['reaction']) && is_scalar($existing['reaction']) ? (string) $existing['reaction'] : '';
        // O decremento usa o escopo gravado no momento da reação. Recalcular
        // a visibilidade atual e subtrair de um contador antigo desalinharia
        // agregados quando o conteúdo mudou de escopo.
        $wasPublic = isset($existing['is_public_context']) && is_numeric($existing['is_public_context']) && (int) $existing['is_public_context'] === 1;
        $wasPositive = $wasPublic && $prevReaction !== self::DISLIKE_REACTION;
        $isPositive = $isPublic && $reactionId !== self::DISLIKE_REACTION;

        if ($prevReaction === $reactionId || $reactionId === self::UNDO_ACTION) {
          // Same selected emoji or explicit Undo: remove the current vote.
          $this->database->delete(self::TABLE_REACTIONS)
            ->condition('id', $existingId)
            ->execute();

          if ($wasPositive) {
            $this->decrementStats($uid, $authorUid);
          }
          $this->invalidateEntityCache($entity, $uid, $authorUid);

          return [
            'action' => 'removed',
            'previous_reaction' => $prevReaction,
            'current_reaction' => NULL,
          ];
        }

        // Different emoji: update reaction and reconcile the stored scope.
        $this->database->update(self::TABLE_REACTIONS)
          ->fields([
            'reaction' => $reactionId,
            'changed' => $now,
            'context_id' => $contextId,
            'is_public_context' => $isPublic ? 1 : 0,
            'host_entity_type' => $host ? $host->getEntityTypeId() : '',
            'host_entity_id' => $host ? (int) $host->id() : 0,
          ])
          ->condition('id', $existingId)
          ->execute();

        if ($wasPositive !== $isPositive) {
          if ($wasPositive) {
            $this->decrementStats($uid, $authorUid);
          }
          else {
            $this->incrementStats($uid, $authorUid);
          }
        }
        if (!$wasPositive && $isPositive) {
          $this->dispatcher->dispatch(new ForumReactionEvent($reactionId), ForumReactionEvent::ADDED);
        }

        $this->invalidateEntityCache($entity, $uid, $authorUid);

        return [
          'action' => 'updated',
          'previous_reaction' => $prevReaction,
          'current_reaction' => $reactionId,
        ];
      }

      // Undo on a fresh/unvoted item is a no-op. Never persist "undo".
      if ($reactionId === self::UNDO_ACTION) {
        return [
          'action' => 'unchanged',
          'previous_reaction' => NULL,
          'current_reaction' => NULL,
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
          'is_public_context' => $isPublic ? 1 : 0,
          'uid' => $uid,
          'author_uid' => $authorUid,
          'reaction' => $reactionId,
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();

      if ($isPublic && $reactionId !== self::DISLIKE_REACTION) {
        $this->incrementStats($uid, $authorUid);
        // Events and existing user stats represent positive reactions only.
        $this->dispatcher->dispatch(new ForumReactionEvent($reactionId), ForumReactionEvent::ADDED);
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
      finally {
        // Finish the Drupal transaction before releasing the vote lock.
        unset($transaction);
      }
    }
    finally {
      if ($locked && $this->lock !== NULL) {
        $this->lock->release($lockName);
      }
    }
  }

  /**
   * Resolves the host entity for a Comment, ForumPost or topic.
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
      // Tópicos independentes públicos, sem Group restrito direto ou herdado.
      if ($this->database->schema()->tableExists('node_field_data')) {
        $topicQuery = $this->database->select('node_field_data', 'n');
        $topicQuery->condition('n.uid', $uid);
        if ($this->scopeResolver->applyPublicTopicConditions($topicQuery, 'n')) {
          $val = $topicQuery->countQuery()->execute()?->fetchField();
          if (is_numeric($val)) {
            $count += (int) $val;
          }
        }
      }

      // Comments legados em tópicos públicos do Fórum.
      if ($this->database->schema()->tableExists('comment_field_data')
        && $this->database->schema()->tableExists('node_field_data')) {
        $commentQuery = $this->database->select('comment_field_data', 'c');
        $commentQuery->join('node_field_data', 'n', 'n.nid = c.entity_id');
        $commentQuery->condition('c.uid', $uid);
        $commentQuery->condition('c.status', 1);
        $commentQuery->condition('c.comment_type', 'aculta_discussion_post');
        $commentQuery->condition('c.default_langcode', 1);
        if ($this->scopeResolver->applyPublicTopicConditions($commentQuery, 'n')) {
          $val = $commentQuery->countQuery()->execute()?->fetchField();
          if (is_numeric($val)) {
            $count += (int) $val;
          }
        }
      }

      // Posts revisionáveis em tópicos públicos do Fórum.
      if ($this->database->schema()->tableExists('aculta_forum_post_field_data')
        && $this->database->schema()->tableExists('node_field_data')) {
        $postQuery = $this->database->select('aculta_forum_post_field_data', 'p');
        $postQuery->join('node_field_data', 'n', 'n.nid = p.topic');
        $postQuery->condition('p.uid', $uid);
        $postQuery->condition('p.status', 1);
        $postQuery->condition('p.default_langcode', 1);
        if ($this->scopeResolver->applyPublicTopicConditions($postQuery, 'n')) {
          $val = $postQuery->countQuery()->execute()?->fetchField();
          if (is_numeric($val)) {
            $count += (int) $val;
          }
        }
      }
    }
    catch (\Throwable) {
      // Fail closed: ambiente mínimo ou falha de consulta não produz contagem
      // pública a partir de dados que não puderam ser validados.
      $count = 0;
    }

    $this->authorPostCounts[$uid] = $count;
    return $count;
  }

  /**
   * Request-local memoization for user aggregate stats (many cards per page).
   *
   * @var array<int, array{reactions_received: int, reactions_given: int}>
   */
  private array $userStatsCache = [];

  /**
   * Retrieves aggregated reaction statistics for a user.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return array{reactions_received: int, reactions_given: int}
   *   Reaction totals received and given by the user.
   */
  public function getUserStats(int $uid): array {
    if ($uid <= 0) {
      return ['reactions_received' => 0, 'reactions_given' => 0];
    }
    if (isset($this->userStatsCache[$uid])) {
      return $this->userStatsCache[$uid];
    }

    $record = $this->database->select(self::TABLE_STATS, 's')
      ->fields('s', ['reactions_received', 'reactions_given'])
      ->condition('uid', $uid)
      ->range(0, 1)
      ->execute()
      ?->fetchAssoc();

    if (is_array($record)) {
      // The O(1) legacy aggregate holds positive reactions only. Subtract
      // current dislikes from the author's displayed received total.
      $dislikeRaw = $this->database->select(self::TABLE_REACTIONS, 'r')
        ->condition('author_uid', $uid)
        ->condition('is_public_context', 1)
        ->condition('reaction', self::DISLIKE_REACTION)
        ->countQuery()
        ->execute()
        ?->fetchField();
      $dislikes = is_numeric($dislikeRaw) ? max(0, (int) $dislikeRaw) : 0;
      $positiveReceived = isset($record['reactions_received']) && is_numeric($record['reactions_received'])
        ? max(0, (int) $record['reactions_received']) : 0;
      return $this->userStatsCache[$uid] = [
        'reactions_received' => max(0, $positiveReceived - $dislikes),
        'reactions_given' => isset($record['reactions_given']) && is_numeric($record['reactions_given']) ? max(0, (int) $record['reactions_given']) : 0,
      ];
    }

    return $this->userStatsCache[$uid] = ['reactions_received' => 0, 'reactions_given' => 0];
  }

  /**
   * Increments stats for giver and receiver.
   */
  protected function incrementStats(int $giverUid, int $authorUid): void {
    $now = $this->time->getRequestTime();
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
    $now = $this->time->getRequestTime();
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
    // Reactions may have been read earlier in the very same POST request.
    unset($this->userStatsCache[$giverUid], $this->userStatsCache[$authorUid]);
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
