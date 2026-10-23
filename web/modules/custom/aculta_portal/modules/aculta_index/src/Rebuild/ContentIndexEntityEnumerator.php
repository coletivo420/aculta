<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Rebuild;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Enumerates provider-owned current entities by keyset for backfill workers.
 */
final class ContentIndexEntityEnumerator {

  /**
   * Maximum keyset page size to bound query and entity memory use.
   */
  private const MAX_BATCH_SIZE = 500;

  /**
   * Maximum distinct cached content entities retained across batches.
   */
  private const CACHE_RESET_ENTITY_LIMIT = 5_000;

  /**
   * Content entities to evict at the next bounded cache reset.
   *
   * @var array<string, array<string, bool>>
   */
  private array $pendingCacheResetIds = [];

  /**
   * Number of distinct pending cache identities.
   */
  private int $pendingCacheResetCount = 0;

  /**
   * Logger used for safe per-batch diagnostics.
   */
  private readonly LoggerInterface $logger;

  /**
   * Maximum distinct cache identities retained between bounded resets.
   */
  private readonly int $cacheResetEntityLimit;

  /**
   * Whether per-batch diagnostics are enabled for this process.
   */
  private readonly bool $diagnosticsEnabled;

  /**
   * Cached base-table ID query metadata, or FALSE when storage is unsupported.
   *
   * @var array<string, array{table: string, id_column: string, bundle_column: ?string}|false>
   */
  private array $baseTableIdQueryMetadata = [];

  public function __construct(
    private readonly ContentIndexProviderRegistry $providers,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    ?LoggerInterface $logger = NULL,
    int $cacheResetEntityLimit = self::CACHE_RESET_ENTITY_LIMIT,
    ?Settings $settings = NULL,
    private readonly ?Connection $database = NULL,
  ) {
    if ($cacheResetEntityLimit < 1 || $cacheResetEntityLimit > self::CACHE_RESET_ENTITY_LIMIT) {
      throw new \InvalidArgumentException(sprintf('Cache reset entity limit must be between 1 and %d.', self::CACHE_RESET_ENTITY_LIMIT));
    }
    $this->logger = $logger ?? new NullLogger();
    $this->cacheResetEntityLimit = $cacheResetEntityLimit;
    $this->diagnosticsEnabled = (bool) ($settings?->get('aculta_index_diagnostics', FALSE) ?? FALSE);
  }

  /**
   * Captures the greatest currently indexable source ID for an audit window.
   *
   * @return string|null
   *   Maximum entity ID, or NULL when the provider has no matching sources.
   */
  public function maximumEntityId(string $providerId, string $entityTypeId): ?string {
    $provider = $this->providers->get($providerId);
    $supported = $provider->getSupportedEntityTypes();
    if (!isset($supported[$entityTypeId])) {
      throw new \InvalidArgumentException(sprintf('Provider %s does not support entity type %s.', $providerId, $entityTypeId));
    }
    if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
      return NULL;
    }

    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    $idKey = $entityType->getKey('id');
    $bundleKey = $entityType->getKey('bundle') ?: NULL;
    if ($idKey === NULL) {
      throw new \LogicException(sprintf('Provider entity type %s must define an ID key.', $entityTypeId));
    }
    $supportedBundles = array_values($supported[$entityTypeId]);
    if ($bundleKey === NULL && $supportedBundles !== [$entityTypeId]) {
      throw new \LogicException(sprintf(
        'Provider entity type %s without a bundle key must declare only its own ID as the fixed bundle.',
        $entityTypeId,
      ));
    }

