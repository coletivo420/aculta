<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Saved;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Universal implementation of SavedContentQueryInterface backed by Drupal Flag storage.
 */
class SavedContentQuery implements SavedContentQueryInterface {

  /**
   * Known flag IDs categorized as bookmarks/saved.
   */
  protected const SAVED_FLAGS = [
    'salvar_topico',
    'salvar_conteudo',
    'bookmark',
  ];

  /**
   * Known flag IDs categorized as followed items.
   */
  protected const FOLLOW_FLAGS = [
    'seguir_topico',
    'seguir_publicacao',
    'following',
  ];

  /**
   * Bundle to purpose mapping for canonical domain purposes.
   */
  protected const BUNDLE_PURPOSE_MAP = [
    'aculta_forum_topic' => 'forum',
    'revista' => 'magazine',
    'aculta_event' => 'eventos',
    'lms_course' => 'courses',
    'wiki_article' => 'wiki',
  ];

  public function __construct(
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ?object $domainPurposeManager = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function query(SavedContentFilter $filter): SavedContentQueryResult {
    $account = $filter->getAccount();
    if (!$account->isAuthenticated() || !$this->database->schema()->tableExists('flagging')) {
      return new SavedContentQueryResult([], 0, FALSE, ['user'], ['flagging_list']);
    }

    $baseQuery = $this->database->select('flagging', 'f')
      ->fields('f', ['flag_id', 'entity_type', 'entity_id', 'created'])
      ->condition('f.uid', (int) $account->id())
      ->condition('f.flag_id', self::EXCLUDED_FLAGS, 'NOT IN');

    $category = $filter->getCategory();
    if ($category === self::CATEGORY_SAVED) {
      $baseQuery->condition('f.flag_id', self::SAVED_FLAGS, 'IN');
    }
    elseif ($category === self::CATEGORY_FOLLOWING) {
      $baseQuery->condition('f.flag_id', self::FOLLOW_FLAGS, 'IN');
    }
    elseif ($category !== NULL && $category !== 'all') {
      $baseQuery->condition('f.flag_id', $category);
    }

    if ($filter->getEntityTypeId() !== NULL) {
      $baseQuery->condition('f.entity_type', $filter->getEntityTypeId());
    }

    $baseQuery->orderBy('f.created', 'DESC');

    // Fetch candidate rows. To handle pagination with post-access filtering safely,
    // we fetch a bounded batch of rows.
    $offset = $filter->getOffset();
    $limit = $filter->getLimit();

    // Query candidates with window headroom to account for unpublished/restricted entities.
    $fetchLimit = max($limit * 3, 60);
    $recordsQuery = clone $baseQuery;
    $recordsQuery->range($offset, $fetchLimit);
    $records = $recordsQuery->execute()->fetchAll();

    if (empty($records)) {
      return new SavedContentQueryResult([], 0, FALSE, ['user', 'user.permissions'], ['flagging_list', 'user:' . $account->id()]);
    }

    // Group IDs by entity type for batch loading.
    $groupedIds = [];
    foreach ($records as $row) {
      $groupedIds[$row->entity_type][] = $row->entity_id;
    }

    $loadedEntities = [];
    foreach ($groupedIds as $entityType => $ids) {
      if ($this->entityTypeManager->hasDefinition($entityType)) {
        try {
          $storage = $this->entityTypeManager->getStorage($entityType);
          $loadedEntities[$entityType] = $storage->loadMultiple($ids);
        }
        catch (\Throwable) {
          $loadedEntities[$entityType] = [];
        }
      }
    }

    $items = [];
    $cacheTags = ['flagging_list', 'user:' . $account->id()];
    $purposeFilter = $filter->getPurpose();
    $bundleFilter = $filter->getBundle();

    foreach ($records as $row) {
      $type = $row->entity_type;
      $id = $row->entity_id;

      // 1. Safe handling of deleted entities (flagging row exists, entity does not).
      if (!isset($loadedEntities[$type][$id])) {
        continue;
      }

      $entity = $loadedEntities[$type][$id];
      if (!$entity instanceof EntityInterface) {
        continue;
      }

      // 2. Strict Entity Access enforcement: unpublished or restricted entities must never leak.
      if (!$entity->access('view', $account)) {
        continue;
      }

      // 3. Optional bundle filter.
      $bundle = $entity->bundle();
      if ($bundleFilter !== NULL && $bundle !== $bundleFilter) {
        continue;
      }

      // 4. Resolve purpose and apply purpose filter if requested.
      $purpose = $this->resolveEntityPurpose($entity);
      if ($purposeFilter !== NULL && $purpose !== $purposeFilter) {
        continue;
      }

      $isFollow = in_array($row->flag_id, self::FOLLOW_FLAGS, TRUE);
      $cat = $isFollow ? self::CATEGORY_FOLLOWING : self::CATEGORY_SAVED;

      $items[] = new SavedContentItem(
        $type,
        $id,
        $entity,
        $row->flag_id,
        (int) $row->created,
        $cat,
        $purpose,
      );

      // Collect entity cache tags.
      $cacheTags = array_merge($cacheTags, $entity->getCacheTags());

      if (count($items) >= $limit) {
        break;
      }
    }

    $cacheTags = array_values(array_unique($cacheTags));
    $hasMore = count($records) > count($items);

    return new SavedContentQueryResult(
      $items,
      count($items),
      $hasMore,
      ['user', 'user.permissions'],
      $cacheTags
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCounts(AccountInterface $account): array {
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
   * {@inheritdoc}
   */
  public function isSaved(AccountInterface $account, string $entityTypeId, string|int $entityId): bool {
    if (!$account->isAuthenticated() || !$this->database->schema()->tableExists('flagging')) {
      return FALSE;
    }

    $count = (int) $this->database->select('flagging', 'f')
      ->condition('f.uid', (int) $account->id())
      ->condition('f.entity_type', $entityTypeId)
      ->condition('f.entity_id', (string) $entityId)
      ->condition('f.flag_id', self::SAVED_FLAGS, 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count > 0;
  }

  /**
   * Resolves the domain purpose for an entity based on bundle or domain fields.
   */
  protected function resolveEntityPurpose(EntityInterface $entity): ?string {
    $bundle = $entity->bundle();
    if (isset(self::BUNDLE_PURPOSE_MAP[$bundle])) {
      return self::BUNDLE_PURPOSE_MAP[$bundle];
    }

    $entityTypeId = $entity->getEntityTypeId();
    if ($entityTypeId === 'aculta_event') {
      return 'eventos';
    }

    return NULL;
  }

}
