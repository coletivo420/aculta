<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Integrity;

use Drupal\Core\Database\Connection;

/**
 * Performs bounded, metadata-only consistency checks on the catalog tables.
 */
final class ContentIndexIntegrityChecker {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Checks the active pointer and catalog references without loading content.
   *
   * @return array
   *   Consistency state and counts only: validity, active generation, active
   *   generation count, orphan entry/relation counts and missing relation
   *   source count. No projected content is returned.
   */
  public function check(): array {
    $state = $this->database->select('aculta_index_state', 'state')
      ->fields('state', ['active_generation'])
      ->condition('state_id', 1)
      ->execute()
      ->fetchField();
    $activeGeneration = $state === FALSE || $state === '' ? NULL : (string) $state;

    $activeGenerationCount = (int) $this->database->select('aculta_index_generation', 'generation')
      ->condition('status', 'active')
      ->countQuery()
      ->execute()
      ->fetchField();

    $activePointerValid = $state !== FALSE;
    if ($activeGeneration === NULL) {
      $activePointerValid = $activePointerValid && $activeGenerationCount === 0;
    }
    else {
      $activePointerStatus = $this->database->select('aculta_index_generation', 'generation')
        ->fields('generation', ['status'])
        ->condition('generation_id', $activeGeneration)
        ->execute()
        ->fetchField();
      $activePointerValid = $activePointerStatus === 'active' && $activeGenerationCount === 1;
    }

    $orphanEntries = $this->database->select('aculta_index_entry', 'entry');
    $orphanEntries->leftJoin('aculta_index_generation', 'generation', 'entry.generation_id = generation.generation_id');
    $orphanEntryCount = (int) $orphanEntries
      ->condition('generation.generation_id', NULL, 'IS NULL')
      ->countQuery()
      ->execute()
      ->fetchField();

    $orphanRelations = $this->database->select('aculta_index_relation', 'relation');
    $orphanRelations->leftJoin('aculta_index_generation', 'generation', 'relation.generation_id = generation.generation_id');
    $orphanRelationCount = (int) $orphanRelations
      ->condition('generation.generation_id', NULL, 'IS NULL')
      ->countQuery()
      ->execute()
      ->fetchField();

    $relationSources = $this->database->select('aculta_index_relation', 'relation');
    $relationSources->leftJoin(
        'aculta_index_entry',
        'entry',
        'relation.generation_id = entry.generation_id'
        . ' AND relation.provider_id = entry.provider_id'
        . ' AND relation.source_entity_type = entry.entity_type'
        . ' AND relation.source_entity_id = entry.entity_id'
        . ' AND relation.source_langcode = entry.langcode',
      );
    $relationSourceMissingCount = (int) $relationSources
      ->condition('entry.entry_id', NULL, 'IS NULL')
      ->countQuery()
      ->execute()
      ->fetchField();

    $valid = $activePointerValid
      && $orphanEntryCount === 0
      && $orphanRelationCount === 0
      && $relationSourceMissingCount === 0;

    return [
      'valid' => $valid,
      'active_generation' => $activeGeneration,
      'active_generation_count' => $activeGenerationCount,
      'orphan_entry_count' => $orphanEntryCount,
      'orphan_relation_count' => $orphanRelationCount,
      'relation_source_missing_count' => $relationSourceMissingCount,
    ];
  }

}
