<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Rebuild;

use Drupal\aculta_index\Generation\ContentIndexGenerationManager;
use Drupal\aculta_index\Integrity\ContentIndexIntegrityChecker;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Storage\ContentIndexWriter;

/**
 * Coordinates bounded provider batches into a building generation.
 */
final class ContentIndexRebuilder {

  /**
   * Maximum batch bound supported by the underlying entity enumerator.
   */
  private const MAX_BATCH_SIZE = 500;

  public function __construct(
    private readonly ContentIndexGenerationManager $generations,
    private readonly ContentIndexProviderRegistry $providers,
    private readonly ContentIndexEntityEnumerator $enumerator,
    private readonly ContentIndexRebuildCheckpointManager $checkpoints,
    private readonly ContentIndexWriter $writer,
    private readonly ContentIndexIntegrityChecker $integrityChecker,
  ) {}

  /**
   * Starts a new, empty generation for a future backfill.
   */
  public function start(): string {
    return $this->generations->start($this->providers->fingerprint());
  }

  /**
   * Writes and checkpoints one bounded keyset batch.
   *
   * A batch's projections are committed before its cursor advances. Replaying
   * after a crash is safe because the writer is idempotent and protects newer
   * incremental source sequences.
   *
   * @return array{generation_id: string, provider_id: string|null, entity_type: string|null, projected: int, complete: bool, cursor_entity_id: string}
   *   Batch outcome. `complete` means every provider/entity-type tuple has
   *   reached its end; validation and activation remain explicit operations.
   */
  public function processNextBatch(string $generationId, int $limit = 100): array {
    if ($limit < 1 || $limit > self::MAX_BATCH_SIZE) {
      throw new \InvalidArgumentException(sprintf('Content Index rebuild batch size must be between 1 and %d.', self::MAX_BATCH_SIZE));
    }
    $checkpoint = $this->checkpoints->load($generationId);
    if (!hash_equals($this->generations->getProviderFingerprint($generationId), $this->providers->fingerprint())) {
      throw new \UnexpectedValueException('The provider inventory changed during an ACULTA Content Index rebuild; restart or repair the generation.');
    }
    $tuples = $this->tuples();
    if ($tuples === []) {
      $this->generations->markBackfillCompleted($generationId, $checkpoint['revision']);
      return [
        'generation_id' => $generationId,
        'provider_id' => NULL,
        'entity_type' => NULL,
        'projected' => 0,
        'complete' => TRUE,
        'cursor_entity_id' => '',
      ];
    }

    $index = $this->findTupleIndex($tuples, $checkpoint);
    $tuple = $tuples[$index];
    $after = $checkpoint['provider'] === '' ? NULL : ($checkpoint['entity_id'] !== '' ? $checkpoint['entity_id'] : NULL);
    $batch = $this->enumerator->nextBatch($tuple['provider_id'], $tuple['entity_type'], $after, $limit);
    $projected = count($batch['projections']);
    $this->writer->writeBatch($generationId, $tuple['provider_id'], $batch['projections'], 0);

    if (!$batch['exhausted']) {
      $cursor = (string) $batch['next_cursor'];
      $this->checkpoints->advance($generationId, $tuple['provider_id'], $tuple['entity_type'], $cursor, $checkpoint['revision']);
      return $this->result($generationId, $tuple, $projected, FALSE, $cursor);
    }

    $nextIndex = $index + 1;
    if (isset($tuples[$nextIndex])) {
      $next = $tuples[$nextIndex];
      $this->checkpoints->advance($generationId, $next['provider_id'], $next['entity_type'], '', $checkpoint['revision']);
      return $this->result($generationId, $tuple, $projected, FALSE, (string) ($batch['next_cursor'] ?? ''));
    }

    $cursor = (string) ($batch['next_cursor'] ?? $checkpoint['entity_id']);
    $this->generations->markBackfillCompleted($generationId, $checkpoint['revision']);
    return $this->result($generationId, $tuple, $projected, TRUE, $cursor);
  }

  /**
   * Validates catalog integrity and promotes a completed build for activation.
   *
   * @return array
   *   Integrity metrics for the validation record; content metadata is omitted.
   */
  public function validate(string $generationId): array {
    if (!hash_equals($this->generations->getProviderFingerprint($generationId), $this->providers->fingerprint())) {
      throw new \UnexpectedValueException('The provider inventory changed before an ACULTA Content Index generation could be validated.');
    }
    $integrity = $this->integrityChecker->check($generationId);
    if (!$integrity['valid']) {
      throw new \UnexpectedValueException('The ACULTA Content Index catalog failed integrity validation.');
    }
    $this->generations->markValidated($generationId);
    return $integrity + ['generation_id' => $generationId, 'validated' => TRUE];
  }

  /**
   * Builds the stable ordering of provider/entity-type work tuples.
   *
   * @return list<array{provider_id: string, entity_type: string}>
   *   Ordered tuples declared by active providers.
   */
  private function tuples(): array {
    $tuples = [];
    $providers = $this->providers->all();
    ksort($providers);
    foreach ($providers as $providerId => $provider) {
      $entityTypes = $provider->getSupportedEntityTypes();
      ksort($entityTypes);
      foreach (array_keys($entityTypes) as $entityTypeId) {
        $tuples[] = ['provider_id' => $providerId, 'entity_type' => $entityTypeId];
      }
    }
    return $tuples;
  }

  /**
   * Finds the current tuple in the stable provider/type ordering.
   *
   * @param list<array{provider_id: string, entity_type: string}> $tuples
   *   Declared provider tuples.
   * @param array{provider: string, entity_type: string, entity_id: string, revision: int} $checkpoint
   *   Persisted generation cursor.
   */
  private function findTupleIndex(array $tuples, array $checkpoint): int {
    if ($checkpoint['provider'] === '') {
      return 0;
    }
    foreach ($tuples as $index => $tuple) {
      if ($tuple['provider_id'] === $checkpoint['provider'] && $tuple['entity_type'] === $checkpoint['entity_type']) {
        return $index;
      }
    }
    throw new \UnexpectedValueException('The provider set changed during an ACULTA Content Index rebuild; restart or repair the generation.');
  }

  /**
   * Builds a concise result for the just-processed tuple.
   *
   * @param string $generationId
   *   Building generation identifier.
   * @param array{provider_id: string, entity_type: string} $tuple
   *   Current provider/type work tuple.
   * @param int $projected
   *   Number of projections sent to the writer.
   * @param bool $complete
   *   Whether all provider/type tuples reached their end.
   * @param string $cursor
   *   Last source entity ID processed in the current tuple.
   *
   * @return array{generation_id: string, provider_id: string|null, entity_type: string|null, projected: int, complete: bool, cursor_entity_id: string}
   *   Batch outcome.
   */
  private function result(string $generationId, array $tuple, int $projected, bool $complete, string $cursor): array {
    return [
      'generation_id' => $generationId,
      'provider_id' => $tuple['provider_id'],
      'entity_type' => $tuple['entity_type'],
      'projected' => $projected,
      'complete' => $complete,
      'cursor_entity_id' => $cursor,
    ];
  }

}
