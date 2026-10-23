<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Storage;

use Drupal\aculta_index\Cache\ContentIndexCandidateCache;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;

/**
 * Persists allowlisted projections and provider-owned relations by generation.
 */
final class ContentIndexWriter {

  /**
   * Maximum attempts when a concurrent insert wins the unique identity race.
   */
  private const MAX_RETRIES = 3;

  /**
   * Maximum number of projections written in one transaction.
   */
  private const MAX_BATCH_SIZE = 500;

  public function __construct(
    private readonly Connection $database,
    private readonly ContentIndexProviderRegistry $providers,
    private readonly TimeInterface $time,
    private readonly ContentIndexCandidateCache $candidateCache,
  ) {}

  /**
   * Writes a projection unless its event sequence is older than stored data.
   *
   * @return bool
   *   TRUE when a row or relation changed; FALSE for an idempotent or stale
   *   job.
   */
  public function write(
    string $generationId,
    string $providerId,
    ContentIndexProjection $projection,
    int $sourceSequence = 0,
  ): bool {
    return $this->writeBatch($generationId, $providerId, [$projection], $sourceSequence) > 0;
  }

  /**
   * Writes a bounded set of projections atomically.
   *
   * The generation lock is held for the transaction. A changed batch advances
   * the candidate revision once and invalidates cached candidate pages once.
   * If a compare-and-swap conflict or identity race occurs, the whole batch is
   * rolled back and retried to keep its writes atomic.
   *
   * @param string $generationId
   *   Writable target generation.
   * @param string $providerId
   *   Provider that owns each projection.
   * @param list<\Drupal\aculta_portal\ContentIndex\ContentIndexProjection> $projections
   *   Projections to write. Empty batches are no-ops.
   * @param int $sourceSequence
   *   Monotonic source event sequence; defaults to rebuild/backfill sequence 0.
   *
   * @return int
   *   Number of identities that changed.
   */
  public function writeBatch(string $generationId, string $providerId, array $projections, int $sourceSequence = 0): int {
    if ($sourceSequence < 0) {
      throw new \InvalidArgumentException('Content Index source sequence must be non-negative.');
    }
    if (count($projections) > self::MAX_BATCH_SIZE) {
      throw new \InvalidArgumentException(sprintf('Content Index writer batches may contain at most %d projections.', self::MAX_BATCH_SIZE));
    }
    if ($projections === []) {
      return 0;
    }

    foreach ($projections as $projection) {
      if (!$projection instanceof ContentIndexProjection) {
        throw new \InvalidArgumentException('Content Index writer batches accept only ContentIndexProjection values.');
      }
      $this->assertProviderOwnsProjection($providerId, $projection);
    }

    for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
      $transaction = $this->database->startTransaction();
      try {
        $this->assertWritableGeneration($generationId);
        $changedCount = 0;
        $retryBatch = FALSE;
        foreach ($projections as $projection) {
          $result = $this->writeOnce($generationId, $providerId, $projection, $sourceSequence, TRUE);
          if ($result === NULL) {
            $retryBatch = TRUE;
            break;
          }
          $changedCount += (int) $result;
        }
        if ($retryBatch) {
          $transaction->rollBack();
          continue;
        }
        if ($changedCount > 0) {
          $this->incrementCandidateRevision($generationId);
        }
        unset($transaction);
        if ($changedCount > 0) {
          $this->candidateCache->invalidateGeneration($generationId);
        }
        return $changedCount;
      }
      catch (IntegrityConstraintViolationException $exception) {
        $transaction->rollBack();
        if ($attempt === self::MAX_RETRIES - 1) {
          throw $exception;
        }
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
    }

    throw new \RuntimeException('Content Index projection batch exceeded its concurrency retry limit.');
  }