    $baseTable = $this->getBaseTableIdQueryMetadata($entityTypeId, $idKey, $bundleKey);
    if ($baseTable !== NULL) {
      $query = $this->database->select($baseTable['table'], 'source');
      $query->addExpression('MAX(source.' . $baseTable['id_column'] . ')', 'maximum_id');
      if ($baseTable['bundle_column'] !== NULL) {
        $query->condition('source.' . $baseTable['bundle_column'], $supportedBundles, 'IN');
      }
      $maximumId = $query->execute()->fetchField();
      return $maximumId === FALSE || $maximumId === NULL ? NULL : (string) $maximumId;
    }
    $query = $this->entityTypeManager->getStorage($entityTypeId)->getQuery()->accessCheck(FALSE);
    if ($bundleKey !== NULL) {
      $query->condition($bundleKey, $supportedBundles, 'IN');
    }
    $ids = array_values($query->sort($idKey, 'DESC')->range(0, 1)->execute());
    return $ids === [] ? NULL : (string) reset($ids);
  }

  /**
   * Loads one bounded keyset page and projects each entity translation.
   *
   * Entity query access checks are disabled because this is a privileged
   * background discovery operation. Returned projections remain internal to
   * the catalog writer; consumer queries must authorize source entities.
   *
   * @param string $providerId
   *   Registered provider that owns the source type.
   * @param string $entityTypeId
   *   Source entity type to scan.
   * @param string|null $afterEntityId
   *   Exclusive keyset cursor from the previous batch.
   * @param int $limit
   *   Maximum number of source entities to load.
   * @param string|null $maximumEntityId
   *   Optional inclusive keyset ceiling for finite reconciliation windows.
   *   NULL leaves rebuild discovery unbounded.
   *
   * @return array
   *   An array with `projections` (a list of projection DTOs), `next_cursor`
   *   (the final entity ID or NULL) and `exhausted` (whether this was the last
   *   page).
   */
  public function nextBatch(string $providerId, string $entityTypeId, ?string $afterEntityId = NULL, int $limit = 100, ?string $maximumEntityId = NULL): array {
    if ($limit < 1 || $limit > self::MAX_BATCH_SIZE) {
      throw new \InvalidArgumentException(sprintf('Content Index batch size must be between 1 and %d.', self::MAX_BATCH_SIZE));
    }

    $provider = $this->providers->get($providerId);
    $supported = $provider->getSupportedEntityTypes();
    if (!isset($supported[$entityTypeId])) {
      throw new \InvalidArgumentException(sprintf('Provider %s does not support entity type %s.', $providerId, $entityTypeId));
    }
    if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
      return ['projections' => [], 'next_cursor' => NULL, 'exhausted' => TRUE];
    }

    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    $idKey = $entityType->getKey('id');
    // EntityType::getKey() represents an absent key as an empty string in
    // current Core definitions and NULL in some test/custom definitions.
    $bundleKey = $entityType->getKey('bundle') ?: NULL;
    if ($idKey === NULL) {
      throw new \LogicException(sprintf('Provider entity type %s must define an ID key.', $entityTypeId));
    }
    $supportedBundles = array_values($supported[$entityTypeId]);
    if ($bundleKey === NULL && $supportedBundles !== [$entityTypeId]) {
      throw new \LogicException(sprintf(
        'Provider entity type %s without a bundle key must declare only its own ID as the fixed bundle.',
        $entityTypeId,
      ));
    }

    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $batchStartedAt = hrtime(TRUE);
    $queryStartedAt = hrtime(TRUE);
    $baseTable = $this->getBaseTableIdQueryMetadata($entityTypeId, $idKey, $bundleKey);
    if ($baseTable !== NULL) {
      $query = $this->database->select($baseTable['table'], 'source')
        ->fields('source', [$baseTable['id_column']]);
      if ($baseTable['bundle_column'] !== NULL) {
        $query->condition('source.' . $baseTable['bundle_column'], $supportedBundles, 'IN');
      }
      if ($afterEntityId !== NULL) {
        $query->condition('source.' . $baseTable['id_column'], $afterEntityId, '>');
      }
      if ($maximumEntityId !== NULL) {
        $query->condition('source.' . $baseTable['id_column'], $maximumEntityId, '<=');
      }
      $ids = $query->orderBy('source.' . $baseTable['id_column'], 'ASC')
        ->range(0, $limit)
        ->execute()
        ->fetchCol();
      $queryStrategy = 'base_table';
    }
    else {
      $query = $storage->getQuery()->accessCheck(FALSE);
      if ($bundleKey !== NULL) {
        $query->condition($bundleKey, $supportedBundles, 'IN');
      }
      $query->sort($idKey)->range(0, $limit);
      if ($afterEntityId !== NULL) {
        $query->condition($idKey, $afterEntityId, '>');
      }
      if ($maximumEntityId !== NULL) {
        $query->condition($idKey, $maximumEntityId, '<=');
      }
      $ids = array_values($query->execute());
      $queryStrategy = 'entity_query';
    }
    $queryMilliseconds = (hrtime(TRUE) - $queryStartedAt) / 1_000_000;
    if ($ids === []) {
      $cacheStartedAt = hrtime(TRUE);
      $this->flushCacheResetQueue();
      $cacheMilliseconds = (hrtime(TRUE) - $cacheStartedAt) / 1_000_000;
      $this->logBatchDiagnostics(
        $providerId,
        $entityTypeId,
        $limit,
        0,
        0,
        $queryMilliseconds,
        $queryStrategy,
        0,
        0,
        $cacheMilliseconds,
        $batchStartedAt,
      );
      return ['projections' => [], 'next_cursor' => NULL, 'exhausted' => TRUE];
    }

    $projections = [];
    $cacheIds = [$entityTypeId => array_fill_keys(array_map('strval', $ids), TRUE)];
    $projectionSucceeded = FALSE;
    $exhausted = count($ids) < $limit;
    $loadMilliseconds = 0.0;
    $projectionMilliseconds = 0.0;
    $cacheMilliseconds = 0.0;
    try {
      $loadStartedAt = hrtime(TRUE);
      $entities = $storage->loadMultiple($ids);
      $loadMilliseconds = (hrtime(TRUE) - $loadStartedAt) / 1_000_000;
      $projectionStartedAt = hrtime(TRUE);
      foreach ($ids as $id) {
        $entity = $entities[$id] ?? NULL;
        if (!$entity instanceof ContentEntityInterface) {
          continue;
        }
        foreach ($entity->getTranslationLanguages() as $langcode => $language) {
          if (!$entity->hasTranslation($langcode)) {
            continue;
          }
          $translation = $entity->getTranslation($langcode);
          $projection = $provider->project($translation);
          if ($projection === NULL) {
            continue;
          }
          if ($projection->entityTypeId !== $entityTypeId
            || $projection->entityId !== (string) $entity->id()
            || $projection->langcode !== $langcode
            || $projection->bundle !== $entity->bundle()) {
            throw new \UnexpectedValueException(sprintf('Provider %s returned a projection that does not match its source entity translation.', $providerId));
          }
          $projections[] = $projection;
          foreach ($projection->relations as $relation) {
            if ($relation instanceof ContentIndexRelation) {
              $cacheIds[$relation->targetEntityTypeId][$relation->targetEntityId] = TRUE;
            }
          }
        }
      }
      $projectionMilliseconds = (hrtime(TRUE) - $projectionStartedAt) / 1_000_000;
      $projectionSucceeded = TRUE;
    }
    finally {
      $cacheStartedAt = hrtime(TRUE);
      // Bound Drupal's static entity caches across keyset batches. Reset only
      // source IDs and relation targets carried by successful projections;
      // this avoids invalidating unrelated cache entries. Cache invalidation
      // itself is costly, so accumulate at most a fixed number of identities
      // before flushing, and flush at provider exhaustion. On projection
      // failure target identities may be incomplete, so clear all content
      // entity storages before propagating the error.
      if (!$projectionSucceeded) {
        $this->pendingCacheResetIds = [];
        $this->pendingCacheResetCount = 0;
        $this->resetAllContentEntityStorageCaches();
      }
      else {
        $this->queueCacheResetIds($cacheIds);
        if ($this->pendingCacheResetCount >= $this->cacheResetEntityLimit || $exhausted) {
          $this->flushCacheResetQueue();
        }
      }
      $cacheMilliseconds = (hrtime(TRUE) - $cacheStartedAt) / 1_000_000;
    }

    $this->logBatchDiagnostics(
      $providerId,
      $entityTypeId,
      $limit,
      count($ids),
      count($projections),
      $queryMilliseconds,
      $queryStrategy,
      $loadMilliseconds,
      $projectionMilliseconds,
      $cacheMilliseconds,
      $batchStartedAt,
    );

    return [
      'projections' => $projections,
      'next_cursor' => (string) end($ids),
      'exhausted' => count($ids) < $limit,
    ];
  }

  /**
   * Logs per-batch timings without source identities or projected metadata.
   */
  private function logBatchDiagnostics(
    string $providerId,
    string $entityTypeId,
    int $limit,
    int $sourceCount,
    int $projectionCount,
    float $queryMilliseconds,
    string $queryStrategy,
    float $loadMilliseconds,
    float $projectionMilliseconds,
    float $cacheMilliseconds,
    int $batchStartedAt,
  ): void {
    if (!$this->diagnosticsEnabled) {
      return;
    }
    $this->logger->debug('Content Index enumeration batch completed. Provider: @provider; entity type: @entity_type; limit: @limit; sources: @source_count; projections: @projection_count; ID discovery: @query_strategy in @query_ms ms; load multiple: @load_ms ms; project: @project_ms ms; cache reset: @cache_ms ms; total: @total_ms ms.', [
      '@provider' => $providerId,
      '@entity_type' => $entityTypeId,
      '@limit' => $limit,
      '@source_count' => $sourceCount,
      '@projection_count' => $projectionCount,
      '@query_strategy' => $queryStrategy,
      '@query_ms' => round($queryMilliseconds, 3),
      '@load_ms' => round($loadMilliseconds, 3),
      '@project_ms' => round($projectionMilliseconds, 3),
      '@cache_ms' => round($cacheMilliseconds, 3),
      '@total_ms' => round((hrtime(TRUE) - $batchStartedAt) / 1_000_000, 3),
    ]);
  }

  /**
   * Gets safe base-table columns for keyset ID discovery when available.
   *
   * EntityQuery can join revision and data tables and group every revision for
   * revisionable content. That makes repeated keyset pages expensive on large
   * entity tables. A direct candidate-ID query is safe only when the storage
   * mapping confirms that ID and bundle columns live on the base table. Entity
   * API remains responsible for loading and projecting candidates.
   *
   * @param string $entityTypeId
   *   Entity type being enumerated.
   * @param string $idKey
   *   Entity type ID key.
   * @param string|null $bundleKey
   *   Entity type bundle key, if any.
   *
   * @return array{table: string, id_column: string, bundle_column: ?string}|null
   *   Verified base table query metadata, or NULL to use EntityQuery.
   */
  private function getBaseTableIdQueryMetadata(string $entityTypeId, string $idKey, ?string $bundleKey): ?array {
    if (array_key_exists($entityTypeId, $this->baseTableIdQueryMetadata)) {
      return $this->baseTableIdQueryMetadata[$entityTypeId] ?: NULL;
    }
    if ($this->database === NULL) {
      $this->baseTableIdQueryMetadata[$entityTypeId] = FALSE;
      return NULL;
    }
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    $baseTable = $entityType->getBaseTable();
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    if (!is_string($baseTable) || $baseTable === '' || !method_exists($storage, 'getTableMapping')) {
      $this->baseTableIdQueryMetadata[$entityTypeId] = FALSE;
      return NULL;
    }
    $mapping = $storage->getTableMapping();
    if (!method_exists($mapping, 'getAllColumns') || !method_exists($mapping, 'getColumnNames')
      || !$this->database->schema()->tableExists($baseTable)) {
      $this->baseTableIdQueryMetadata[$entityTypeId] = FALSE;
      return NULL;
    }
    $columns = $mapping->getAllColumns($baseTable);
    $idColumns = $mapping->getColumnNames($idKey);
    $idColumn = reset($idColumns);
    $bundleColumn = NULL;
    if ($bundleKey !== NULL) {
      $bundleColumns = $mapping->getColumnNames($bundleKey);
      $bundleColumn = reset($bundleColumns);
    }
    if (!is_string($idColumn) || !in_array($idColumn, $columns, TRUE)
      || ($bundleKey !== NULL && (!is_string($bundleColumn) || !in_array($bundleColumn, $columns, TRUE)))) {
      $this->baseTableIdQueryMetadata[$entityTypeId] = FALSE;
      return NULL;
    }
    $this->baseTableIdQueryMetadata[$entityTypeId] = [
      'table' => $baseTable,
      'id_column' => $idColumn,
      'bundle_column' => is_string($bundleColumn) ? $bundleColumn : NULL,
    ];
    return $this->baseTableIdQueryMetadata[$entityTypeId];
  }

  /**
   * Queues source and relation target identities for a bounded cache reset.
   *
   * @param array<string, array<string, bool>> $entityIds
   *   Entity IDs read or projected by this batch, keyed by entity type.
   */
  private function queueCacheResetIds(array $entityIds): void {
    foreach ($entityIds as $entityTypeId => $ids) {
      foreach (array_keys($ids) as $id) {
        if (!isset($this->pendingCacheResetIds[$entityTypeId][$id])) {
          $this->pendingCacheResetIds[$entityTypeId][$id] = TRUE;
          $this->pendingCacheResetCount++;
        }
      }
    }
  }

  /**
   * Evicts pending content entities from storage static caches.
   */
  private function flushCacheResetQueue(): void {
    foreach ($this->pendingCacheResetIds as $entityTypeId => $ids) {
      if (!$this->entityTypeManager->hasDefinition($entityTypeId)
        || !$this->entityTypeManager->getDefinition($entityTypeId)->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }
      $this->entityTypeManager->getStorage($entityTypeId)->resetCache(array_keys($ids));
    }
    $this->pendingCacheResetIds = [];
    $this->pendingCacheResetCount = 0;
  }

  /**
   * Clears every content entity storage after a provider projection failure.
   */
  private function resetAllContentEntityStorageCaches(): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if ($entityType->entityClassImplements(ContentEntityInterface::class)) {
        $this->entityTypeManager->getStorage($entityTypeId)->resetCache();
      }
    }
  }

}
