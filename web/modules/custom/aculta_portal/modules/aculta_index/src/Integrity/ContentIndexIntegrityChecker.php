<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Integrity;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_index\Rebuild\ContentIndexEntityEnumerator;
use Drupal\aculta_index\Storage\ContentIndexWriter;
use Psr\Log\LoggerInterface;

/**
 * Checks catalog structure and runs resumable, bounded integrity audits.
 */
final class ContentIndexIntegrityChecker {

  /**
   * Whether per-batch diagnostics are enabled for this process.
   */
  private readonly bool $diagnosticsEnabled;

  /**
   * Source and relation cache identities retained during entry reconciliation.
   *
   * @var array<string, array<string, string>>
   */
  private array $pendingAuditCacheResetIds = [];

  /**
   * Number of distinct pending source and relation cache identities.
   */
  private int $pendingAuditCacheResetCount = 0;

  /**
   * Maximum rows inspected by one incremental audit batch.
   */
  private const MAX_BATCH_SIZE = 500;

  /**
   * Maximum source identities retained across audit entry batches.
   */
  private const SOURCE_AUDIT_CACHE_RESET_ENTITY_LIMIT = 5_000;

  /**
   * Minimum delay between completed scheduled audit cycles.
   */
  private const SCHEDULE_INTERVAL = 86400;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly ContentIndexProviderRegistry $providers,
    private readonly ContentIndexWorkManager $work,
    private readonly ContentIndexEntityEnumerator $enumerator,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ContentIndexWriter $writer,
    private readonly LoggerInterface $logger,
    ?Settings $settings = NULL,
  ) {
    $this->diagnosticsEnabled = (bool) ($settings?->get('aculta_index_diagnostics', FALSE) ?? FALSE);
  }

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
        'source_provider_cursor' => '',
        'source_entity_type_cursor' => '',
        'source_entity_id_cursor' => '',
        'source_entity_max_id' => '',
        'orphan_entry_count' => 0,
        'orphan_relation_count' => 0,
        'relation_source_missing_count' => 0,
        'stale_projection_count' => 0,
        'missing_source_count' => 0,
        'unverified_source_count' => 0,
        'missing_catalog_entry_count' => 0,
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
    $batchStartedAt = hrtime(TRUE);
    $scanMilliseconds = 0.0;
    $reconciliationMilliseconds = 0.0;
    $checkpointMilliseconds = 0.0;
    $transaction = $this->database->startTransaction();
    try {
      $state = $this->loadAuditState(TRUE);
      if ($state === NULL || $state['status'] !== 'running') {
        throw new \LogicException('No ACULTA Content Index audit is running.');
      }

      $sourceBatch = NULL;
      $sourceCursor = NULL;
      if ($state['phase'] === 'entries') {
        $rows = $this->entryAuditRows((int) $state['cursor_id'], (int) $state['entry_max_id'], (string) $state['active_generation'], $limit);
      }
      elseif ($state['phase'] === 'relations') {
        $rows = $this->relationAuditRows((int) $state['cursor_id'], (int) $state['relation_max_id'], $limit);
      }
      else {
        $sourceCursor = $this->sourceCursorFromState($state);
        $sourceMaximum = (string) $state['source_entity_max_id'];
        $sourceBatch = $sourceCursor === NULL || $sourceMaximum === ''
          ? ['projections' => [], 'next_cursor' => NULL, 'exhausted' => TRUE]
          : $this->enumerator->nextBatch(
            $sourceCursor['provider_id'],
            $sourceCursor['entity_type'],
            $state['source_entity_id_cursor'] === '' ? NULL : (string) $state['source_entity_id_cursor'],
            $limit,
            $sourceMaximum,
          );
        $rows = [];
      }
      $scanMilliseconds = (hrtime(TRUE) - $batchStartedAt) / 1_000_000;
      $updates = [
        'cursor_id' => $rows === [] ? (int) $state['cursor_id'] : (int) end($rows)['row_id'],
        'updated' => $this->time->getRequestTime(),
        'revision' => ((int) $state['revision']) + 1,
      ];
      $orphanEntries = 0;
      $orphanRelations = 0;
      $missingRelationSources = 0;
      $sourceAudit = ['stale' => 0, 'missing' => 0, 'unverified' => 0, 'project' => [], 'delete' => []];
      $sourceCatalogAudit = ['missing' => 0, 'stale' => 0, 'unverified' => 0, 'project' => []];
      foreach ($rows as $row) {
        if ($state['phase'] === 'entries') {
          $orphanEntries += (int) $row['orphan'];
        }
        else {
          $orphanRelations += (int) $row['orphan'];
          $missingRelationSources += (int) $row['source_missing'];
        }
      }
      $reconciliationStartedAt = hrtime(TRUE);
      if ($state['phase'] === 'entries') {
        $sourceAudit = $this->reconcileEntrySources($rows, (string) $state['active_generation']);
      }
      elseif ($sourceBatch !== NULL && $sourceCursor !== NULL) {
        $sourceCatalogAudit = $this->reconcileCatalogCoverage(
          $sourceCursor['provider_id'],
          $sourceBatch['projections'],
          (string) $state['active_generation'],
        );
      }
      $reconciliationMilliseconds = (hrtime(TRUE) - $reconciliationStartedAt) / 1_000_000;
      if ($state['phase'] === 'entries') {
        $updates['orphan_entry_count'] = (int) $state['orphan_entry_count'] + $orphanEntries;
        $updates['stale_projection_count'] = (int) $state['stale_projection_count'] + $sourceAudit['stale'];
        $updates['missing_source_count'] = (int) $state['missing_source_count'] + $sourceAudit['missing'];
        $updates['unverified_source_count'] = (int) $state['unverified_source_count'] + $sourceAudit['unverified'];
      }
      else {
        $updates['orphan_relation_count'] = (int) $state['orphan_relation_count'] + $orphanRelations;
        $updates['relation_source_missing_count'] = (int) $state['relation_source_missing_count'] + $missingRelationSources;
        if ($state['phase'] === 'sources') {
          $updates['stale_projection_count'] = (int) $state['stale_projection_count'] + $sourceCatalogAudit['stale'];
          $updates['unverified_source_count'] = (int) $state['unverified_source_count'] + $sourceCatalogAudit['unverified'];
          $updates['missing_catalog_entry_count'] = (int) $state['missing_catalog_entry_count'] + $sourceCatalogAudit['missing'];
        }
      }

      $phaseMaximum = $state['phase'] === 'entries'
        ? (int) $state['entry_max_id']
        : (int) $state['relation_max_id'];
      $phaseFinished = $state['phase'] === 'sources'
        ? (bool) $sourceBatch['exhausted']
        : ((int) $updates['cursor_id'] >= $phaseMaximum || count($rows) < $limit);
      if ($state['phase'] === 'entries' && $phaseFinished) {
        $this->flushAuditEntityCaches();
      }
      if ($state['phase'] === 'sources') {
        $updates['source_entity_id_cursor'] = $sourceBatch['next_cursor'] ?? (string) $state['source_entity_id_cursor'];
        if ($phaseFinished) {
          $nextSourceCursor = $this->nextSourceCursor(
            (string) $state['source_provider_cursor'],
            (string) $state['source_entity_type_cursor'],
          );
          if ($nextSourceCursor === NULL) {
            $this->completeAuditFields($updates, $state);
            $updates['source_entity_max_id'] = '';
          }
          else {
            $updates['source_provider_cursor'] = $nextSourceCursor['provider_id'];
            $updates['source_entity_type_cursor'] = $nextSourceCursor['entity_type'];
            $updates['source_entity_id_cursor'] = '';
            $updates['source_entity_max_id'] = $this->enumerator->maximumEntityId(
              $nextSourceCursor['provider_id'],
              $nextSourceCursor['entity_type'],
            ) ?? '';
          }
        }
      }
      elseif ($phaseFinished && $state['phase'] === 'entries') {
        $updates['phase'] = 'relations';
        $updates['cursor_id'] = 0;
      }
      elseif ($phaseFinished && $state['phase'] === 'relations') {
        $firstSourceCursor = (string) $state['active_generation'] === ''
          ? NULL
          : $this->nextSourceCursor(NULL, NULL);
        if ($firstSourceCursor === NULL) {
          $this->completeAuditFields($updates, $state);
        }
        else {
          $updates['phase'] = 'sources';
          $updates['cursor_id'] = 0;
          $updates['source_provider_cursor'] = $firstSourceCursor['provider_id'];
          $updates['source_entity_type_cursor'] = $firstSourceCursor['entity_type'];
          $updates['source_entity_id_cursor'] = '';
          $updates['source_entity_max_id'] = $this->enumerator->maximumEntityId(
            $firstSourceCursor['provider_id'],
            $firstSourceCursor['entity_type'],
          ) ?? '';
        }
      }

      $checkpointStartedAt = hrtime(TRUE);
      $checkpointed = $this->database->update('aculta_index_audit')
        ->fields($updates)
        ->condition('audit_id', 1)
        ->condition('status', 'running')
        ->condition('revision', $state['revision'])
        ->execute();
      $checkpointMilliseconds = (hrtime(TRUE) - $checkpointStartedAt) / 1_000_000;
      if ($checkpointed !== 1) {
        throw new \LogicException('The Content Index audit checkpoint changed during batch processing.');
      }
      unset($transaction);

      // Requeue after the audit checkpoint commits. If the active generation
      // changed during the batch, its new rebuild supersedes this repair work.
      $queueStartedAt = hrtime(TRUE);
      $currentPointer = $this->activePointer();
      if (($currentPointer['generation'] ?? '') === (string) $state['active_generation']) {
        foreach ($sourceAudit['project'] as $identity) {
          if ($this->providers->has($identity['provider_id'])) {
            $this->work->schedule(
              $identity['provider_id'],
              $identity['entity_type'],
              $identity['entity_id'],
              $identity['langcode'],
            );
          }
        }
        foreach ($sourceAudit['delete'] as $identity) {
          if ($this->providers->has($identity['provider_id'])) {
            $this->work->schedule(
              $identity['provider_id'],
              $identity['entity_type'],
              $identity['entity_id'],
              $identity['langcode'],
              'delete',
            );
          }
        }
        foreach ($sourceCatalogAudit['project'] as $identity) {
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
      $queueMilliseconds = (hrtime(TRUE) - $queueStartedAt) / 1_000_000;
      $projectionCount = $sourceBatch === NULL ? 0 : count($sourceBatch['projections']);
      $scanCount = $sourceBatch === NULL ? count($rows) : $projectionCount;
      if ($this->diagnosticsEnabled) {
        $this->logger->debug('Content Index integrity batch completed. Phase: @phase; scanned: @scan_count; projections: @projection_count; scan: @scan_ms ms; reconcile: @reconcile_ms ms; checkpoint: @checkpoint_ms ms; queue: @queue_ms ms; total: @total_ms ms.', [
          '@phase' => $state['phase'],
          '@scan_count' => $scanCount,
          '@projection_count' => $projectionCount,
          '@scan_ms' => round($scanMilliseconds, 3),
          '@reconcile_ms' => round($reconciliationMilliseconds, 3),
          '@checkpoint_ms' => round($checkpointMilliseconds, 3),
          '@queue_ms' => round($queueMilliseconds, 3),
          '@total_ms' => round((hrtime(TRUE) - $batchStartedAt) / 1_000_000, 3),
        ]);
      }
      return $this->getAuditState();
    }
    catch (\Throwable $exception) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
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
    unset(
      $state['revision'],
      $state['entry_max_id'],
      $state['relation_max_id'],
      $state['source_provider_cursor'],
      $state['source_entity_type_cursor'],
      $state['source_entity_id_cursor'],
      $state['source_entity_max_id'],
    );
    $state['valid'] = $state['status'] === 'complete'
      && (bool) $state['active_pointer_valid']
      && (int) $state['orphan_entry_count'] === 0
      && (int) $state['orphan_relation_count'] === 0
      && (int) $state['relation_source_missing_count'] === 0
      && (int) $state['stale_projection_count'] === 0
      && (int) $state['missing_source_count'] === 0
      && (int) $state['missing_catalog_entry_count'] === 0
      && (int) $state['unverified_source_count'] === 0;
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
        'bundle',
        'provider_version',
        'projection_hash',
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
        'provider_id' => (string) $row->provider_id,
        'entity_type' => (string) $row->entity_type,
        'entity_id' => (string) $row->entity_id,
        'langcode' => (string) $row->langcode,
        'bundle' => (string) $row->bundle,
        'provider_version' => (int) $row->provider_version,
        'projection_hash' => (string) $row->projection_hash,
        'active_generation' => $isActiveGeneration,
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
   * Revalidates active source rows in bounded batches under their owner.
   *
   * Missing or no-longer-indexable sources are queued for deletion only when
   * the registered provider still owns the stored entity type and bundle.
   * Providers/types/bundles whose ownership cannot be confirmed are retained.
   *
   * @param list<array> $rows
   *   Rows selected by the structural audit cursor.
   * @param string $activeGeneration
   *   Generation captured when this audit cycle began.
   *
   * @return array{stale: int, missing: int, unverified: int, project: array, delete: array}
   *   Findings and deduplicated work identities.
   */
  private function reconcileEntrySources(array $rows, string $activeGeneration): array {
    $result = ['stale' => 0, 'missing' => 0, 'unverified' => 0, 'project' => [], 'delete' => []];
    $eligible = [];
    $idsByType = [];
    foreach ($rows as $row) {
      if ($activeGeneration === '' || !$row['active_generation']) {
        continue;
      }
      if (!$this->providers->has($row['provider_id'])) {
        $result['unverified']++;
        continue;
      }
      $provider = $this->providers->get($row['provider_id']);
      $supported = $provider->getSupportedEntityTypes();
      if (!in_array($row['bundle'], $supported[$row['entity_type']] ?? [], TRUE)
        || !$this->entityTypeManager->hasDefinition($row['entity_type'])) {
        $result['unverified']++;
        continue;
      }
      $eligible[] = $row;
      $idsByType[$row['entity_type']][hash('sha256', $row['entity_id'])] = $row['entity_id'];
    }

    $entitiesByType = [];
    $cacheResetIdsByType = $idsByType;
    $resetAllCaches = FALSE;
    foreach ($idsByType as $entityTypeId => $entityIds) {
      $loadedEntities = $this->entityTypeManager
        ->getStorage($entityTypeId)
        ->loadMultiple(array_values($entityIds));
      foreach ($loadedEntities as $entity) {
        if ($entity instanceof ContentEntityInterface) {
          $entitiesByType[$entityTypeId][hash('sha256', (string) $entity->id())] = $entity;
        }
      }
    }

    foreach ($eligible as $row) {
      $key = hash('sha256', implode("\0", [
        $row['provider_id'],
        $row['entity_type'],
        $row['entity_id'],
        $row['langcode'],
      ]));
      $identity = [
        'provider_id' => $row['provider_id'],
        'entity_type' => $row['entity_type'],
        'entity_id' => $row['entity_id'],
        'langcode' => $row['langcode'],
      ];
      $entity = $entitiesByType[$row['entity_type']][hash('sha256', $row['entity_id'])] ?? NULL;
      if (!$entity instanceof ContentEntityInterface) {
        $result['missing']++;
        $result['delete'][$key] = $identity;
        continue;
      }
      $supportedBundles = $this->providers->get($row['provider_id'])
        ->getSupportedEntityTypes()[$row['entity_type']] ?? [];
      if (!in_array($entity->bundle(), $supportedBundles, TRUE)) {
        // The provider still owns the stored bundle, but no longer owns the
        // current source bundle. Remove only this provider's old projection.
        $result['missing']++;
        $result['delete'][$key] = $identity;
        continue;
      }
      if (!$entity->hasTranslation($row['langcode'])) {
        $result['missing']++;
        $result['delete'][$key] = $identity;
        continue;
      }

      $provider = $this->providers->get($row['provider_id']);
      if ($provider instanceof ContentIndexIndexabilityProviderInterface) {
        try {
          $indexable = $provider->isIndexable($entity->getTranslation($row['langcode']));
        }
        catch (\Throwable $exception) {
          $resetAllCaches = TRUE;
          $this->logger->warning('Content Index source eligibility could not be verified. Error class: @class', [
            '@class' => $exception::class,
          ]);
          $result['unverified']++;
          continue;
        }
        if (!$indexable) {
          $result['missing']++;
          $result['delete'][$key] = $identity;
        }
        // Full hashes and provider versions are compared during the source
        // coverage pass, which projects each currently discoverable source
        // once. Avoid rebuilding rich projections in this entry pass.
        continue;
      }

      // Providers without the optional eligibility contract retain complete
      // source verification in this pass.
      try {
        $projection = $provider->project($entity->getTranslation($row['langcode']));
        if ($projection === NULL) {
          $resetAllCaches = TRUE;
          $result['missing']++;
          $result['delete'][$key] = $identity;
          continue;
        }
        if ($projection->entityTypeId !== $row['entity_type']
          || $projection->entityId !== $row['entity_id']
          || $projection->langcode !== $row['langcode']) {
          $resetAllCaches = TRUE;
          $result['unverified']++;
          continue;
        }
        foreach ($projection->relations as $relation) {
          if ($relation instanceof ContentIndexRelation) {
            $cacheResetIdsByType[$relation->targetEntityTypeId][hash('sha256', $relation->targetEntityId)] = $relation->targetEntityId;
          }
        }
        $currentHash = $this->writer->calculateProjectionHash($row['provider_id'], $projection);
      }
      catch (\Throwable $exception) {
        $resetAllCaches = TRUE;
        // A changed bundle is outside the provider's declared ownership.
        $this->logger->warning('Content Index source reconciliation could not project a source. Error class: @class', [
          '@class' => $exception::class,
        ]);
        $result['unverified']++;
        continue;
      }
      if ($row['provider_version'] !== $provider->getProjectionVersion()
        || !hash_equals($row['projection_hash'], $currentHash)) {
        $result['stale']++;
        $result['project'][$key] = $identity;
      }
    }
    if ($resetAllCaches) {
      $this->resetAllContentEntityStorageCaches();
      $this->pendingAuditCacheResetIds = [];
      $this->pendingAuditCacheResetCount = 0;
    }
    else {
      $this->queueAuditEntityCacheReset($cacheResetIdsByType);
    }
    return $result;
  }

  /**
   * Queues loaded sources and relation targets for a bounded cache reset.
   *
   * @param array<string, array<string, string>> $entityIdsByType
   *   Entity IDs keyed by type and hashed identity.
   */
  private function queueAuditEntityCacheReset(array $entityIdsByType): void {
    foreach ($entityIdsByType as $entityTypeId => $entityIds) {
      foreach ($entityIds as $key => $entityId) {
        if (!isset($this->pendingAuditCacheResetIds[$entityTypeId][$key])) {
          $this->pendingAuditCacheResetIds[$entityTypeId][$key] = $entityId;
          $this->pendingAuditCacheResetCount++;
        }
      }
    }
    if ($this->pendingAuditCacheResetCount >= self::SOURCE_AUDIT_CACHE_RESET_ENTITY_LIMIT) {
      $this->flushAuditEntityCaches();
    }
  }

  /**
   * Evicts pending source and relation entities from storage caches.
   */
  private function flushAuditEntityCaches(): void {
    foreach ($this->pendingAuditCacheResetIds as $entityTypeId => $entityIds) {
      if (!$this->entityTypeManager->hasDefinition($entityTypeId)
        || !$this->entityTypeManager->getDefinition($entityTypeId)->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }
      $this->entityTypeManager->getStorage($entityTypeId)->resetCache(array_values($entityIds));
    }
    $this->pendingAuditCacheResetIds = [];
    $this->pendingAuditCacheResetCount = 0;
  }

  /**
   * Clears all content entity caches after an unverifiable projection.
   */
  private function resetAllContentEntityStorageCaches(): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if ($entityType->entityClassImplements(ContentEntityInterface::class)) {
        $this->entityTypeManager->getStorage($entityTypeId)->resetCache();
      }
    }
  }

  /**
   * Finds source projections missing or stale in the active catalog generation.
   *
   * @param string $providerId
   *   Provider currently enumerating source entities.
   * @param list<\Drupal\aculta_portal\ContentIndex\ContentIndexProjection> $projections
   *   Bounded provider projections from one entity type.
   * @param string $generationId
   *   Generation captured by the audit checkpoint.
   *
   * @return array{missing: int, stale: int, unverified: int, project: array}
   *   Findings and work identities.
   */
  private function reconcileCatalogCoverage(string $providerId, array $projections, string $generationId): array {
    $result = ['missing' => 0, 'stale' => 0, 'unverified' => 0, 'project' => []];
    if ($projections === [] || !$this->providers->has($providerId)) {
      return $result;
    }

    $entityIds = [];
    $langcodes = [];
    foreach ($projections as $projection) {
      if (!$projection instanceof ContentIndexProjection) {
        $result['unverified']++;
        continue;
      }
      $entityIds[hash('sha256', $projection->entityId)] = $projection->entityId;
      $langcodes[$projection->langcode] = $projection->langcode;
    }
    if ($entityIds === []) {
      return $result;
    }

    $provider = $this->providers->get($providerId);
    $entityTypeId = $projections[0]->entityTypeId;
    $rows = $this->database->select('aculta_index_entry', 'entry')
      ->fields('entry', ['entity_id', 'langcode', 'provider_id', 'provider_version', 'projection_hash'])
      ->condition('generation_id', $generationId)
      ->condition('entity_type', $entityTypeId)
      ->condition('entity_id', array_values($entityIds), 'IN')
      ->condition('langcode', array_values($langcodes), 'IN')
      ->execute();
    $stored = [];
    foreach ($rows as $row) {
      $identityKey = hash('sha256', implode("\0", [$entityTypeId, (string) $row->entity_id, (string) $row->langcode]));
      $stored[$identityKey] = [
        'provider_id' => (string) $row->provider_id,
        'provider_version' => (int) $row->provider_version,
        'projection_hash' => (string) $row->projection_hash,
      ];
    }

    foreach ($projections as $projection) {
      if (!$projection instanceof ContentIndexProjection) {
        continue;
      }
      $key = hash('sha256', implode("\0", [$entityTypeId, $projection->entityId, $projection->langcode]));
      $identity = [
        'provider_id' => $providerId,
        'entity_type' => $projection->entityTypeId,
        'entity_id' => $projection->entityId,
        'langcode' => $projection->langcode,
      ];
      if (!isset($stored[$key])) {
        $result['missing']++;
        $result['project'][$key] = $identity;
        continue;
      }
      if ($stored[$key]['provider_id'] !== $providerId) {
        $result['unverified']++;
        continue;
      }
      try {
        $currentHash = $this->writer->calculateProjectionHash($providerId, $projection);
      }
      catch (\Throwable $exception) {
        $this->logger->warning('Content Index source coverage could not verify a projection. Error class: @class', [
          '@class' => $exception::class,
        ]);
        $result['unverified']++;
        continue;
      }
      if ($stored[$key]['provider_version'] !== $provider->getProjectionVersion()
        || !hash_equals($stored[$key]['projection_hash'], $currentHash)) {
        $result['stale']++;
        $result['project'][$key] = $identity;
      }
    }
    return $result;
  }

  /**
   * Returns the provider/type pair stored by an active source audit cursor.
   */
  private function sourceCursorFromState(array $state): ?array {
    if ((string) $state['active_generation'] === '') {
      return NULL;
    }
    $providerId = (string) $state['source_provider_cursor'];
    $entityTypeId = (string) $state['source_entity_type_cursor'];
    if (!$this->providers->has($providerId)) {
      return NULL;
    }
    $supported = $this->providers->get($providerId)->getSupportedEntityTypes();
    return isset($supported[$entityTypeId])
      ? ['provider_id' => $providerId, 'entity_type' => $entityTypeId]
      : NULL;
  }

  /**
   * Finds the first or next provider/type pair in stable lexical order.
   */
  private function nextSourceCursor(?string $afterProviderId, ?string $afterEntityTypeId): ?array {
    $afterKey = $afterProviderId === NULL ? NULL : $afterProviderId . "\0" . ($afterEntityTypeId ?? '');
    $providers = $this->providers->all();
    ksort($providers);
    foreach ($providers as $providerId => $provider) {
      $entityTypes = $provider->getSupportedEntityTypes();
      ksort($entityTypes);
      foreach ($entityTypes as $entityTypeId => $_bundles) {
        $key = $providerId . "\0" . $entityTypeId;
        if ($afterKey === NULL || strcmp($key, $afterKey) > 0) {
          return ['provider_id' => $providerId, 'entity_type' => $entityTypeId];
        }
      }
    }
    return NULL;
  }

  /**
   * Marks the audit complete and records whether its generation stayed active.
   */
  private function completeAuditFields(array &$updates, array $state): void {
    $currentPointer = $this->activePointer();
    $samePointer = ($currentPointer['generation'] ?? '') === (string) $state['active_generation'];
    $updates['active_pointer_valid'] = (int) ((bool) $state['active_pointer_valid'] && $currentPointer['valid'] && $samePointer);
    $updates['status'] = 'complete';
    $updates['completed'] = $this->time->getRequestTime();
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
