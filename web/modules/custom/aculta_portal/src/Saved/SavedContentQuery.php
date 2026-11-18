<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Saved;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Universal implementation of SavedContentQueryInterface backed by Flags.
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
   * Bundle to site key mapping for canonical domains.
   */
  protected const BUNDLE_SITE_KEY_MAP = [
    'aculta_forum_topic' => 'forum',
    'revista' => 'magazine',
    'aculta_event' => 'eventos',
    'lms_course' => 'courses',
    'wiki_article' => 'wiki',
  ];

  public function __construct(
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ?object $siteManager = NULL,
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
    else {
      // By default ('all' or NULL), include all bookmark and following flags.
      // This strictly excludes moderation and abuse flags from the start.
      $baseQuery->condition('f.flag_id', array_merge(self::SAVED_FLAGS, self::FOLLOW_FLAGS), 'IN');
    }

    if ($filter->getEntityTypeId() !== NULL) {
      $baseQuery->condition('f.entity_type', $filter->getEntityTypeId());
    }

    $baseQuery->orderBy('f.created', 'DESC');

    // Fetch candidate rows up to a safe window to perform access checks.
    $offset = $filter->getOffset();
    $limit = $filter->getLimit();

    $statement = $baseQuery->range(0, 500)->execute();
    if ($statement === NULL) {
      return new SavedContentQueryResult(
        [],
        0,
        FALSE,
        ['user', 'user.permissions'],
        ['flagging_list', 'user:' . $account->id()]
      );
    }

    $records = [];
    foreach ($statement->fetchAll() as $candidate) {
      if (!is_object($candidate)) {
        continue;
      }
      $flagId = $candidate->flag_id ?? NULL;
      $entityType = $candidate->entity_type ?? NULL;
      $entityId = $candidate->entity_id ?? NULL;
      $created = $candidate->created ?? NULL;
      if (!is_string($flagId)
        || !is_string($entityType)
        || $entityType === ''
        || (!is_int($entityId) && !is_string($entityId))
        || (!is_int($created) && (!is_string($created) || !ctype_digit($created)))) {
        continue;
      }
      $records[] = [
        'flag_id' => $flagId,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'created' => (int) $created,
      ];
    }

    if (empty($records)) {
      return new SavedContentQueryResult(
        [],
        0,
        FALSE,
        ['user', 'user.permissions'],
        ['flagging_list', 'user:' . $account->id()]
      );
    }

    // Group IDs by entity type for batch loading.
    $groupedIds = [];
    foreach ($records as $row) {
      $groupedIds[$row['entity_type']][] = $row['entity_id'];
    }

    /** @var array<string, array<int|string, EntityInterface>> $loadedEntities */
    $loadedEntities = [];
    foreach ($groupedIds as $entityType => $ids) {
      if ($this->entityTypeManager->hasDefinition($entityType)) {
        try {
          $storage = $this->entityTypeManager->getStorage($entityType);
          $loaded = [];
          foreach ($storage->loadMultiple($ids) as $id => $entity) {
            $loaded[$id] = $entity;
          }
          $loadedEntities[$entityType] = $loaded;
        }
        catch (\Throwable) {
          $loadedEntities[$entityType] = [];
        }
      }
    }

    $accessibleItems = [];
    $allCacheTags = ['flagging_list', 'user:' . $account->id()];
    $siteKeyFilter = $filter->getSiteKey();
    $bundleFilter = $filter->getBundle();

    foreach ($records as $row) {
      $type = $row['entity_type'];
      $id = $row['entity_id'];

      // 1. Safe handling of deleted entities (flagging row exists, entity
      // does not exist).
      if (!isset($loadedEntities[$type][$id])) {
        continue;
      }

      $entity = $loadedEntities[$type][$id];

      // 2. Strict Entity Access enforcement: unpublished or restricted
      // entities must never leak.
      if (!$entity->access('view', $account)) {
        continue;
      }

      // 3. Optional bundle filter.
      $bundle = $entity->bundle();
      if ($bundleFilter !== NULL && $bundle !== $bundleFilter) {
        continue;
      }

      // 4. Resolve site key and apply the optional site key filter.
      $siteKey = $this->resolveEntitySiteKey($entity);
      if ($siteKeyFilter !== NULL && $siteKey !== $siteKeyFilter) {
        continue;
      }

      $isFollow = in_array($row['flag_id'], self::FOLLOW_FLAGS, TRUE);
      $cat = $isFollow ? self::CATEGORY_FOLLOWING : self::CATEGORY_SAVED;

      $accessibleItems[] = new SavedContentItem(
        $type,
        $id,
        $entity,
        $row['flag_id'],
        $row['created'],
        $cat,
        $siteKey,
      );

      // Collect entity cache tags.
      $allCacheTags = array_merge($allCacheTags, $entity->getCacheTags());
    }

    $totalCount = count($accessibleItems);
    $pagedItems = array_slice($accessibleItems, $offset, $limit);
    $hasMore = ($offset + $limit) < $totalCount;
    $cacheTags = array_values(array_unique($allCacheTags));

    return new SavedContentQueryResult(
      $pagedItems,
      $totalCount,
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

    $saved = $this->executeCountQuery($this->database->select('flagging', 'f')
      ->condition('f.uid', (int) $account->id())
      ->condition('f.flag_id', self::SAVED_FLAGS, 'IN')
      ->countQuery());

    $following = $this->executeCountQuery($this->database->select('flagging', 'f')
      ->condition('f.uid', (int) $account->id())
      ->condition('f.flag_id', self::FOLLOW_FLAGS, 'IN')
      ->countQuery());

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

    $count = $this->executeCountQuery($this->database->select('flagging', 'f')
      ->condition('f.uid', (int) $account->id())
      ->condition('f.entity_type', $entityTypeId)
      ->condition('f.entity_id', (string) $entityId)
      ->condition('f.flag_id', self::SAVED_FLAGS, 'IN')
      ->countQuery());

    return $count > 0;
  }

  /**
   * Resolves the domain site key for an entity based on its bundle.
   */
  protected function resolveEntitySiteKey(EntityInterface $entity): ?string {
    $bundle = $entity->bundle();
    if (isset(self::BUNDLE_SITE_KEY_MAP[$bundle])) {
      return self::BUNDLE_SITE_KEY_MAP[$bundle];
    }

    $entityTypeId = $entity->getEntityTypeId();
    if ($entityTypeId === 'aculta_event') {
      return 'eventos';
    }

    return NULL;
  }

  /**
   * Executes a count query and safely narrows the database value to an integer.
   */
  private function executeCountQuery(SelectInterface $query): int {
    $statement = $query->execute();
    if ($statement === NULL) {
      return 0;
    }

    $count = $statement->fetchField();
    if (is_int($count)) {
      return max(0, $count);
    }
    if (is_string($count) && ctype_digit($count)) {
      return (int) $count;
    }

    return 0;
  }

}
