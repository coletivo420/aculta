<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Rebuild;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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

  public function __construct(
    private readonly ContentIndexProviderRegistry $providers,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Loads one bounded keyset page and projects each entity translation.
   *
   * Entity query access checks are disabled because this is a privileged
   * background discovery operation. Returned projections remain internal to
   * the catalog writer; consumer queries must authorize source entities.
   *
   * @return array
   *   An array with `projections` (a list of projection DTOs), `next_cursor`
   *   (the final entity ID or NULL) and `exhausted` (whether this was the last
   *   page).
   */
  public function nextBatch(string $providerId, string $entityTypeId, ?string $afterEntityId = NULL, int $limit = 100): array {
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
    $query = $storage->getQuery()->accessCheck(FALSE);
    if ($bundleKey !== NULL) {
      $query->condition($bundleKey, $supportedBundles, 'IN');
    }
    $query->sort($idKey)->range(0, $limit);
    if ($afterEntityId !== NULL) {
      $query->condition($idKey, $afterEntityId, '>');
    }
    $ids = array_values($query->execute());
    if ($ids === []) {
      $this->flushCacheResetQueue();
      return ['projections' => [], 'next_cursor' => NULL, 'exhausted' => TRUE];
    }

    $projections = [];
    $cacheIds = [$entityTypeId => array_fill_keys(array_map('strval', $ids), TRUE)];
    $projectionSucceeded = FALSE;
    $exhausted = count($ids) < $limit;
    try {
      $entities = $storage->loadMultiple($ids);
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
      $projectionSucceeded = TRUE;
    }
    finally {
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
        if ($this->pendingCacheResetCount >= self::CACHE_RESET_ENTITY_LIMIT || $exhausted) {
          $this->flushCacheResetQueue();
        }
      }
    }

    return [
      'projections' => $projections,
      'next_cursor' => (string) end($ids),
      'exhausted' => count($ids) < $limit,
    ];
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
