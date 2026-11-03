<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cleanup;

use Drupal\aculta_file_uploader\Cleanup\FileCleanupAssessorInterface;
use Drupal\aculta_file_uploader\Cleanup\FileCleanupStatus;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Builds a read-only preview for explicitly selected File and Media IDs.
 *
 * This service does not discover or delete source content. The owning module
 * supplies explicit asset candidates; each candidate is rechecked through the
 * shared Portal assessment APIs. Reports contain only entity type, ID, and
 * cleanup status, never filenames, labels, URIs, or content.
 */
final class ContentCleanupPreview {

  /**
   * Maximum number of asset candidates in one preview request.
   */
  private const MAX_CANDIDATES = 100;

  /**
   * Entity types owned by the shared asset cleanup contracts.
   *
   * @var list<string>
   */
  private const ASSET_ENTITY_TYPES = ['file', 'media'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileCleanupAssessorInterface $fileAssessor,
    private readonly MediaCleanupAssessorInterface $mediaAssessor,
  ) {}

  /**
   * Assesses explicitly supplied assets without performing any mutation.
   *
   * @param array<int, int|string> $fileIds
   *   File IDs discovered by an owning module or operator.
   * @param array<int, int|string> $mediaIds
   *   Media IDs discovered by an owning module or operator.
   *
   * @return array<string, mixed>
   *   A privacy-minimized report with `dry_run`, `executed`, `items`, and
   *   per-type `summary` keys. Each item contains only type, ID, status, and a
   *   candidate boolean. Candidates require a separately authorized operation
   *   and immediate revalidation before deletion.
   *
   * @throws \InvalidArgumentException
   *   If the supplied IDs are malformed or exceed the bounded batch size.
   */
  public function preview(array $fileIds, array $mediaIds): array {
    $fileIds = $this->normalizeIds($fileIds);
    $mediaIds = $this->normalizeIds($mediaIds);
    if (count($fileIds) + count($mediaIds) > self::MAX_CANDIDATES) {
      throw new \InvalidArgumentException(sprintf('A cleanup preview accepts at most %d assets.', self::MAX_CANDIDATES));
    }

    $files = $fileIds === [] ? [] : $this->entityTypeManager->getStorage('file')->loadMultiple($fileIds);
    $media = $mediaIds === [] ? [] : $this->entityTypeManager->getStorage('media')->loadMultiple($mediaIds);
    $items = [];
    $summary = [
      'file' => ['requested' => count($fileIds), 'candidate' => 0, 'retained' => 0],
      'media' => ['requested' => count($mediaIds), 'candidate' => 0, 'retained' => 0],
    ];

    foreach ($fileIds as $id) {
      $entity = $files[$id] ?? NULL;
      $status = $entity instanceof FileInterface
        ? $this->fileAssessor->assess($entity)->value
        : 'not_found';
      $candidate = $status === FileCleanupStatus::Available->value;
      $items[] = ['type' => 'file', 'id' => $id, 'status' => $status, 'candidate' => $candidate];
      $summary['file'][$candidate ? 'candidate' : 'retained']++;
    }

    foreach ($mediaIds as $id) {
      $entity = $media[$id] ?? NULL;
      $status = $entity instanceof MediaInterface
        ? $this->mediaAssessor->assess($entity)->value
        : 'not_found';
      $candidate = $status === FileCleanupStatus::Available->value;
      $items[] = ['type' => 'media', 'id' => $id, 'status' => $status, 'candidate' => $candidate];
      $summary['media'][$candidate ? 'candidate' : 'retained']++;
    }

    return [
      'dry_run' => TRUE,
      'executed' => FALSE,
      'items' => $items,
      'summary' => $summary,
    ];
  }

  /**
   * Inventories one bounded page of File or Media cleanup candidates.
   *
   * Entity access is intentionally bypassed for this internal maintenance
   * inventory so that references across private content are still assessed.
   * Any future UI or command must enforce administrative access before calling
   * this service. Only IDs and assessment results are returned.
   *
   * @param string $entityType
   *   Either `file` or `media`.
   * @param int|string|null $afterId
   *   Positive entity ID from the preceding page, or NULL to start.
   * @param int $limit
   *   Maximum number of entities to assess, from 1 to 100.
   *
   * @return array<string, mixed>
   *   A read-only report with the standard preview keys plus `has_more` and
   *   `next_after_id` for keyset pagination.
   *
   * @throws \InvalidArgumentException
   *   If the entity type, cursor, or page size is invalid.
   */
  public function inventory(string $entityType, int|string|null $afterId = NULL, int $limit = 50): array {
    if (!in_array($entityType, self::ASSET_ENTITY_TYPES, TRUE)) {
      throw new \InvalidArgumentException('Only File and Media cleanup inventory is supported.');
    }
    if ($limit < 1 || $limit > self::MAX_CANDIDATES) {
      throw new \InvalidArgumentException(sprintf('Inventory page size must be from 1 to %d.', self::MAX_CANDIDATES));
    }
    $cursor = $afterId === NULL ? NULL : $this->normalizeCursor($afterId);

    $definition = $this->entityTypeManager->getDefinition($entityType);
    $idKey = $definition->getKey('id');
    if ($idKey === NULL || $idKey === '') {
      throw new \LogicException(sprintf('Entity type %s has no ID key.', $entityType));
    }

    $query = $this->entityTypeManager->getStorage($entityType)->getQuery();
    $query->accessCheck(FALSE);
    if ($cursor !== NULL) {
      $query->condition($idKey, $cursor, '>');
    }
    $ids = array_values($query
      ->sort($idKey)
      ->range(0, $limit + 1)
      ->execute());
    $hasMore = count($ids) > $limit;
    $ids = array_slice($ids, 0, $limit);
    $ids = $this->normalizeIds($ids);

    $report = $entityType === 'file'
      ? $this->preview($ids, [])
      : $this->preview([], $ids);
    $report['has_more'] = $hasMore;
    $report['next_after_id'] = $hasMore && $ids !== [] ? end($ids) : NULL;
    return $report;
  }

  /**
   * Validates, canonicalizes, deduplicates, and sorts entity IDs.
   *
   * @param array<int, int|string> $ids
   *   Numeric entity IDs.
   *
   * @return list<string>
   *   Canonical positive integer IDs.
   */
  private function normalizeIds(array $ids): array {
    if (!array_is_list($ids)) {
      throw new \InvalidArgumentException('Cleanup preview IDs must be provided as a list.');
    }
    $normalized = [];
    foreach ($ids as $id) {
      if (!is_int($id) && !is_string($id)) {
        throw new \InvalidArgumentException('Cleanup preview IDs must be positive integers.');
      }
      $validated = filter_var((string) $id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      if ($validated === FALSE) {
        throw new \InvalidArgumentException('Cleanup preview IDs must be positive integers.');
      }
      $normalized[(string) $validated] = (string) $validated;
    }
    $result = array_values($normalized);
    sort($result, SORT_NUMERIC);
    return $result;
  }

  /**
   * Validates a keyset pagination cursor.
   */
  private function normalizeCursor(int|string $cursor): string {
    $validated = filter_var((string) $cursor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validated === FALSE) {
      throw new \InvalidArgumentException('Inventory cursor must be a positive integer.');
    }
    return (string) $validated;
  }

}
