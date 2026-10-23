<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Query;

use Drupal\aculta_index\Cache\ContentIndexCandidateCache;
use Drupal\aculta_index\Generation\ContentIndexGenerationManager;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Selects a bounded candidate page and rechecks source Entity Access.
 */
final class ContentIndexQuery implements ContentIndexQueryInterface {

  /**
   * Maximum catalog candidates checked for one request. */
  private const MAX_CANDIDATES = 100;

  public function __construct(
    private readonly Connection $database,
    private readonly ContentIndexGenerationManager $generations,
    private readonly ContentIndexAccessFilter $accessFilter,
    private readonly ContentIndexCursorManager $cursors,
    private readonly ContentIndexProviderRegistry $providers,
    private readonly ContentIndexCandidateCache $candidateCache,
  ) {}

  /**
   * Queries current source entities with a fixed candidate scan bound.
   *
   * Supported filters are `entity_type`, `bundle`, `domain_id`, and
   * `published`.
   * Neither totals nor facets are calculated or returned.
   *
   * @param array<string, mixed> $filters
   *   Allowlisted catalog filters.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account for source Entity Access checks and cursor ownership.
   * @param string|null $cursor
   *   Opaque continuation token returned by a previous query.
   * @param int $limit
   *   Maximum candidates to scan, from 1 through 100.
   */
  public function query(array $filters, AccountInterface $account, ?string $cursor = NULL, int $limit = 25): ContentIndexQueryResult {
    if ($limit < 1 || $limit > self::MAX_CANDIDATES) {
      throw new \InvalidArgumentException(sprintf('Content Index candidate limit must be between 1 and %d.', self::MAX_CANDIDATES));
    }
    $filters = $this->normalizeFilters($filters);
    // Include sort semantics in the cursor binding so cursors issued by older
    // versions cannot be resumed against the immutable-ID keyset.
    $queryHash = hash('sha256', json_encode([
      'filters' => $filters,
      'sort' => 'entry_id_desc_v1',
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $generationId = $this->generations->getActiveGeneration();
    $cacheability = new CacheableMetadata();
    $cacheability->setCacheMaxAge(0);
    if ($generationId === NULL) {
      return new ContentIndexQueryResult([], NULL, $cacheability);
    }

    $uid = (int) $account->id();
    $after = $cursor === NULL ? NULL : $this->cursors->load($cursor, $uid, $generationId, $queryHash);
    $candidates = $this->loadConsistentCandidates($generationId, $filters, $after, $limit);
    $access = $this->accessFilter->filter($candidates, $account);
    $cacheability = $cacheability->merge($access->cacheability);

    $nextCursor = NULL;
    if (count($candidates) === $limit) {
      $last = end($candidates);
      if ($cursor === NULL) {
        $nextCursor = $this->cursors->create($uid, $generationId, $queryHash, 0, $last['entry_id']);
      }
      else {
        $this->cursors->advance($cursor, $uid, $generationId, $queryHash, 0, $last['entry_id'], $after['revision']);
        $nextCursor = $cursor;
      }
    }
    elseif ($cursor !== NULL) {
      $this->cursors->delete($cursor, $uid, $generationId, $queryHash);
    }

    return new ContentIndexQueryResult($access->entities, $nextCursor, $cacheability);
  }

  /**
   * Reads a cached or SQL candidate page from one stable generation revision.
   *
   * A concurrent writer advances the revision in the same transaction as its
   * projection change. One retry avoids returning a page cached or loaded
   * across that change without waiting indefinitely under continuous writes.
   * Entity Access is still revalidated by the caller after this method.
   *
   * @param string $generationId
   *   Active generation selected for the query.
   * @param array{entity_type: string|null, bundle: string|null, domain_id: string|null, published: bool|null} $filters
   *   Normalized allowlisted query filters.
   * @param array{changed: int, entry_id: int, revision: int}|null $after
   *   Private keyset cursor state.
   * @param int $limit
   *   Maximum candidate identities in the page.
   *
   * @return list<array{entity_type: string, entity_id: string, langcode: string, entry_id: int}>
   *   Candidate identities only.
   */
  private function loadConsistentCandidates(string $generationId, array $filters, ?array $after, int $limit): array {
    $queryKey = hash('sha256', json_encode([
      'filters' => $filters,
      'after_entry_id' => $after['entry_id'] ?? NULL,
      'limit' => $limit,
      'provider_fingerprint' => $this->providers->fingerprint(),
      'sort' => 'entry_id_desc_v1',
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $revision = $this->candidateCache->getGenerationRevision($generationId);

    for ($attempt = 0; $attempt < 2; $attempt++) {
      if ($revision === NULL) {
        return $this->loadCandidates($generationId, $filters, $after, $limit);
      }

      $candidates = $this->candidateCache->get($generationId, $revision, $queryKey);
      $confirmedRevision = $this->candidateCache->getGenerationRevision($generationId);
      if ($confirmedRevision !== $revision) {
        $revision = $confirmedRevision;
        continue;
      }
      if ($candidates !== NULL) {
        return $candidates;
      }

      $candidates = $this->loadCandidates($generationId, $filters, $after, $limit);
      $confirmedRevision = $this->candidateCache->getGenerationRevision($generationId);
      if ($confirmedRevision === $revision) {
        $this->candidateCache->set($generationId, $revision, $queryKey, $candidates);
        return $candidates;
      }
      $revision = $confirmedRevision;
      if ($attempt === 1) {
        return $this->loadCandidates($generationId, $filters, $after, $limit);
      }
    }

    return $this->loadCandidates($generationId, $filters, $after, $limit);
  }

  /**
   * Loads candidate identities in immutable entry ID order.
   *
   * @param string $generationId
   *   Generation selected by the active pointer.
   * @param array{entity_type: string|null, bundle: string|null, domain_id: string|null, published: bool|null} $filters
   *   Normalized allowlisted filters.
   * @param array{changed: int, entry_id: int, revision: int}|null $after
   *   Private keyset state, when continuing a query.
   * @param int $limit
   *   Maximum candidates in this page.
   *
   * @return list<array{entity_type: string, entity_id: string, langcode: string, entry_id: int}>
   *   Candidate identities; not yet authorized.
   */
  private function loadCandidates(string $generationId, array $filters, ?array $after, int $limit): array {
    if ($this->providers->all() === []) {
      return [];
    }
    $query = $this->database->select('aculta_index_entry', 'entry')
      ->fields('entry', ['entry_id', 'entity_type', 'entity_id', 'langcode'])
      ->condition('generation_id', $generationId);
    $compatibleProviders = $query->orConditionGroup();
    foreach ($this->providers->all() as $providerId => $provider) {
      $compatibleProviders->condition($query->andConditionGroup()
        ->condition('provider_id', $providerId)
        ->condition('provider_version', $provider->getProjectionVersion()));
    }
    $query->condition($compatibleProviders);
    if ($filters['entity_type'] !== NULL) {
      $query->condition('entity_type', $filters['entity_type']);
    }
    if ($filters['bundle'] !== NULL) {
      $query->condition('bundle', $filters['bundle']);
    }
    if ($filters['domain_id'] !== NULL) {
      $query->condition('domain_id', $filters['domain_id']);
    }
    if ($filters['published'] !== NULL) {
      $query->condition('published', (int) $filters['published']);
    }
    if ($after !== NULL) {
      $query->condition('entry_id', $after['entry_id'], '<');
    }
    $rows = $query
      ->orderBy('entry_id', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAllAssoc('entry_id');

    $candidates = [];
    foreach ($rows as $row) {
      $candidates[] = [
        'entity_type' => (string) $row->entity_type,
        'entity_id' => (string) $row->entity_id,
        'langcode' => (string) $row->langcode,
        'entry_id' => (int) $row->entry_id,
      ];
    }
    return $candidates;
  }

  /**
   * Validates and orders the allowlisted query filters.
   *
   * @param array<string, mixed> $filters
   *   User-supplied filters.
   *
   * @return array{entity_type: string|null, bundle: string|null, domain_id: string|null, published: bool|null}
   *   Stable normalized filter values.
   */
  private function normalizeFilters(array $filters): array {
    $allowed = ['entity_type', 'bundle', 'domain_id', 'published'];
    if (array_diff(array_keys($filters), $allowed) !== []) {
      throw new \InvalidArgumentException('Content Index query contains an unsupported filter.');
    }
    $normalized = [
      'entity_type' => $this->machineNameFilter($filters['entity_type'] ?? NULL, 'entity_type', 32),
      'bundle' => $this->machineNameFilter($filters['bundle'] ?? NULL, 'bundle'),
      'domain_id' => $this->stringFilter($filters['domain_id'] ?? NULL, 'domain_id', 128),
      'published' => NULL,
    ];
    if (array_key_exists('published', $filters) && $filters['published'] !== NULL) {
      if (!is_bool($filters['published'])) {
        throw new \InvalidArgumentException('Content Index published filter must be boolean.');
      }
      $normalized['published'] = $filters['published'];
    }
    return $normalized;
  }

  /**
   * Validates an optional Drupal machine name. */
  private function machineNameFilter(mixed $value, string $name, int $maxLength = 64): ?string {
    if ($value === NULL) {
      return NULL;
    }
    if (!is_string($value) || strlen($value) > $maxLength || preg_match('/^[a-z][a-z0-9_]*$/', $value) !== 1) {
      throw new \InvalidArgumentException(sprintf('Content Index %s filter is invalid.', $name));
    }
    return $value;
  }

  /**
   * Validates an optional bounded string filter. */
  private function stringFilter(mixed $value, string $name, int $maxLength): ?string {
    if ($value === NULL) {
      return NULL;
    }
    if (!is_string($value) || $value === '' || strlen($value) > $maxLength) {
      throw new \InvalidArgumentException(sprintf('Content Index %s filter is invalid.', $name));
    }
    return $value;
  }

}
