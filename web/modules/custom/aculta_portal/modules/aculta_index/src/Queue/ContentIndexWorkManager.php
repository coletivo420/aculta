<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Queue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\Core\Queue\QueueFactory;
use Psr\Log\LoggerInterface;

/**
 * Deduplicates source changes and tracks Core Queue retry/dead-letter state.
 */
final class ContentIndexWorkManager {

  /**
   * Queue worker plugin ID.
   */
  public const QUEUE_ID = 'aculta_index_update';

  /**
   * Maximum retry attempts before marking work dead.
   */
  private const MAX_ATTEMPTS = 5;

  /**
   * Initial exponential retry delay in seconds.
   */
  private const INITIAL_BACKOFF = 30;

  /**
   * Maximum retry delay in seconds.
   */
  private const MAX_BACKOFF = 21600;

  /**
   * Time before an unclaimed queued record is dispatched again.
   */
  private const STUCK_QUEUE_AGE = 300;

  public function __construct(
    private readonly Connection $database,
    private readonly QueueFactory $queues,
    private readonly TimeInterface $time,
    private readonly ContentIndexProviderRegistry $providers,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Records the newest desired operation and returns its per-identity sequence.
   *
   * @param string $providerId
   *   Stable provider machine name.
   * @param string $entityTypeId
   *   Source entity type.
   * @param string $entityId
   *   Source entity ID.
   * @param string $langcode
   *   Source translation language.
   * @param string $operation
   *   Either `project` or `delete`.
   */
  public function schedule(
    string $providerId,
    string $entityTypeId,
    string $entityId,
    string $langcode,
    string $operation = 'project',
  ): int {
    if (!in_array($operation, ['project', 'delete'], TRUE)) {
      throw new \InvalidArgumentException('Content Index work operation must be project or delete.');
    }

    for ($attempt = 0; $attempt < 3; $attempt++) {
      $transaction = $this->database->startTransaction();
      try {
        $this->lockActivationFenceIfNecessary();
        $existing = $this->database->select('aculta_index_work', 'work')
          ->fields('work', ['work_id', 'sequence', 'status'])
          ->condition('provider_id', $providerId)
          ->condition('entity_type', $entityTypeId)
          ->condition('entity_id', $entityId)
          ->condition('langcode', $langcode)
          ->forUpdate()
          ->execute()
          ->fetchAssoc();
        $now = $this->time->getRequestTime();

        if ($existing) {
          $workId = (int) $existing['work_id'];
          $sequence = (int) $existing['sequence'] + 1;
          $needsDispatch = $existing['status'] !== 'queued';
          $updated = $this->database->update('aculta_index_work')
            ->fields([
              'sequence' => $sequence,
              'operation' => $operation,
              'status' => 'queued',
              'attempts' => 0,
              'available_at' => 0,
              'last_error_code' => '',
              'updated' => $now,
            ])
            ->condition('work_id', $workId)
            ->condition('sequence', (int) $existing['sequence'])
            ->execute();
          if ($updated !== 1) {
            $transaction->rollBack();
            continue;
          }
        }
        else {
          $sequence = 1;
          $needsDispatch = TRUE;
          $workId = (int) $this->database->insert('aculta_index_work')
            ->fields([
              'provider_id' => $providerId,
              'entity_type' => $entityTypeId,
              'entity_id' => $entityId,
              'langcode' => $langcode,
              'sequence' => $sequence,
              'operation' => $operation,
              'status' => 'queued',
              'created' => $now,
              'updated' => $now,
            ])
            ->execute();
        }

        unset($transaction);
        if ($needsDispatch) {
          $this->dispatch($workId);
        }
        return $sequence;
      }
      catch (IntegrityConstraintViolationException $exception) {
        $transaction->rollBack();
        if ($attempt === 2) {
          throw $exception;
        }
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
    }

    throw new \RuntimeException('Could not reserve a Content Index work sequence after concurrent changes.');
  }

  /**
   * Returns bounded work counts required before a generation can be activated.
   *
   * @param list<string> $providerIds
   *   Currently registered providers included in the rebuild inventory.
   *
   * @return array{ready: bool, queued: int, processing: int, retry: int, dead: int}
   *   Gate counts. A dead-letter blocks activation until retried and completed.
   */
  public function getActivationGateState(array $providerIds): array {
    $state = ['ready' => TRUE, 'queued' => 0, 'processing' => 0, 'retry' => 0, 'dead' => 0];
    if ($providerIds === []) {
      return $state;
    }
    $rows = $this->database->select('aculta_index_work', 'work')
      ->fields('work', ['status'])
      ->condition('provider_id', $providerIds, 'IN')
      ->condition('status', ['queued', 'processing', 'retry', 'dead'], 'IN')
      ->groupBy('status');
    $rows->addExpression('COUNT(*)', 'count');
    foreach ($rows->execute() as $row) {
      $state[$row->status] = (int) $row->count;
    }
    $state['ready'] = array_sum(array_slice($state, 1)) === 0;
    return $state;
  }

  /**
   * Resets a dead-letter record for an explicit operational retry.
   */
  public function retryDead(int $workId): bool {
    if ($workId < 1) {
      throw new \InvalidArgumentException('Content Index work ID must be positive.');
    }
    $transaction = $this->database->startTransaction();
    try {
      $this->lockActivationFenceIfNecessary();
      $work = $this->database->select('aculta_index_work', 'work')
        ->fields('work', ['provider_id'])
        ->condition('work_id', $workId)
        ->condition('status', 'dead')
        ->forUpdate()
        ->execute()
        ->fetchAssoc();
      if (!$work || !$this->providers->has((string) $work['provider_id'])) {
        unset($transaction);
        return FALSE;
      }
      $updated = $this->database->update('aculta_index_work')
        ->fields([
          'status' => 'queued',
          'attempts' => 0,
          'available_at' => 0,
          'last_error_code' => '',
          'updated' => $this->time->getRequestTime(),
        ])
        ->condition('work_id', $workId)
        ->condition('status', 'dead')
        ->execute();
      unset($transaction);
      if ($updated !== 1) {
        return FALSE;
      }
      return $this->dispatch($workId);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Lists a small page of dead-letter identities for internal repair tooling.
   *
   * @return list<array{work_id: int, provider_id: string, entity_type: string, entity_id: string, langcode: string, sequence: int, attempts: int, last_error_code: string, updated: int}>
   *   Operational identifiers and normalized error codes. Callers must restrict
   *   this data to authorized administrators.
   */
  public function listDead(int $limit = 50): array {
    if ($limit < 1 || $limit > 100) {
      throw new \InvalidArgumentException('Dead-letter page size must be between 1 and 100.');
    }
    $rows = $this->database->select('aculta_index_work', 'work')
      ->fields('work', [
        'work_id',
        'provider_id',
        'entity_type',
        'entity_id',
        'langcode',
        'sequence',
        'attempts',
        'last_error_code',
        'updated',
      ])
      ->condition('status', 'dead')
      ->orderBy('updated')
      ->orderBy('work_id')
      ->range(0, $limit)
      ->execute();
    $dead = [];
    foreach ($rows as $row) {
      $dead[] = [
        'work_id' => (int) $row->work_id,
        'provider_id' => (string) $row->provider_id,
        'entity_type' => (string) $row->entity_type,
        'entity_id' => (string) $row->entity_id,
        'langcode' => (string) $row->langcode,
        'sequence' => (int) $row->sequence,
        'attempts' => (int) $row->attempts,
        'last_error_code' => (string) $row->last_error_code,
        'updated' => (int) $row->updated,
      ];
    }
    return $dead;
  }

  /**
   * Claims a queued item once and returns its latest desired operation.
   *
   * @return array{work_id: int, provider_id: string, entity_type: string, entity_id: string, langcode: string, sequence: int, operation: string}|null
   *   Work claim, or NULL when this queue item is stale or already claimed.
   */
  public function claim(int $workId): ?array {
    $transaction = $this->database->startTransaction();
    try {
      $work = $this->database->select('aculta_index_work', 'work')
        ->fields('work', ['provider_id', 'entity_type', 'entity_id', 'langcode', 'sequence', 'operation'])
        ->condition('work_id', $workId)
        ->condition('status', 'queued')
        ->forUpdate()
        ->execute()
        ->fetchAssoc();
      if (!$work) {
        unset($transaction);
        return NULL;
      }

      $claimed = $this->database->update('aculta_index_work')
        ->fields([
          'status' => 'processing',
          'updated' => $this->time->getRequestTime(),
        ])
        ->condition('work_id', $workId)
        ->condition('sequence', (int) $work['sequence'])
        ->condition('status', 'queued')
        ->execute();
      if ($claimed !== 1) {
        $transaction->rollBack();
        return NULL;
      }

      unset($transaction);
      return [
        'work_id' => $workId,
        'provider_id' => (string) $work['provider_id'],
        'entity_type' => (string) $work['entity_type'],
        'entity_id' => (string) $work['entity_id'],
        'langcode' => (string) $work['langcode'],
        'sequence' => (int) $work['sequence'],
        'operation' => (string) $work['operation'],
      ];
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Marks work done only if the same sequence is still being processed.
   */
  public function complete(int $workId, int $sequence): bool {
    return $this->database->update('aculta_index_work')
      ->fields([
        'status' => 'done',
        'attempts' => 0,
        'available_at' => 0,
        'last_error_code' => '',
        'updated' => $this->time->getRequestTime(),
      ])
      ->condition('work_id', $workId)
      ->condition('sequence', $sequence)
      ->condition('status', 'processing')
      ->execute() === 1;
  }

  /**
   * Records a failed attempt with backoff or dead-letter status.
   */
  public function fail(int $workId, int $sequence, string $errorCode): bool {
    $work = $this->database->select('aculta_index_work', 'work')
      ->fields('work', ['attempts'])
      ->condition('work_id', $workId)
      ->condition('sequence', $sequence)
      ->condition('status', 'processing')
      ->execute()
      ->fetchAssoc();
    if (!$work) {
      return FALSE;
    }

    $attempts = (int) $work['attempts'] + 1;
    $dead = $attempts >= self::MAX_ATTEMPTS;
    $delay = min(self::INITIAL_BACKOFF * (2 ** ($attempts - 1)), self::MAX_BACKOFF);
    $normalizedCode = preg_match('/^[a-z][a-z0-9_]{0,63}$/', $errorCode) === 1 ? $errorCode : 'worker_failure';
    return $this->database->update('aculta_index_work')
      ->fields([
        'status' => $dead ? 'dead' : 'retry',
        'attempts' => $attempts,
        'available_at' => $dead ? 0 : $this->time->getRequestTime() + $delay,
        'last_error_code' => $normalizedCode,
        'updated' => $this->time->getRequestTime(),
      ])
      ->condition('work_id', $workId)
      ->condition('sequence', $sequence)
      ->condition('status', 'processing')
      ->execute() === 1;
  }

  /**
   * Re-enqueues due retries and queued records whose Queue item may be lost.
   *
   * @return int
   *   Number of Queue items recreated.
   */
  public function recover(int $limit = 100): int {
    if ($limit < 1 || $limit > 500) {
      throw new \InvalidArgumentException('Recovery limit must be between 1 and 500.');
    }
    $now = $this->time->getRequestTime();
    $dispatched = 0;

    $retryIds = $this->database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id', 'sequence'])
      ->condition('status', 'retry')
      ->condition('available_at', $now, '<=')
      ->orderBy('available_at')
      ->range(0, $limit)
      ->execute()
      ->fetchAllKeyed(0, 1);
    foreach ($retryIds as $workId => $sequence) {
      $updated = $this->database->update('aculta_index_work')
        ->fields(['status' => 'queued', 'available_at' => 0, 'updated' => $now])
        ->condition('work_id', $workId)
        ->condition('sequence', $sequence)
        ->condition('status', 'retry')
        ->condition('available_at', $now, '<=')
        ->execute();
      if ($updated === 1 && $this->dispatch((int) $workId)) {
        $dispatched++;
      }
    }

    $remaining = max(0, $limit - $dispatched);
    if ($remaining > 0) {
      $cutoff = $now - self::STUCK_QUEUE_AGE;
      $queuedIds = $this->database->select('aculta_index_work', 'work')
        ->fields('work', ['work_id', 'updated'])
        ->condition('status', 'queued')
        ->condition('updated', $cutoff, '<=')
        ->orderBy('updated')
        ->range(0, $remaining)
        ->execute()
        ->fetchAllKeyed(0, 1);
      foreach ($queuedIds as $workId => $updatedAt) {
        $claimed = $this->database->update('aculta_index_work')
          ->fields(['updated' => $now])
          ->condition('work_id', $workId)
          ->condition('status', 'queued')
          ->condition('updated', $updatedAt)
          ->execute();
        if ($claimed === 1 && $this->dispatch((int) $workId)) {
          $dispatched++;
        }
      }
    }

    $remaining = max(0, $limit - $dispatched);
    if ($remaining > 0) {
      $staleProcessing = $this->database->select('aculta_index_work', 'work')
        ->fields('work', ['work_id', 'sequence', 'attempts', 'updated'])
        ->condition('status', 'processing')
        ->condition('updated', $now - self::STUCK_QUEUE_AGE, '<=')
        ->orderBy('updated')
        ->range(0, $remaining)
        ->execute();
      foreach ($staleProcessing as $work) {
        $attempts = (int) $work->attempts + 1;
        $dead = $attempts >= self::MAX_ATTEMPTS;
        $delay = min(self::INITIAL_BACKOFF * (2 ** ($attempts - 1)), self::MAX_BACKOFF);
        $this->database->update('aculta_index_work')
          ->fields([
            'status' => $dead ? 'dead' : 'retry',
            'attempts' => $attempts,
            'available_at' => $dead ? 0 : $now + $delay,
            'last_error_code' => 'worker_timeout',
            'updated' => $now,
          ])
          ->condition('work_id', $work->work_id)
          ->condition('sequence', $work->sequence)
          ->condition('status', 'processing')
          ->condition('updated', $work->updated)
          ->execute();
      }
    }

    return $dispatched;
  }

  /**
   * Returns the current durable work status for operations and tests.
   *
   * @return array<string, int|string>|null
   *   Minimal work state, or NULL when the work identity has not been seen.
   */
  public function getStatus(string $providerId, string $entityTypeId, string $entityId, string $langcode): ?array {
    $result = $this->database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id', 'sequence', 'operation', 'status', 'attempts', 'available_at', 'last_error_code'])
      ->condition('provider_id', $providerId)
      ->condition('entity_type', $entityTypeId)
      ->condition('entity_id', $entityId)
      ->condition('langcode', $langcode)
      ->execute()
      ->fetchAssoc();
    if (!$result) {
      return NULL;
    }
    $result['work_id'] = (int) $result['work_id'];
    $result['sequence'] = (int) $result['sequence'];
    $result['attempts'] = (int) $result['attempts'];
    $result['available_at'] = (int) $result['available_at'];
    return $result;
  }

  /**
   * Adds the minimum stable identifier to the Core Queue.
   */
  private function dispatch(int $workId): bool {
    try {
      $created = $this->queues->get(self::QUEUE_ID)->createItem(['work_id' => $workId]);
      return $created !== FALSE;
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not enqueue ACULTA Content Index work; it remains recoverable. Error class: @class', [
        '@class' => $exception::class,
      ]);
      return FALSE;
    }
  }

  /**
   * Serializes scheduling with rebuild and retained-generation activation.
   */
  private function lockActivationFenceIfNecessary(): void {
    $fenced = $this->database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['generation_id'])
      ->condition('status', ['building', 'validated', 'retained'], 'IN')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($fenced === FALSE) {
      return;
    }
    $state = $this->database->select('aculta_index_state', 'state')
      ->fields('state', ['state_id'])
      ->condition('state_id', 1)
      ->forUpdate()
      ->execute()
      ->fetchField();
    if ($state === FALSE) {
      throw new \LogicException('The ACULTA Content Index activation fence is missing.');
    }
  }

}
