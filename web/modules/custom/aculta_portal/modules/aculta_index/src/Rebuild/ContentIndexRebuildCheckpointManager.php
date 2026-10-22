<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Rebuild;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Loads and advances one generation's keyset checkpoint with compare-and-swap.
 */
final class ContentIndexRebuildCheckpointManager {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Loads the current checkpoint for a generation that is still building.
   *
   * @return array
   *   The last committed cursor fields (`provider`, `entity_type`, `entity_id`)
   *   and its compare-and-swap `revision`.
   */
  public function load(string $generationId): array {
    $checkpoint = $this->database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['cursor_provider', 'cursor_entity_type', 'cursor_entity_id', 'cursor_revision'])
      ->condition('generation_id', $generationId)
      ->condition('status', 'building')
      ->execute()
      ->fetchAssoc();
    if (!$checkpoint) {
      throw new \InvalidArgumentException(sprintf('Generation %s is missing or is not building.', $generationId));
    }

    return [
      'provider' => (string) $checkpoint['cursor_provider'],
      'entity_type' => (string) $checkpoint['cursor_entity_type'],
      'entity_id' => (string) $checkpoint['cursor_entity_id'],
      'revision' => (int) $checkpoint['cursor_revision'],
    ];
  }

  /**
   * Commits a processed entity cursor if no competing worker advanced it.
   */
  public function advance(
    string $generationId,
    string $providerId,
    string $entityTypeId,
    string $entityId,
    int $expectedRevision,
  ): int {
    if ($expectedRevision < 0) {
      throw new \InvalidArgumentException('Checkpoint revision must be non-negative.');
    }
    $updated = $this->database->update('aculta_index_generation')
      ->fields([
        'cursor_provider' => $providerId,
        'cursor_entity_type' => $entityTypeId,
        'cursor_entity_id' => $entityId,
        'cursor_langcode' => '',
        'cursor_revision' => $expectedRevision + 1,
        'updated' => $this->time->getRequestTime(),
      ])
      ->condition('generation_id', $generationId)
      ->condition('status', 'building')
      ->condition('cursor_revision', $expectedRevision)
      ->execute();

    if ($updated !== 1) {
      throw new \RuntimeException(sprintf('Checkpoint for generation %s changed or is no longer writable.', $generationId));
    }

    return $expectedRevision + 1;
  }

}
