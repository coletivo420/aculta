<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Integrity;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;

/**
 * Checks catalog structure and runs resumable, bounded integrity audits.
 */
final class ContentIndexIntegrityChecker {

  /**
   * Maximum rows inspected by one incremental audit batch.
   */
  private const MAX_BATCH_SIZE = 500;

  /**
   * Minimum delay between completed scheduled audit cycles.
   */
  private const SCHEDULE_INTERVAL = 86400;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly ContentIndexProviderRegistry $providers,
    private readonly ContentIndexWorkManager $work,
  ) {}

  /**
   * Checks aggregate catalog consistency for generation validation.
   *
   * @param string|null $generationId
   *   Generation whose provider projection versions should be checked; defaults
   *   to the active generation for operational health checks.
   *
   * @return array
   *   Consistency state and counts only. No projected content is returned.
   */
  public function check(?string $generationId = NULL): array {
    $pointer = $this->activePointer();
    $checkedGeneration = $generationId ?? $pointer['generation'];
    $orphanEntryCount = $this->countOrphanEntries();
    $orphanRelationCount = $this->countOrphanRelations();
    $relationSourceMissingCount = $this->countMissingRelationSources();
    $staleProjectionCount = $this->countStaleProjections($checkedGeneration);
    $valid = $pointer['valid']
      && $orphanEntryCount === 0
      && $orphanRelationCount === 0
      && $relationSourceMissingCount === 0
      && $staleProjectionCount === 0;

    return [
      'valid' => $valid,
      'active_generation' => $pointer['generation'],
      'checked_generation' => $checkedGeneration,
      'active_generation_count' => $pointer['active_count'],
      'orphan_entry_count' => $orphanEntryCount,
      'orphan_relation_count' => $orphanRelationCount,
      'relation_source_missing_count' => $relationSourceMissingCount,
      'stale_projection_count' => $staleProjectionCount,
    ];
  }

  /**
   * Starts a new incremental audit, capturing stable table ID ceilings.
   *
   * @return array
   *   Initial audit state containing only status, cursor and counts.
   */
  public function startAudit(): array {
    $transaction = $this->database->startTransaction();
    try {
      $state = $this->loadAuditState(TRUE);
      if ($state !== NULL && $state['status'] === 'running') {
        throw new \LogicException('An ACULTA Content Index audit is already running.');
      }
      $pointer = $this->activePointer();
      $values = [
        'status' => 'running',
        'phase' => 'entries',
        'cursor_id' => 0,
        'entry_max_id' => $this->maxId('aculta_index_entry', 'entry_id'),
        'relation_max_id' => $this->maxId('aculta_index_relation', 'relation_id'),
        'orphan_entry_count' => 0,
        'orphan_relation_count' => 0,
        'relation_source_missing_count' => 0,
        'stale_projection_count' => 0,
        'active_pointer_valid' => (int) $pointer['valid'],
        'active_generation' => $pointer['generation'] ?? '',
        'created' => $this->time->getRequestTime(),
        'updated' => $this->time->getRequestTime(),
        'completed' => 0,
      ];
      if ($state === NULL) {
        $this->database->insert('aculta_index_audit')
          ->fields(['audit_id' => 1, 'revision' => 1] + $values)
          ->execute();
      }
      else {
        $this->database->update('aculta_index_audit')
          ->fields($values + ['revision' => ((int) $state['revision']) + 1])
          ->condition('audit_id', 1)
          ->condition('revision', $state['revision'])
          ->execute();
      }
      unset($transaction);
      return $this->getAuditState();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Processes one keyset batch in the current audit phase.
   *
   * The cursor advances over table IDs, not result matches, so batches stay
   * bounded even for a clean catalog. Captured maxima make this run a finite
   * scan window; inserts beyond those IDs are handled by a later audit cycle.
   *
   * @return array
   *   Current audit status, progress cursor and aggregate findings.
   */
  public function processAuditBatch(int $limit = 200): array {
    if ($limit < 1 || $limit > self::MAX_BATCH_SIZE) {
      throw new \InvalidArgumentException(sprintf('Content Index audit batch size must be between 1 and %d.', self::MAX_BATCH_SIZE));
    }
    $transaction = $this->database->startTransaction();
    try {
      $state = $this->loadAuditState(TRUE);
      if ($state === NULL || $state['status'] !== 'running') {
        throw new \LogicException('No ACULTA Content Index audit is running.');
      }

      $rows = $state['phase'] === 'entries'
        ? $this->entryAuditRows((int) $state['cursor_id'], (int) $state['entry_max_id'], (string) $state['active_generation'], $limit)
        : $this->relationAuditRows((int) $state['cursor_id'], (int) $state['relation_max_id'], $limit);
      $updates = [
        'cursor_id' => $rows === [] ? (int) $state['cursor_id'] : (int) end($rows)['row_id'],
        'updated' => $this->time->getRequestTime(),
        'revision' => ((int) $state['revision']) + 1,
      ];
      $orphanEntries = 0;
      $orphanRelations = 0;
      $missingRelationSources = 0;
      $staleProjections = 0;
      $staleIdentities = [];
      foreach ($rows as $row) {
        if ($state['phase'] === 'entries') {
          $orphanEntries += (int) $row['orphan'];
          $staleProjections += (int) $row['stale'];
          if ($row['stale']) {
            $identityKey = hash('sha256', implode("\0", [
              $row['provider_id'],
              $row['entity_type'],
              $row['entity_id'],
              $row['langcode'],
            ]));
            $staleIdentities[$identityKey] = [
              'provider_id' => $row['provider_id'],
              'entity_type' => $row['entity_type'],
              'entity_id' => $row['entity_id'],
              'langcode' => $row['langcode'],
            ];
          }
        }
        else {
          $orphanRelations += (int) $row['orphan'];
          $missingRelationSources += (int) $row['source_missing'];
        }
      }
      if ($state['phase'] === 'entries') {
        $updates['orphan_entry_count'] = (int) $state['orphan_entry_count'] + $orphanEntries;
        $updates['stale_projection_count'] = (int) $state['stale_projection_count'] + $staleProjections;
      }
      else {
        $updates['orphan_relation_count'] = (int) $state['orphan_relation_count'] + $orphanRelations;
        $updates['relation_source_missing_count'] = (int) $state['relation_source_missing_count'] + $missingRelationSources;
      }

      $phaseMaximum = $state['phase'] === 'entries' ? (int) $state['entry_max_id'] : (int) $state['relation_max_id'];
      $phaseFinished = (int) $updates['cursor_id'] >= $phaseMaximum || count($rows) < $limit;
      if ($phaseFinished && $state['phase'] === 'entries') {
        $updates['phase'] = 'relations';
        $updates['cursor_id'] = 0;
      }
      elseif ($phaseFinished) {
        $currentPointer = $this->activePointer();
        $samePointer = ($currentPointer['generation'] ?? '') === (string) $state['active_generation'];
        $updates['active_pointer_valid'] = (int) ((bool) $state['active_pointer_valid'] && $currentPointer['valid'] && $samePointer);
        $updates['status'] = 'complete';
        $updates['completed'] = $this->time->getRequestTime();
      }

      $this->database->update('aculta_index_audit')
        ->fields($updates)
        ->condition('audit_id', 1)
        ->condition('status', 'running')
        ->condition('revision', $state['revision'])
        ->execute();
      unset($transaction);

      // Requeue after the audit checkpoint commits. If the active generation
      // changed during the batch, its new rebuild supersedes this repair work.
      $currentPointer = $this->activePointer();
      if (($currentPointer['generation'] ?? '') === (string) $state['active_generation']) {
        foreach ($staleIdentities as $identity) {
          if ($this->providers->has($identity['provider_id'])) {
            $this->work->schedule(
              $identity['provider_id'],
              $identity['entity_type'],
              $identity['entity_id'],
              $identity['langcode'],
            );
          }
        }
      }
      return $this->getAuditState();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Returns the persisted aggregate state without entity metadata.
   */
  public function getAuditState(): array {
    $state = $this->loadAuditState();
    if ($state === NULL) {
      return ['status' => 'idle'];
    }
    unset($state['revision'], $state['entry_max_id'], $state['relation_max_id']);
    $state['valid'] = $state['status'] === 'complete'
      && (bool) $state['active_pointer_valid']
      && (int) $state['orphan_entry_count'] === 0
      && (int) $state['orphan_relation_count'] === 0
      && (int) $state['relation_source_missing_count'] === 0
      && (int) $state['stale_projection_count'] === 0;
    return $state;
  }

  /**
   * Runs one bounded cron batch, starting a cycle at most once per day.
   *
   * @return array
   *   Current audit state and aggregate findings.
   */
  public function runScheduledAuditBatch(int $limit = 200): array {
    $state = $this->loadAuditState();
    $now = $this->time->getRequestTime();
    if ($state === NULL
      || ($state['status'] === 'complete' && (int) $state['completed'] <= $now - self::SCHEDULE_INTERVAL)
      || ($state['status'] === 'idle')) {
      try {
        $this->startAudit();
      }
      catch (\LogicException) {
        // Another cron request may have started the same scheduled cycle.
        $state = $this->loadAuditState();
        if ($state === NULL || $state['status'] !== 'running') {
          throw new \LogicException('Unable to start the scheduled Content Index audit.');
        }
      }
    }
    $state = $this->loadAuditState();
    if ($state !== NULL && $state['status'] === 'running') {
      return $this->processAuditBatch($limit);
    }
    return $this->getAuditState();
  }

  /**
   * Reads entry IDs and whether each references a known generation.
   *
   * @return list<array>
   *   Bounded scan rows.
   */
  private function entryAuditRows(int $after, int $maximum, string $activeGeneration, int $limit): array {
    if ($maximum === 0 || $after >= $maximum) {
      return [];
    }
    $query = $this->database->select('aculta_index_entry', 'entry')
      ->fields('entry', [
        'entry_id',
        'generation_id',
        'provider_id',
        'entity_type',
        'entity_id',
        'langcode',
        'provider_version',
      ]);
    $query->addField('generation', 'generation_id', 'found_generation');
    $query->leftJoin('aculta_index_generation', 'generation', 'entry.generation_id = generation.generation_id');
    $rows = $query->condition('entry.entry_id', $after, '>')
      ->condition('entry.entry_id', $maximum, '<=')
      ->orderBy('entry.entry_id')
      ->range(0, $limit)
      ->execute();
    $result = [];
    foreach ($rows as $row) {
      $isActiveGeneration = $activeGeneration !== '' && (string) $row->generation_id === $activeGeneration;
      $result[] = [
        'row_id' => (int) $row->entry_id,
        'orphan' => $row->found_generation === NULL,
        'stale' => $isActiveGeneration && $this->isStaleProjection((string) $row->provider_id, (int) $row->provider_version),
        'provider_id' => (string) $row->provider_id,
        'entity_type' => (string) $row->entity_type,
        'entity_id' => (string) $row->entity_id,
        'langcode' => (string) $row->langcode,
      ];
    }
    return $result;
  }

  /**
   * Counts entries in one generation with a mismatched provider version.
   *
   * Providers that are disabled are ignored.
   */
  private function countStaleProjections(?string $generationId): int {
    if ($generationId === NULL || $generationId === '') {
      return 0;
    }

    $count = 0;
    foreach ($this->providers->all() as $providerId => $provider) {
      $count += (int) $this->database->select('aculta_index_entry', 'entry')
        ->condition('generation_id', $generationId)
        ->condition('provider_id', $providerId)
        ->condition('provider_version', $provider->getProjectionVersion(), '<>')
        ->countQuery()
        ->execute()
        ->fetchField();
    }
    return $count;
  }

  /**
   * Determines whether a projection's provider version needs reindexing.
   */
  private function isStaleProjection(string $providerId, int $storedVersion): bool {
    return $this->providers->has($providerId)
      && $storedVersion !== $this->providers->get($providerId)->getProjectionVersion();
  }

  /**
   * Reads relation IDs and checks generation/source existence.
   *
   * @return list<array{row_id: int, orphan: bool, source_missing: bool}>
   *   Bounded scan rows.
   */
  private function relationAuditRows(int $after, int $maximum, int $limit): array {
    if ($maximum === 0 || $after >= $maximum) {
      return [];
    }
    $query = $this->database->select('aculta_index_relation', 'relation')
      ->fields('relation', ['relation_id']);
    $query->addField('generation', 'generation_id', 'found_generation');
    $query->addField('entry', 'entry_id', 'found_source');
    $query->leftJoin('aculta_index_generation', 'generation', 'relation.generation_id = generation.generation_id');
    $query->leftJoin(
      'aculta_index_entry',
      'entry',
      'relation.generation_id = entry.generation_id'
        . ' AND relation.provider_id = entry.provider_id'
        . ' AND relation.source_entity_type = entry.entity_type'
        . ' AND relation.source_entity_id = entry.entity_id'
        . ' AND relation.source_langcode = entry.langcode',
    );
    $rows = $query->condition('relation.relation_id', $after, '>')
      ->condition('relation.relation_id', $maximum, '<=')
      ->orderBy('relation.relation_id')
      ->range(0, $limit)
      ->execute();
    $result = [];
    foreach ($rows as $row) {
      $result[] = [
        'row_id' => (int) $row->relation_id,
        'orphan' => $row->found_generation === NULL,
        'source_missing' => $row->found_source === NULL,
      ];
    }
    return $result;
  }

  /**
   * Returns the active generation pointer's current consistency state.
   *
   * @return array{valid: bool, generation: string|null, active_count: int}
   *   Pointer consistency and identity.
   */
  private function activePointer(): array {
    $state = $this->database->select('aculta_index_state', 'state')
      ->fields('state', ['active_generation'])
      ->condition('state_id', 1)
      ->execute()
      ->fetchField();
    $generation = $state === FALSE || $state === '' ? NULL : (string) $state;
    $activeCount = (int) $this->database->select('aculta_index_generation', 'generation')
      ->condition('status', 'active')
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($state === FALSE) {
      return ['valid' => FALSE, 'generation' => NULL, 'active_count' => $activeCount];
    }
    if ($generation === NULL) {
      return ['valid' => $activeCount === 0, 'generation' => NULL, 'active_count' => $activeCount];
    }
    $status = $this->database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['status'])
      ->condition('generation_id', $generation)
      ->execute()
      ->fetchField();
    return [
      'valid' => $status === 'active' && $activeCount === 1,
      'generation' => $generation,
      'active_count' => $activeCount,
    ];
  }

  /**
   * Loads the singleton audit state, optionally locking it for update.
   */
  private function loadAuditState(bool $forUpdate = FALSE): ?array {
    $query = $this->database->select('aculta_index_audit', 'audit')
      ->fields('audit');
    if ($forUpdate) {
      $query->forUpdate();
    }
    $state = $query->condition('audit_id', 1)->execute()->fetchAssoc();
    return $state ?: NULL;
  }

  /**
   * Gets the greatest primary key without scanning a table.
   */
  private function maxId(string $table, string $field): int {
    $maximum = $this->database->select($table, 'item')
      ->fields('item', [$field])
      ->orderBy($field, 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    return $maximum === FALSE ? 0 : (int) $maximum;
  }

  /**
   * Counts entries whose generation is missing for rebuild validation.
   */
  private function countOrphanEntries(): int {
    $query = $this->database->select('aculta_index_entry', 'entry');
    $query->leftJoin('aculta_index_generation', 'generation', 'entry.generation_id = generation.generation_id');
    return (int) $query->condition('generation.generation_id', NULL, 'IS NULL')
      ->countQuery()->execute()->fetchField();
  }

  /**
   * Counts relations whose generation is missing for rebuild validation.
   */
  private function countOrphanRelations(): int {
    $query = $this->database->select('aculta_index_relation', 'relation');
    $query->leftJoin('aculta_index_generation', 'generation', 'relation.generation_id = generation.generation_id');
    return (int) $query->condition('generation.generation_id', NULL, 'IS NULL')
      ->countQuery()->execute()->fetchField();
  }

  /**
   * Counts relations whose source projection is missing for rebuild validation.
   */
  private function countMissingRelationSources(): int {
    $query = $this->database->select('aculta_index_relation', 'relation');
    $query->leftJoin(
      'aculta_index_entry',
      'entry',
      'relation.generation_id = entry.generation_id'
        . ' AND relation.provider_id = entry.provider_id'
        . ' AND relation.source_entity_type = entry.entity_type'
        . ' AND relation.source_entity_id = entry.entity_id'
        . ' AND relation.source_langcode = entry.langcode',
    );
    return (int) $query->condition('entry.entry_id', NULL, 'IS NULL')
      ->countQuery()->execute()->fetchField();
  }

}
