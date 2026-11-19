<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Queue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Database\Transaction;
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
        $existingQuery = $this->database->select('aculta_index_work', 'work');
        $existingQuery->fields('work', ['work_id', 'sequence', 'status']);
        $existingQuery->condition('provider_id', $providerId);
        $existingQuery->condition('entity_type', $entityTypeId);
        $existingQuery->condition('entity_id', $entityId);
        $existingQuery->condition('langcode', $langcode);
        $existingQuery->forUpdate();
        $existing = self::fetchAssoc($existingQuery->execute());
        $now = $this->time->getRequestTime();

        if ($existing) {
          $workId = self::integerValue($existing['work_id'] ?? NULL);
          $existingSequence = self::integerValue($existing['sequence'] ?? NULL);
          $sequence = $existingSequence + 1;
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
            ->condition('sequence', $existingSequence)
            ->execute();
          if ($updated !== 1) {
            $transaction->rollBack();
            $transaction = NULL;
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

        $transaction->commitOrRelease();
        $transaction = NULL;
        if ($needsDispatch) {
          $this->dispatch($workId);
        }
        return $sequence;
      }
      catch (IntegrityConstraintViolationException $exception) {
        if ($transaction instanceof Transaction) {
          $transaction->rollBack();
          $transaction = NULL;
        }
        if ($attempt === 2) {
          throw $exception;
        }
      }
      catch (\Throwable $exception) {
        if ($transaction instanceof Transaction) {
          $transaction->rollBack();
          $transaction = NULL;
        }
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
    $result = $rows->execute();
    if (!$result instanceof StatementInterface) {
      throw new \RuntimeException('Could not read Content Index queue state.');
    }
    foreach ($result as $row) {
      if (!is_object($row) || !isset($row->status, $row->count) || !is_string($row->status) || !array_key_exists($row->status, $state) || $row->status === 'ready') {
        throw new \UnexpectedValueException('Content Index queue state query returned a malformed row.');
      }
      $state[$row->status] = self::requiredInteger($row->count);
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
      $workQuery = $this->database->select('aculta_index_work', 'work');
      $workQuery->fields('work', ['provider_id']);
      $workQuery->condition('work_id', $workId);
      $workQuery->condition('status', 'dead');
      $workQuery->forUpdate();
      $work = self::fetchAssoc($workQuery->execute());
      $providerId = self::stringValue($work['provider_id'] ?? NULL);
      if (!$work || !$this->providers->has($providerId)) {
        $transaction->commitOrRelease();
        $transaction = NULL;
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
      $transaction->commitOrRelease();
      $transaction = NULL;
      if ($updated !== 1) {
        return FALSE;
      }
      return $this->dispatch($workId);
    }
    catch (\Throwable $exception) {
      if ($transaction instanceof Transaction) {
        $transaction->rollBack();
        $transaction = NULL;
      }
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
    if (!$rows instanceof StatementInterface) {
      return $dead;
    }
    foreach ($rows as $row) {
      if (!is_object($row) || !isset($row->work_id, $row->provider_id, $row->entity_type, $row->entity_id, $row->langcode, $row->sequence, $row->attempts, $row->last_error_code, $row->updated)) {
        continue;
      }
      $dead[] = [
        'work_id' => self::integerValue($row->work_id),
        'provider_id' => self::stringValue($row->provider_id),
        'entity_type' => self::stringValue($row->entity_type),
        'entity_id' => self::stringValue($row->entity_id),
        'langcode' => self::stringValue($row->langcode),
        'sequence' => self::integerValue($row->sequence),
        'attempts' => self::integerValue($row->attempts),
        'last_error_code' => self::stringValue($row->last_error_code),
        'updated' => self::integerValue($row->updated),
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
      $workQuery = $this->database->select('aculta_index_work', 'work');
      $workQuery->fields('work', ['provider_id', 'entity_type', 'entity_id', 'langcode', 'sequence', 'operation']);
      $workQuery->condition('work_id', $workId);
      $workQuery->condition('status', 'queued');
      $workQuery->forUpdate();
      $work = self::fetchAssoc($workQuery->execute());
      if (!$work) {
        $transaction->commitOrRelease();
        $transaction = NULL;
        return NULL;
      }

      $claimed = $this->database->update('aculta_index_work')
        ->fields([
          'status' => 'processing',
          'updated' => $this->time->getRequestTime(),
        ])
        ->condition('work_id', $workId)
        ->condition('sequence', self::integerValue($work['sequence'] ?? NULL))
        ->condition('status', 'queued')
        ->execute();
      if ($claimed !== 1) {
        $transaction->rollBack();
        $transaction = NULL;
        return NULL;
      }

      $transaction->commitOrRelease();
      $transaction = NULL;
      return [
        'work_id' => $workId,
        'provider_id' => self::stringValue($work['provider_id'] ?? NULL),
        'entity_type' => self::stringValue($work['entity_type'] ?? NULL),
        'entity_id' => self::stringValue($work['entity_id'] ?? NULL),
        'langcode' => self::stringValue($work['langcode'] ?? NULL),
        'sequence' => self::integerValue($work['sequence'] ?? NULL),
        'operation' => self::stringValue($work['operation'] ?? NULL),
      ];
    }
    catch (\Throwable $exception) {
      if ($transaction instanceof Transaction) {
        $transaction->rollBack();
        $transaction = NULL;
      }
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
    $workQuery = $this->database->select('aculta_index_work', 'work');
    $workQuery->fields('work', ['attempts']);
    $workQuery->condition('work_id', $workId);
    $workQuery->condition('sequence', $sequence);
    $workQuery->condition('status', 'processing');
    $work = self::fetchAssoc($workQuery->execute());
    if (!$work) {
      return FALSE;
    }

    $attempts = self::integerValue($work['attempts'] ?? NULL) + 1;
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

    $retryQuery = $this->database->select('aculta_index_work', 'work');
    $retryQuery->fields('work', ['work_id', 'sequence']);
    $retryQuery->condition('status', 'retry');
    $retryQuery->condition('available_at', $now, '<=');
    $retryQuery->orderBy('available_at');
    $retryQuery->range(0, $limit);
    $retryResult = $retryQuery->execute();
    $retryIds = $retryResult instanceof StatementInterface ? $retryResult->fetchAllKeyed(0, 1) : [];
    foreach ($retryIds as $workId => $sequence) {
      $retryId = self::storageId($workId);
      $retrySequence = self::storageId($sequence);
      if ($retryId === NULL || $retrySequence === NULL) {
        continue;
      }
      $updated = $this->database->update('aculta_index_work')
        ->fields(['status' => 'queued', 'available_at' => 0, 'updated' => $now])
        ->condition('work_id', $retryId)
        ->condition('sequence', $retrySequence)
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
      $queuedQuery = $this->database->select('aculta_index_work', 'work');
      $queuedQuery->fields('work', ['work_id', 'updated']);
      $queuedQuery->condition('status', 'queued');
      $queuedQuery->condition('updated', $cutoff, '<=');
      $queuedQuery->orderBy('updated');
      $queuedQuery->range(0, $remaining);
      $queuedResult = $queuedQuery->execute();
      $queuedIds = $queuedResult instanceof StatementInterface ? $queuedResult->fetchAllKeyed(0, 1) : [];
      foreach ($queuedIds as $workId => $updatedAt) {
        $queuedId = self::storageId($workId);
        $queuedUpdated = self::storageId($updatedAt);
        if ($queuedId === NULL || $queuedUpdated === NULL) {
          continue;
        }
        $claimed = $this->database->update('aculta_index_work')
          ->fields(['updated' => $now])
          ->condition('work_id', $queuedId)
          ->condition('status', 'queued')
          ->condition('updated', $queuedUpdated)
          ->execute();
        if ($claimed === 1 && $this->dispatch(self::integerValue($queuedId))) {
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
      if (!$staleProcessing instanceof StatementInterface) {
        return $dispatched;
      }
      foreach ($staleProcessing as $work) {
        if (!is_object($work) || !isset($work->attempts, $work->work_id, $work->sequence, $work->updated)) {
          continue;
        }
        $workId = self::storageId($work->work_id);
        $sequence = self::storageId($work->sequence);
        $updatedAt = self::storageId($work->updated);
        if ($workId === NULL || $sequence === NULL || $updatedAt === NULL) {
          continue;
        }
        $attempts = self::integerValue($work->attempts) + 1;
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
          ->condition('work_id', $workId)
          ->condition('sequence', $sequence)
          ->condition('status', 'processing')
          ->condition('updated', $updatedAt)
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
    $statusQuery = $this->database->select('aculta_index_work', 'work');
    $statusQuery->fields('work', ['work_id', 'sequence', 'operation', 'status', 'attempts', 'available_at', 'last_error_code']);
    $statusQuery->condition('provider_id', $providerId);
    $statusQuery->condition('entity_type', $entityTypeId);
    $statusQuery->condition('entity_id', $entityId);
    $statusQuery->condition('langcode', $langcode);
    $result = self::fetchAssoc($statusQuery->execute());
    if (!$result) {
      return NULL;
    }
    foreach (['work_id', 'sequence', 'operation', 'status', 'attempts', 'available_at', 'last_error_code'] as $field) {
      if (!array_key_exists($field, $result)) {
        throw new \UnexpectedValueException(sprintf('Content Index work status is missing the %s field.', $field));
      }
    }
    return [
      'work_id' => self::requiredInteger($result['work_id']),
      'sequence' => self::requiredInteger($result['sequence']),
      'operation' => self::requiredString($result['operation']),
      'status' => self::requiredString($result['status']),
      'attempts' => self::requiredInteger($result['attempts']),
      'available_at' => self::requiredInteger($result['available_at']),
      'last_error_code' => self::requiredString($result['last_error_code']),
    ];
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
    $fenceQuery = $this->database->select('aculta_index_generation', 'generation');
    $fenceQuery->fields('generation', ['generation_id']);
    $fenceQuery->condition('status', ['building', 'validated', 'retained'], 'IN');
    $fenceQuery->range(0, 1);
    $fenced = $fenceQuery->execute();
    if (!$fenced instanceof StatementInterface) {
      throw new \RuntimeException('Could not read the ACULTA Content Index activation fence.');
    }
    $fence = $fenced->fetchField();
    if ($fence === FALSE) {
      return;
    }
    if (!is_int($fence) && !is_string($fence)) {
      throw new \UnexpectedValueException('The ACULTA Content Index activation fence returned an invalid generation ID.');
    }
    $stateQuery = $this->database->select('aculta_index_state', 'state');
    $stateQuery->fields('state', ['state_id']);
    $stateQuery->condition('state_id', 1);
    $stateQuery->forUpdate();
    $state = $stateQuery->execute();
    if (!$state instanceof StatementInterface) {
      throw new \RuntimeException('Could not read the ACULTA Content Index activation state.');
    }
    $stateId = $state->fetchField();
    if ($stateId === FALSE) {
      throw new \LogicException('The ACULTA Content Index activation fence is missing.');
    }
    if (!is_int($stateId) && !is_string($stateId)) {
      throw new \UnexpectedValueException('The ACULTA Content Index activation state returned an invalid ID.');
    }
  }

  /**
   * Fetches a database row as an associative array when the query is valid.
   *
   * @return array<mixed>|null
   *   The row, or NULL when the query is invalid or has no result.
   */
  private static function fetchAssoc(mixed $result): ?array {
    if (!$result instanceof StatementInterface) {
      return NULL;
    }
    $row = $result->fetchAssoc();
    return is_array($row) ? $row : NULL;
  }

  /**
   * Converts database values to integers without casting malformed data.
   */
  private static function integerValue(mixed $value): int {
    if (is_int($value)) {
      return $value;
    }
    if (is_string($value)) {
      $integer = filter_var($value, FILTER_VALIDATE_INT);
      return $integer === FALSE ? 0 : $integer;
    }
    return 0;
  }

  /**
   * Converts a required database integer or fails on malformed data.
   */
  private static function requiredInteger(mixed $value): int {
    if (is_int($value)) {
      return $value;
    }
    if (is_string($value)) {
      $integer = filter_var($value, FILTER_VALIDATE_INT);
      if ($integer !== FALSE) {
        return $integer;
      }
    }
    throw new \UnexpectedValueException('Content Index database query returned an invalid integer.');
  }

  /**
   * Converts a required database string or integer to text.
   */
  private static function requiredString(mixed $value): string {
    if (is_string($value) || is_int($value)) {
      return (string) $value;
    }
    throw new \UnexpectedValueException('Content Index database query returned an invalid string.');
  }

  /**
   * Narrows database IDs to values supported by query conditions.
   */
  private static function storageId(mixed $value): int|string|null {
    if (is_int($value)) {
      return $value;
    }
    return is_string($value) ? $value : NULL;
  }

  /**
   * Converts a database value to text without stringifying structures.
   */
  private static function stringValue(mixed $value): string {
    if (is_scalar($value)) {
      return (string) $value;
    }
    return $value instanceof \Stringable ? (string) $value : '';
  }

}
