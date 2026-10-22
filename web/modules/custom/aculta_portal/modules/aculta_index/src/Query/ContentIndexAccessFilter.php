<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Query;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Revalidates entity view access for a bounded page of catalog candidates.
 */
final class ContentIndexAccessFilter {

  /**
   * Maximum candidates authorized in one call.
   */
  private const MAX_CANDIDATES = 100;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Loads and authorizes candidates in per-entity-type batches.
   *
   * @param list<array{entity_type: string, entity_id: string, langcode: string}> $candidates
   *   Candidate identities from a private catalog query. These are not
   *   authorized by their presence in the catalog.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account against which the original entity access is checked.
   *
   * @return \Drupal\aculta_index\Query\ContentIndexAccessResult
   *   Current source translations with allowed view access and cache metadata
   *   merged from all allowed and denied access results.
   */
  public function filter(array $candidates, AccountInterface $account): ContentIndexAccessResult {
    if (count($candidates) > self::MAX_CANDIDATES) {
      throw new \InvalidArgumentException(sprintf('At most %d Content Index candidates may be access-checked per call.', self::MAX_CANDIDATES));
    }

    $cacheability = new CacheableMetadata();
    $allowed = [];
    $chunks = array_chunk($candidates, self::MAX_CANDIDATES);
    foreach ($chunks as $chunk) {
      $byType = [];
      foreach ($chunk as $candidate) {
        if (!isset($candidate['entity_type'], $candidate['entity_id'], $candidate['langcode'])) {
          throw new \InvalidArgumentException('Every Content Index candidate must include entity_type, entity_id and langcode.');
        }
        $byType[$candidate['entity_type']][$candidate['entity_id']] = TRUE;
      }

      $allowedInChunk = [];
      foreach ($byType as $entityTypeId => $ids) {
        if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
          continue;
        }
        $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
        $storage = $this->entityTypeManager->getStorage($entityTypeId);
        $entities = $storage->loadMultiple(array_keys($ids));
        foreach ($chunk as $candidate) {
          if ($candidate['entity_type'] !== $entityTypeId) {
            continue;
          }
          $entity = $entities[$candidate['entity_id']] ?? NULL;
          if (!$entity instanceof ContentEntityInterface) {
            $listCacheability = new CacheableMetadata();
            $listCacheability->setCacheTags($entityType->getListCacheTags());
            $cacheability = $cacheability->merge($listCacheability);
            continue;
          }
          $cacheability = $cacheability->merge(CacheableMetadata::createFromObject($entity));
          if (!$entity->hasTranslation($candidate['langcode'])) {
            continue;
          }

          $translation = $entity->getTranslation($candidate['langcode']);
          $access = $translation->access('view', $account, TRUE);
          $cacheability = $cacheability->merge(CacheableMetadata::createFromObject($translation));
          $cacheability = $cacheability->merge(CacheableMetadata::createFromObject($access));
          if ($access->isAllowed()) {
            $allowedInChunk[$this->candidateKey($candidate)] = $translation;
          }
        }
      }

      foreach ($chunk as $candidate) {
        $key = $this->candidateKey($candidate);
        if (isset($allowedInChunk[$key])) {
          $allowed[] = $allowedInChunk[$key];
        }
      }
    }

    return new ContentIndexAccessResult($allowed, $cacheability);
  }

  /**
   * Returns an unambiguous key for an entity-language candidate.
   *
   * @param array{entity_type: string, entity_id: string, langcode: string} $candidate
   *   Candidate identity.
   */
  private function candidateKey(array $candidate): string {
    return hash('sha256', json_encode([
      $candidate['entity_type'],
      $candidate['entity_id'],
      $candidate['langcode'],
    ], JSON_THROW_ON_ERROR));
  }

}
