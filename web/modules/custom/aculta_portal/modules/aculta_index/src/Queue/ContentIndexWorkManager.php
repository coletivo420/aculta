<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Queue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
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

}
