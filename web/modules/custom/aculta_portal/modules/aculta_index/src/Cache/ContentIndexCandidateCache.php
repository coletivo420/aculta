<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Cache;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;

/**
 * Caches only bounded Content Index candidate identity pages.
 *
 * Source entities and access decisions are never cached here. Every consumer
 * request must reload entities and revalidate Entity Access.
 */
final class ContentIndexCandidateCache {

  /**
   * Candidate pages stay short lived if an invalidation is missed.
   */
  private const MAX_AGE = 60;

  public function __construct(
    private readonly Connection $database,
    private readonly CacheBackendInterface $cache,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Returns the current candidate revision for a generation.
   */
  public function getGenerationRevision(string $generationId): ?int {
    $revision = $this->database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['candidate_revision'])
      ->condition('generation_id', $generationId)
      ->execute()
      ->fetchField();
    return $revision === FALSE ? NULL : (int) $revision;
  }

  /**
   * Loads a candidate page, or NULL when the cache has no matching page.
   *
   * @return list<array{entity_type: string, entity_id: string, langcode: string, entry_id: int}>|null
   *   Candidate identities only.
   */
  public function get(string $generationId, int $revision, string $queryKey): ?array {
    $item = $this->cache->get($this->cacheId($generationId, $revision, $queryKey));
    return $item !== FALSE && is_array($item->data) ? $item->data : NULL;
  }

  /**
   * Stores one bounded candidate page, tagged by its generation.
   *
   * @param string $generationId
   *   Active or building generation that produced the candidate page.
   * @param int $revision
   *   Candidate revision captured before loading the page.
   * @param string $queryKey
   *   Hash of the normalized query state.
   * @param list<array{entity_type: string, entity_id: string, langcode: string, entry_id: int}> $candidates
   *   Candidate identities only.
   */
  public function set(string $generationId, int $revision, string $queryKey, array $candidates): void {
    $this->cache->set(
      $this->cacheId($generationId, $revision, $queryKey),
      $candidates,
      time() + self::MAX_AGE,
      [$this->generationTag($generationId)],
    );
  }

  /**
   * Invalidates candidate pages produced from one generation.
   */
  public function invalidateGeneration(string $generationId): void {
    $this->cacheTagsInvalidator->invalidateTags([$this->generationTag($generationId)]);
  }

  /**
   * Builds a cache ID that does not expose query parameters.
   */
  private function cacheId(string $generationId, int $revision, string $queryKey): string {
    return 'aculta_index:candidates:' . hash('sha256', $generationId . ':' . $revision . ':' . $queryKey);
  }

  /**
   * Builds the cache tag shared by pages from one generation.
   */
  private function generationTag(string $generationId): string {
    return 'aculta_index_generation:' . $generationId;
  }

}
