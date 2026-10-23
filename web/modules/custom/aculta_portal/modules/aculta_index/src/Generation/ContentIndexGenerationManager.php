<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Generation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;

/**
 * Owns validated generation lifecycle and active-pointer changes.
 */
final class ContentIndexGenerationManager {

  /**
   * Creates a new generation in the building state.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly UuidInterface $uuid,
    private readonly TimeInterface $time,
    private readonly ContentIndexWorkManager $workManager,
    private readonly ContentIndexProviderRegistry $providers,
  ) {}

  /**
   * Starts a rebuild generation and returns its opaque ID.
   */
  public function start(?string $providerFingerprint = NULL): string {
    if ($providerFingerprint !== NULL && preg_match('/^[a-f0-9]{64}$/', $providerFingerprint) !== 1) {
      throw new \InvalidArgumentException('Provider inventory fingerprint must be a SHA-256 hex digest.');
    }
    $id = $this->uuid->generate();
    $now = $this->time->getRequestTime();
    $this->database->insert('aculta_index_generation')
      ->fields([
        'generation_id' => $id,
        'status' => 'building',
        'provider_fingerprint' => $providerFingerprint ?? '',
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    return $id;
  }

  /**
   * Marks a building generation validated for activation.
   */
  public function markValidated(string $generationId): void {
    $now = $this->time->getRequestTime();
    $count = $this->database->update('aculta_index_generation')
      ->fields([
        'status' => 'validated',
        'validated' => $now,
        'updated' => $now,
      ])
      ->condition('generation_id', $generationId)
      ->condition('status', 'building')
      ->condition('backfill_completed', 0, '>')
      ->execute();

    if ($count !== 1) {
      throw new \InvalidArgumentException(sprintf('Generation %s is missing or is not building.', $generationId));
    }
  }

  /**
   * Marks a backfill complete only for the checkpoint revision just read.
   *
   * @param string $generationId
   *   Building generation identifier.
   * @param int $expectedRevision
   *   Checkpoint revision used by the final batch.
   */
  public function markBackfillCompleted(string $generationId, int $expectedRevision): void {
    $updated = $this->database->update('aculta_index_generation')
      ->fields([
        'backfill_completed' => $this->time->getRequestTime(),
        'cursor_revision' => $expectedRevision + 1,
        'updated' => $this->time->getRequestTime(),
      ])
      ->condition('generation_id', $generationId)
      ->condition('status', 'building')
      ->condition('cursor_revision', $expectedRevision)
      ->condition('backfill_completed', 0)
      ->execute();
    if ($updated !== 1) {
      throw new \RuntimeException(sprintf('Backfill checkpoint for generation %s changed or is no longer writable.', $generationId));
    }
  }

  /**
   * Switches the active pointer to a validated or retained generation.
   */
  public function activate(string $generationId): void {
    $transaction = $this->database->startTransaction();
    try {
      $target = $this->database->select('aculta_index_generation', 'generation')
        ->fields('generation', ['status', 'validated', 'provider_fingerprint'])
        ->condition('generation_id', $generationId)
        ->forUpdate()
        ->execute()
        ->fetchAssoc();
      if (!$target || !in_array($target['status'], ['validated', 'retained'], TRUE) || (int) $target['validated'] === 0) {
        throw new \InvalidArgumentException(sprintf('Generation %s is not validated for activation.', $generationId));
      }

      $state = $this->database->select('aculta_index_state', 'state')
        ->fields('state', ['active_generation', 'revision'])
        ->condition('state_id', 1)
        ->forUpdate()
        ->execute()
        ->fetchAssoc();
      if (!$state) {
        throw new \LogicException('The ACULTA Content Index active-generation pointer is missing.');
      }
      if ($target['status'] === 'validated') {
        if ($target['provider_fingerprint'] !== ''
          && !hash_equals((string) $target['provider_fingerprint'], $this->providers->fingerprint())) {
          throw new \RuntimeException('The provider inventory changed after validation; rebuild validation is required again.');
        }
        $gate = $this->workManager->getActivationGateState(array_keys($this->providers->all()));
        if (!$gate['ready']) {
          throw new \RuntimeException(sprintf(
            'Generation activation is waiting for source work to drain (queued: %d, processing: %d, retry: %d, dead: %d).',
            $gate['queued'],
            $gate['processing'],
            $gate['retry'],
            $gate['dead'],
          ));
        }
      }
      if ($state['active_generation'] === $generationId) {
        return;
      }

      $now = $this->time->getRequestTime();
      if ($state['active_generation'] !== '') {
        $retained = $this->database->update('aculta_index_generation')
          ->fields(['status' => 'retained', 'updated' => $now])
          ->condition('generation_id', $state['active_generation'])
          ->condition('status', 'active')
          ->execute();
        if ($retained !== 1) {
          throw new \LogicException('The active-generation pointer does not reference an active generation.');
        }
      }
      $activated = $this->database->update('aculta_index_generation')
        ->fields(['status' => 'active', 'activated' => $now, 'updated' => $now])
        ->condition('generation_id', $generationId)
        ->condition('status', $target['status'])
        ->execute();
      if ($activated !== 1) {
        throw new \RuntimeException('The validated generation changed concurrently; activation was rolled back.');
      }

      $updated = $this->database->update('aculta_index_state')
        ->fields([
          'active_generation' => $generationId,
          'revision' => (int) $state['revision'] + 1,
          'updated' => $now,
        ])
        ->condition('state_id', 1)
        ->condition('revision', (int) $state['revision'])
        ->execute();
      if ($updated !== 1) {
        throw new \RuntimeException('The active-generation pointer changed concurrently; activation was rolled back.');
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Returns the active catalog generation ID, or NULL before first build.
   */
  public function getActiveGeneration(): ?string {
    $active = $this->database->select('aculta_index_state', 'state')
      ->fields('state', ['active_generation'])
      ->condition('state_id', 1)
      ->execute()
      ->fetchField();

    return $active === FALSE || $active === '' ? NULL : (string) $active;
  }

  /**
   * Returns generations that receive live source changes.
   *
   * @return list<string>
   *   Active, building, and validated generation IDs. Retained generations are
   *   excluded pending the rollback synchronization/retention policy.
   */
  public function getWritableGenerations(): array {
    return array_map('strval', $this->database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['generation_id'])
      ->condition('status', ['active', 'building', 'validated'], 'IN')
      ->orderBy('created')
      ->execute()
      ->fetchCol());
  }

  /**
   * Stops a partial rebuild before its derived rows are removed in batches.
   *
   * Only a building generation can be discarded. Validated generations may be
   * awaiting activation and retained generations are rollback targets, so both
   * require a separate lifecycle decision.
   */
  public function discardBuilding(string $generationId): void {
    $transaction = $this->database->startTransaction();
    try {
      $generation = $this->database->select('aculta_index_generation', 'generation')
        ->fields('generation', ['status'])
        ->condition('generation_id', $generationId)
        ->forUpdate()
        ->execute()
        ->fetchAssoc();
      if (!$generation || $generation['status'] !== 'building') {
        throw new \InvalidArgumentException(sprintf('Generation %s is missing or is not an incomplete building generation.', $generationId));
      }
      $activeGeneration = $this->getActiveGeneration();
      if ($activeGeneration === $generationId) {
        throw new \LogicException('The active Content Index generation cannot be discarded.');
      }
      $updated = $this->database->update('aculta_index_generation')
        ->fields(['status' => 'discarding', 'updated' => $this->time->getRequestTime()])
        ->condition('generation_id', $generationId)
        ->condition('status', 'building')
        ->execute();
      if ($updated !== 1) {
        throw new \RuntimeException(sprintf('Generation %s changed while discard was starting.', $generationId));
      }
      unset($transaction);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Removes a discarding generation's derived rows with a bounded batch.
   *
   * @return array{relations_removed: int, entries_removed: int, complete: bool}
   *   Number of rows removed and whether the generation record was removed.
   */
  public function discardBatch(string $generationId, int $limit = 500): array {
    if ($limit < 1 || $limit > 5000) {
      throw new \InvalidArgumentException('Generation cleanup limit must be between 1 and 5000.');
    }
    $transaction = $this->database->startTransaction();
    try {
      $generation = $this->database->select('aculta_index_generation', 'generation')
        ->fields('generation', ['status'])
        ->condition('generation_id', $generationId)
        ->forUpdate()
        ->execute()
        ->fetchAssoc();
      if (!$generation || $generation['status'] !== 'discarding') {
        throw new \InvalidArgumentException(sprintf('Generation %s is not marked for discard.', $generationId));
      }

      $relationIds = $this->database->select('aculta_index_relation', 'relation')
        ->fields('relation', ['relation_id'])
        ->condition('generation_id', $generationId)
        ->orderBy('relation_id')
        ->range(0, $limit)
        ->execute()
        ->fetchCol();
      $relationsRemoved = $relationIds === [] ? 0 : $this->database->delete('aculta_index_relation')
        ->condition('relation_id', $relationIds, 'IN')
        ->condition('generation_id', $generationId)
        ->execute();

      $entryIds = $this->database->select('aculta_index_entry', 'entry')
        ->fields('entry', ['entry_id'])
        ->condition('generation_id', $generationId)
        ->orderBy('entry_id')
        ->range(0, $limit)
        ->execute()
        ->fetchCol();
      $entriesRemoved = $entryIds === [] ? 0 : $this->database->delete('aculta_index_entry')
        ->condition('entry_id', $entryIds, 'IN')
        ->condition('generation_id', $generationId)
        ->execute();

      $hasRelations = $this->database->select('aculta_index_relation', 'relation')
        ->fields('relation', ['relation_id'])
        ->condition('generation_id', $generationId)
        ->range(0, 1)
        ->execute()
        ->fetchField() !== FALSE;
      $hasEntries = $this->database->select('aculta_index_entry', 'entry')
        ->fields('entry', ['entry_id'])
        ->condition('generation_id', $generationId)
        ->range(0, 1)
        ->execute()
        ->fetchField() !== FALSE;
      $complete = !$hasRelations && !$hasEntries;
      if ($complete) {
        $this->database->delete('aculta_index_cursor')
          ->condition('generation_id', $generationId)
          ->execute();
        $this->database->delete('aculta_index_generation')
          ->condition('generation_id', $generationId)
          ->condition('status', 'discarding')
          ->execute();
      }
      unset($transaction);
      return [
        'relations_removed' => $relationsRemoved,
        'entries_removed' => $entriesRemoved,
        'complete' => $complete,
      ];
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Returns the provider inventory fingerprint of a building generation.
   */
  public function getProviderFingerprint(string $generationId): string {
    $fingerprint = $this->database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['provider_fingerprint'])
      ->condition('generation_id', $generationId)
      ->condition('status', 'building')
      ->execute()
      ->fetchField();
    if ($fingerprint === FALSE) {
      throw new \InvalidArgumentException(sprintf('Generation %s is missing or is not building.', $generationId));
    }
    return (string) $fingerprint;
  }

}