  /**
   * Removes one projection and its outgoing relations if the event is current.
   */
  public function remove(
    string $generationId,
    string $providerId,
    string $entityTypeId,
    string $entityId,
    string $langcode,
    int $sourceSequence,
  ): bool {
    if ($sourceSequence < 0) {
      throw new \InvalidArgumentException('Content Index source sequence must be non-negative.');
    }
    $transaction = $this->database->startTransaction();
    try {
      $this->assertWritableGeneration($generationId);
      $deleted = $this->database->delete('aculta_index_entry')
        ->condition('generation_id', $generationId)
        ->condition('provider_id', $providerId)
        ->condition('entity_type', $entityTypeId)
        ->condition('entity_id', $entityId)
        ->condition('langcode', $langcode)
        ->condition('source_sequence', $sourceSequence, '<=')
        ->execute();
      if ($deleted !== 1) {
        unset($transaction);
        return FALSE;
      }

      $this->deleteRelations($generationId, $providerId, $entityTypeId, $entityId, $langcode);
      $this->incrementCandidateRevision($generationId);
      unset($transaction);
      $this->candidateCache->invalidateGeneration($generationId);
      return TRUE;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Performs a single optimistic write attempt.
   *
   * NULL indicates a compare-and-swap race and asks the caller to retry.
   */
  private function writeOnce(
    string $generationId,
    string $providerId,
    ContentIndexProjection $projection,
    int $sourceSequence,
    bool $generationLocked = FALSE,
  ): ?bool {
    if (!$generationLocked) {
      $this->assertWritableGeneration($generationId);
    }
    $identity = [
      'generation_id' => $generationId,
      'entity_type' => $projection->entityTypeId,
      'entity_id' => $projection->entityId,
      'langcode' => $projection->langcode,
    ];
    $providerVersion = $this->providers->get($providerId)->getProjectionVersion();
    $existing = $this->database->select('aculta_index_entry', 'entry')
      ->fields('entry', ['entry_id', 'provider_id', 'provider_version', 'changed', 'source_sequence', 'projection_hash'])
      ->condition('generation_id', $generationId)
      ->condition('entity_type', $projection->entityTypeId)
      ->condition('entity_id', $projection->entityId)
      ->condition('langcode', $projection->langcode)
      ->forUpdate()
      ->execute()
      ->fetchAssoc();

    $now = $this->time->getRequestTime();
    $hash = $this->projectionHash($providerId, $providerVersion, $projection);
    $fields = [
      'provider_id' => $providerId,
      'provider_version' => $providerVersion,
      'bundle' => $projection->bundle,
      'uuid' => $projection->uuid,
      'label' => $projection->label,
      'domain_id' => $projection->domainId ?? '',
      'published' => $projection->published === NULL ? NULL : (int) $projection->published,
      'created' => $projection->created,
      'changed' => $projection->changed,
      'published_at' => $projection->publishedAt ?? 0,
      'projection_hash' => $hash,
      'source_sequence' => $sourceSequence,
      'updated' => $now,
    ];

    if (!$existing) {
      $this->database->insert('aculta_index_entry')
        ->fields($identity + $fields)
        ->execute();
      $this->replaceRelations($generationId, $providerId, $projection, $now);
      return TRUE;
    }

    if ($existing['provider_id'] !== $providerId) {
      throw new \LogicException('The stored entity identity is owned by a different Content Index provider.');
    }
    $existingSequence = (int) $existing['source_sequence'];
    $existingChanged = (int) $existing['changed'];
    $existingProviderVersion = (int) $existing['provider_version'];
    if ($sourceSequence < $existingSequence
      || ($sourceSequence === $existingSequence && $projection->changed < $existingChanged)) {
      return FALSE;
    }
    if ($existingProviderVersion === $providerVersion
      && $sourceSequence === $existingSequence
      && $projection->changed === $existingChanged
      && $existing['projection_hash'] !== $hash) {
      throw new \LogicException('A Content Index source sequence produced conflicting projections.');
    }
    $relationsMatch = $existing['projection_hash'] === $hash
      ? $this->relationsMatch($generationId, $providerId, $projection)
      : FALSE;
    if ($sourceSequence === $existingSequence
      && $projection->changed === $existingChanged
      && $existing['projection_hash'] === $hash
      && $relationsMatch
      && $existingProviderVersion === $providerVersion) {
      return FALSE;
    }

    $entryUpdate = $this->database->update('aculta_index_entry')
      ->fields($fields)
      ->condition('entry_id', $existing['entry_id'])
      ->condition('provider_id', $providerId)
      ->condition('changed', $existingChanged)
      ->condition('source_sequence', $existingSequence)
      ->condition('projection_hash', $existing['projection_hash'])
      ->execute();
    if ($entryUpdate !== 1) {
      return NULL;
    }

    if (!$relationsMatch) {
      $this->replaceRelations($generationId, $providerId, $projection, $now);
      return TRUE;
    }
    return TRUE;
  }

  /**
   * Confirms the generation is still eligible for projection writes.
   */
  private function assertWritableGeneration(string $generationId): void {
    $status = $this->database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['status'])
      ->condition('generation_id', $generationId)
      ->forUpdate()
      ->execute()
      ->fetchField();
    if (!in_array($status, ['building', 'validated', 'active'], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Generation %s is missing or not writable.', $generationId));
    }
  }

  /**
   * Changes the candidate cache namespace atomically with a catalog write.
   */
  private function incrementCandidateRevision(string $generationId): void {
    $updated = $this->database->update('aculta_index_generation')
      ->expression('candidate_revision', 'candidate_revision + 1')
      ->condition('generation_id', $generationId)
      ->execute();
    if ($updated !== 1) {
      throw new \LogicException('Cannot advance the candidate cache revision for a missing Content Index generation.');
    }
  }

  /**
   * Confirms the provider owns this entity type and bundle.
   */
  private function assertProviderOwnsProjection(string $providerId, ContentIndexProjection $projection): void {
    if (!$this->providers->has($providerId)) {
      throw new \InvalidArgumentException(sprintf('Unknown ACULTA Content Index provider: %s.', $providerId));
    }
    $supported = $this->providers->get($providerId)->getSupportedEntityTypes();
    if (!in_array($projection->bundle, $supported[$projection->entityTypeId] ?? [], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Provider %s does not own projection %s:%s.', $providerId, $projection->entityTypeId, $projection->bundle));
    }
  }

  /**
   * Returns a canonical hash of the provider's allowlisted projection.
   */
  private function projectionHash(string $providerId, int $providerVersion, ContentIndexProjection $projection): string {
    $relations = [];
    foreach ($projection->relations as $relation) {
      $relations[] = $this->relationValues($relation);
    }
    sort($relations);

    return hash('sha256', json_encode([
      'provider_id' => $providerId,
      'provider_version' => $providerVersion,
      'entity_type' => $projection->entityTypeId,
      'entity_id' => $projection->entityId,
      'langcode' => $projection->langcode,
      'bundle' => $projection->bundle,
      'uuid' => $projection->uuid,
      'label' => $projection->label,
      'domain_id' => $projection->domainId,
      'published' => $projection->published,
      'created' => $projection->created,
      'changed' => $projection->changed,
      'published_at' => $projection->publishedAt,
      'relations' => $relations,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
  }

  /**
   * Calculates the canonical hash used to compare a source with its projection.
   *
   * @param string $providerId
   *   Registered provider that owns the projection.
   * @param \Drupal\aculta_portal\ContentIndex\ContentIndexProjection $projection
   *   Current source projection.
   *
   * @return string
   *   SHA-256 fingerprint using the provider's current projection version.
   */
  public function calculateProjectionHash(string $providerId, ContentIndexProjection $projection): string {
    $this->assertProviderOwnsProjection($providerId, $projection);
    return $this->projectionHash(
      $providerId,
      $this->providers->get($providerId)->getProjectionVersion(),
      $projection,
    );
  }

  /**
   * Replaces one source translation's outgoing relations atomically.
   */
  private function replaceRelations(string $generationId, string $providerId, ContentIndexProjection $projection, int $updated): void {
    $this->deleteRelations($generationId, $providerId, $projection->entityTypeId, $projection->entityId, $projection->langcode);
    foreach ($this->uniqueRelations($providerId, $projection) as $relationHash => $relation) {
      $this->database->insert('aculta_index_relation')
        ->fields([
          'generation_id' => $generationId,
          'provider_id' => $providerId,
          'source_entity_type' => $projection->entityTypeId,
          'source_entity_id' => $projection->entityId,
          'source_langcode' => $projection->langcode,
          'relation_type' => $relation->type,
          'target_entity_type' => $relation->targetEntityTypeId,
          'target_entity_id' => $relation->targetEntityId,
          'target_langcode' => $relation->targetLangcode,
          'relation_hash' => $relationHash,
          'updated' => $updated,
        ])
        ->execute();
    }
  }

  /**
   * Deletes the relations owned by one source projection.
   */
  private function deleteRelations(string $generationId, string $providerId, string $entityTypeId, string $entityId, string $langcode): void {
    $this->database->delete('aculta_index_relation')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', $providerId)
      ->condition('source_entity_type', $entityTypeId)
      ->condition('source_entity_id', $entityId)
      ->condition('source_langcode', $langcode)
      ->execute();
  }

  /**
   * Checks stored relation hashes against the canonical projection set.
   */
  private function relationsMatch(string $generationId, string $providerId, ContentIndexProjection $projection): bool {
    $stored = $this->database->select('aculta_index_relation', 'relation')
      ->fields('relation', ['relation_hash'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', $providerId)
      ->condition('source_entity_type', $projection->entityTypeId)
      ->condition('source_entity_id', $projection->entityId)
      ->condition('source_langcode', $projection->langcode)
      ->execute()
      ->fetchCol();
    sort($stored);
    return $stored === array_keys($this->uniqueRelations($providerId, $projection));
  }

  /**
   * Deduplicates relations by their stable SHA-256 identity.
   *
   * @param string $providerId
   *   Stable owner of this entity projection.
   * @param \Drupal\aculta_portal\ContentIndex\ContentIndexProjection $projection
   *   Source projection containing explicit relations.
   *
   * @return array<string, \Drupal\aculta_portal\ContentIndex\ContentIndexRelation>
   *   Relations keyed by stable hash.
   */
  private function uniqueRelations(string $providerId, ContentIndexProjection $projection): array {
    $unique = [];
    foreach ($projection->relations as $relation) {
      $hash = hash('sha256', json_encode([
        $providerId,
        $projection->entityTypeId,
        $projection->entityId,
        $projection->langcode,
        ...$this->relationValues($relation),
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
      $unique[$hash] = $relation;
    }
    ksort($unique);
    return $unique;
  }

  /**
   * Returns a canonical relation identity tuple.
   *
   * @return array{string, string, string, string}
   *   Type and target identity values.
   */
  private function relationValues(ContentIndexRelation $relation): array {
    return [
      $relation->type,
      $relation->targetEntityTypeId,
      $relation->targetEntityId,
      $relation->targetLangcode,
    ];
  }

}
