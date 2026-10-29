<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Saved;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides access to user saved content and follow flags without parallel storage.
 */
class SavedContentService {

  /**
   * Known flag IDs for saved items.
   */
  public const SAVED_FLAGS = [
    'salvar_topico',
    'salvar_conteudo',
    'bookmark',
  ];

  /**
   * Known flag IDs for followed items.
   */
  public const FOLLOW_FLAGS = [
    'seguir_topico',
    'seguir_publicacao',
    'following',
  ];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Connection $database,
    protected readonly ?object $flagService = NULL,
  ) {}

  /**
   * Gets saved items flagged by the given account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to inspect.
   * @param string|null $category
   *   Optional category filter: 'saved', 'following', or a specific flag_id.
   * @param int $limit
   *   Maximum number of items to return.
   * @param int $offset
   *   Query offset.
   *
   * @return array<int, array{
   *   entity_type: string,
   *   entity_id: string|int,
   *   entity: \Drupal\Core\Entity\EntityInterface,
   *   flag_id: string,
   *   created: int,
   *   category: string
   * }>
   *   Authorized saved items, ordered newest first.
   */
  public function getSavedItems(
    AccountInterface $account,
    ?string $category = 'saved',
    int $limit = 20,
    int $offset = 0,
  ): array {
    if (!$account->isAuthenticated()) {
      return [];
    }

    if (!$this->database->schema()->tableExists('flagging')) {
      return [];
    }

    $query = $this->database->select('flagging', 'f')
      ->fields('f', ['flag_id', 'entity_type', 'entity_id', 'created'])
      ->condition('f.uid', (int) $account->id());

    if ($category === 'saved') {
      $query->condition('f.flag_id', self::SAVED_FLAGS, 'IN');
    }
    elseif ($category === 'following') {
      $query->condition('f.flag_id', self::FOLLOW_FLAGS, 'IN');
    }
    elseif ($category !== NULL && $category !== 'all') {
      $query->condition('f.flag_id', $category);
    }
    else {
      // Exclude moderation/abuse flags from personal bookmarks.
      $query->condition('f.flag_id', ['abuse', 'sinalizar_topico', 'sinalizar_comentario', 'sinalizar_post_forum', 'claim_ownership'], 'NOT IN');
    }

    $query->orderBy('f.created', 'DESC');
    $query->range($offset, $limit);
    $records = $query->execute()->fetchAll();

    if (empty($records)) {
      return [];
    }

    // Group entity IDs by entity type to load multiple.
    $groupedIds = [];
    foreach ($records as $row) {
      $groupedIds[$row->entity_type][] = $row->entity_id;
    }

    $loadedEntities = [];
    foreach ($groupedIds as $entityType => $ids) {
      if ($this->entityTypeManager->hasDefinition($entityType)) {
        $storage = $this->entityTypeManager->getStorage($entityType);
        $loadedEntities[$entityType] = $storage->loadMultiple($ids);
      }
    }

    $results = [];
    foreach ($records as $row) {
      $type = $row->entity_type;
      $id = $row->entity_id;
      if (isset($loadedEntities[$type][$id])) {
        $entity = $loadedEntities[$type][$id];
        // Enforce strict access check before exposing any saved item.
        if ($entity instanceof EntityInterface && $entity->access('view', $account)) {
          $isFollow = in_array($row->flag_id, self::FOLLOW_FLAGS, TRUE);
          $results[] = [
            'entity_type' => $type,
            'entity_id' => $id,
            'entity' => $entity,
            'flag_id' => $row->flag_id,
            'created' => (int) $row->created,
            'category' => $isFollow ? 'following' : 'saved',
          ];
        }
      }
    }

    return $results;
  }

  /**
   * Returns count of saved and followed items for an account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to inspect.
   *
   * @return array{saved: int, following: int, total: int}
   *   Aggregated counts.
   */
  public function getSavedCounts(AccountInterface $account): array {
    if (!$account->isAuthenticated() || !$this->database->schema()->tableExists('flagging')) {
      return ['saved' => 0, 'following' => 0, 'total' => 0];
    }

    $saved = (int) $this->database->select('flagging', 'f')
      ->condition('f.uid', (int) $account->id())
      ->condition('f.flag_id', self::SAVED_FLAGS, 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();

    $following = (int) $this->database->select('flagging', 'f')
      ->condition('f.uid', (int) $account->id())
      ->condition('f.flag_id', self::FOLLOW_FLAGS, 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();

    return [
      'saved' => $saved,
      'following' => $following,
      'total' => $saved + $following,
    ];
  }

  /**
   * Checks if a specific entity is flagged as saved by the user.
   */
  public function isSaved(AccountInterface $account, string $entityType, int|string $entityId): bool {
    if (!$account->isAuthenticated() || !$this->database->schema()->tableExists('flagging')) {
      return FALSE;
    }

    $count = (int) $this->database->select('flagging', 'f')
      ->condition('f.uid', (int) $account->id())
      ->condition('f.entity_type', $entityType)
      ->condition('f.entity_id', (string) $entityId)
      ->condition('f.flag_id', self::SAVED_FLAGS, 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count > 0;
  }

  /**
   * Gets cache tags for saved content queries for an account.
   */
  public function getCacheTags(AccountInterface $account): array {
    return [
      'flagging_list',
      'user:' . $account->id(),
    ];
  }

}
