<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Rebuild;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
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
    $bundleKey = $entityType->getKey('bundle');
    if ($idKey === NULL || $bundleKey === NULL) {
      throw new \LogicException(sprintf('Provider entity type %s must define both ID and bundle keys.', $entityTypeId));
    }

    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($bundleKey, $supported[$entityTypeId], 'IN')
      ->sort($idKey)
      ->range(0, $limit);
    if ($afterEntityId !== NULL) {
      $query->condition($idKey, $afterEntityId, '>');
    }
    $ids = array_values($query->execute());
    if ($ids === []) {
      return ['projections' => [], 'next_cursor' => NULL, 'exhausted' => TRUE];
    }

    $entities = $storage->loadMultiple($ids);
    $projections = [];
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
      }
    }

    return [
      'projections' => $projections,
      'next_cursor' => (string) end($ids),
      'exhausted' => count($ids) < $limit,
    ];
  }

}
