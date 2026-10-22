<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Generation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;

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
  ) {}

  /**
   * Starts a rebuild generation and returns its opaque ID.
   */
  public function start(): string {
    $id = $this->uuid->generate();
    $now = $this->time->getRequestTime();
    $this->database->insert('aculta_index_generation')
      ->fields([
        'generation_id' => $id,
        'status' => 'building',
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
      ->execute();

    if ($count !== 1) {
      throw new \InvalidArgumentException(sprintf('Generation %s is missing or is not building.', $generationId));
    }
  }

  /**
   * Switches the active pointer to a validated or retained generation.
   */
  public function activate(string $generationId): void {
    $transaction = $this->database->startTransaction();
    try {
      $target = $this->database->select('aculta_index_generation', 'generation')
        ->fields('generation', ['status', 'validated'])
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
   * Returns generations that must receive live changes during a rebuild.
   *
   * @return list<string>
   *   Active and building generation IDs.
   */
  public function getWritableGenerations(): array {
    return array_map('strval', $this->database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['generation_id'])
      ->condition('status', ['active', 'building'], 'IN')
      ->orderBy('created')
      ->execute()
      ->fetchCol());
  }

}
