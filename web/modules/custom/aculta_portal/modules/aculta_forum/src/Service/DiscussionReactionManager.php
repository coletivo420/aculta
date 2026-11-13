<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Analytics\ForumAnalyticsScopeResolver;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Event\ForumReactionEvent;
use Drupal\comment\CommentInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages discussion reactions, aggregations, and user reaction counters.
 */
class DiscussionReactionManager {

  public const TABLE_REACTIONS = 'aculta_discussion_reaction';
  public const TABLE_STATS = 'aculta_discussion_reaction_user_stats';

  public function __construct(
    protected readonly Connection $database,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ForumAnalyticsScopeResolver $scopeResolver,
    protected readonly EventDispatcherInterface $dispatcher,
    protected readonly TimeInterface $time,
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
    return (string) ($this->configFactory->get('aculta_forum.settings')->get('default_reaction') ?: 'broccoli');
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

    // O escopo é propriedade do conteúdo, não da conta que reage. Um
    // administrador lendo um tópico restrito não torna a reação pública.
    $scope = $this->scopeResolver->resolve($entity);
    $host = $scope->host ?? $this->resolveHost($entity);
    $contextId = $scope->scope;
    $isPublic = $scope->isPublicForum();

    $existing = $this->database->select(self::TABLE_REACTIONS, 'r')
      ->fields('r', ['id', 'reaction', 'is_public_context'])
      ->condition('entity_type', $entityType)
      ->condition('entity_id', $entityId)
      ->condition('uid', $uid)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();

    try {
      if ($existing) {
        $prevReaction = (string) $existing['reaction'];
        // O decremento usa o escopo gravado no momento da reação. Recalcular
        // a visibilidade atual e subtrair de um contador antigo desalinharia
        // agregados quando o conteúdo mudou de escopo.
        $wasPublic = (int) $existing['is_public_context'] === 1;
        if ($prevReaction === $reactionId) {
          // Same emoji: remove reaction.
          $this->database->delete(self::TABLE_REACTIONS)
            ->condition('id', $existing['id'])
            ->execute();

          if ($wasPublic) {
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
          ->condition('id', $existing['id'])
          ->execute();

        if ($wasPublic !== $isPublic) {
          if ($wasPublic) {
            $this->decrementStats($uid, $authorUid);
          }
          else {
            $this->incrementStats($uid, $authorUid);
          }
        }

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
          'is_public_context' => $isPublic ? 1 : 0,
          'uid' => $uid,
          'author_uid' => $authorUid,
          'reaction' => $reactionId,
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();

      if ($isPublic) {
        $this->incrementStats($uid, $authorUid);
        // Só a inclusão nova e pública gera `added`; troca e remoção não.
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
      // Tópicos independentes públicos, sem Group restrito direto ou herdado.
      if ($this->database->schema()->tableExists('node_field_data')) {
        $topicQuery = $this->database->select('node_field_data', 'n');
        $topicQuery->condition('n.uid', $uid);
        if ($this->scopeResolver->applyPublicTopicConditions($topicQuery, 'n')) {
          $count += (int) $topicQuery->countQuery()->execute()->fetchField();
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
          $count += (int) $commentQuery->countQuery()->execute()->fetchField();
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
          $count += (int) $postQuery->countQuery()->execute()->fetchField();
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
