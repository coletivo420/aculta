<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_wiki\Entity\WikiProposal;

/**
 * Copies legacy Wiki proposal revisions to the shared contribution entity.
 *
 * Entity IDs, UUIDs, and revision IDs are preserved so route parameters and
 * queued Wiki outcome envelopes continue to resolve without translation.
 */
final class WikiContributionMigration {

  private const BATCH_SIZE = 10;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
  ) {}

  /**
   * Migrates a bounded batch and updates Drupal's update-hook sandbox.
   *
   * @param array<string, mixed> $sandbox
   *   Persistent update-hook state.
   *
   * @return string
   *   Progress information for the update log.
   */
  public function migrateBatch(array &$sandbox): string {
    if (!isset($sandbox['initialized'])) {
      $sandbox['last_id'] = 0;
      $sandbox['processed'] = 0;
      $sandbox['total'] = (int) $this->entityTypeManager
        ->getStorage('aculta_wiki_proposal')
        ->getQuery()
        ->accessCheck(FALSE)
        ->count()
        ->execute();
      $sandbox['initialized'] = TRUE;
    }

    $legacyStorage = $this->entityTypeManager->getStorage('aculta_wiki_proposal');
    $ids = $legacyStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', (int) $sandbox['last_id'], '>')
      ->sort('id', 'ASC')
      ->range(0, self::BATCH_SIZE)
      ->execute();
    $idempotencyHashes = $this->getIdempotencyHashes();

    foreach ($ids as $id) {
      $this->migrateProposal((int) $id, $idempotencyHashes[(int) $id] ?? NULL);
      $sandbox['last_id'] = (int) $id;
      $sandbox['processed']++;
    }

    $sandbox['#finished'] = count($ids) < self::BATCH_SIZE
      ? 1
      : min(0.99, (int) $sandbox['processed'] / max(1, (int) $sandbox['total']));
    return sprintf(
      'Reconciled %d of %d Wiki proposals.',
      (int) $sandbox['processed'],
      (int) $sandbox['total'],
    );
  }

  /**
   * Migrates all revisions of one Wiki proposal in a single transaction.
   */
  private function migrateProposal(int $legacyId, ?string $idempotencyHash): void {
    if ($this->database->select('aculta_wiki_contribution_migration', 'migration')
      ->fields('migration', ['legacy_id'])
      ->condition('legacy_id', $legacyId)
      ->execute()
      ->fetchField()) {
      return;
    }

    $legacyStorage = $this->entityTypeManager->getStorage('aculta_wiki_proposal');
    $legacy = $legacyStorage->load($legacyId);
    if (!$legacy instanceof WikiProposal) {
      throw new \RuntimeException(sprintf('Legacy Wiki proposal %d disappeared during migration.', $legacyId));
    }
    $revisionIds = array_keys($legacyStorage->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('id', $legacyId)
      ->sort('revision_id', 'ASC')
      ->execute());
    $revisions = $legacyStorage->loadMultipleRevisions(array_map('strval', $revisionIds));
    if ($revisionIds === [] || count($revisions) !== count($revisionIds)) {
      throw new \RuntimeException(sprintf('Wiki proposal %d has an incomplete revision history.', $legacyId));
    }

    $transaction = $this->database->startTransaction();
    try {
      $contributionStorage = $this->entityTypeManager->getStorage('aculta_contribution');
      $collision = $contributionStorage->load($legacyId);
      if ($collision instanceof Contribution) {
        throw new \RuntimeException(sprintf(
          'Wiki proposal %d collides with an existing ACULTA Contribution; no data was overwritten.',
          $legacyId,
        ));
      }

      $contribution = NULL;
      foreach ($revisionIds as $legacyRevisionId) {
        $legacyRevision = $revisions[$legacyRevisionId] ?? NULL;
        if (!$legacyRevision instanceof WikiProposal) {
          throw new \RuntimeException(sprintf(
            'Wiki proposal %d revision %d could not be loaded.',
            $legacyId,
            $legacyRevisionId,
          ));
        }
        if (count($legacyRevision->getTranslationLanguages()) > 1) {
          throw new \RuntimeException(sprintf(
            'Wiki proposal %d revision %d has translations that are not supported by this migration.',
            $legacyId,
            $legacyRevisionId,
          ));
        }

        if ($contribution === NULL) {
          $contribution = $contributionStorage->create([
            'id' => $legacyId,
            'uuid' => $legacy->uuid(),
          ]);
        }
        else {
          $contribution = $contributionStorage->load($legacyId);
          if (!$contribution instanceof Contribution) {
            throw new \RuntimeException(sprintf('Migrated Wiki proposal %d was not persisted.', $legacyId));
          }
          $contribution->setNewRevision(TRUE);
        }

        $target = $legacyRevision->get('target')->entity;
        $sourceData = [
          'wiki_intent' => (string) $legacyRevision->get('intent')->value,
          'proposed_text' => (string) $legacyRevision->get('proposed_text')->value,
          'source_uris' => array_values(array_filter(array_map(
            static fn (array $item): string => (string) ($item['value'] ?? ''),
            $legacyRevision->get('source_uri')->getValue(),
          ), static fn (string $uri): bool => $uri !== '')),
          'legacy_target_id' => (int) $legacyRevision->get('target')->target_id,
        ];
        if ($target) {
          unset($sourceData['legacy_target_id']);
        }

        $contribution->set('id', $legacyId);
        $contribution->set('uuid', $legacy->uuid());
        $contribution->set('uid', (int) $legacyRevision->getOwnerId());
        $contribution->set('subject', (string) $legacyRevision->get('subject')->value);
        $contribution->set('source', 'wiki');
        $contribution->set('source_module', 'aculta_wiki');
        $contribution->set('purpose', 'wiki');
        $contribution->set('intent', 'update');
        $contribution->set('target_type', 'node');
        $contribution->set('target_uuid', $target ? $target->uuid() : NULL);
        $contribution->set('base_revision', (string) $legacyRevision->get('target_revision_id')->value);
        $contribution->set('payload', $legacyRevision->getProposedFields());
        $contribution->set('source_data', $sourceData);
        $contribution->set('decision_data', []);
        $contribution->set('rationale', (string) $legacyRevision->get('rationale')->value);
        $contribution->set('state', (string) $legacyRevision->get('state')->value);
        $contribution->set('decision_note', (string) $legacyRevision->get('decision_note')->value);
        $contribution->set('idempotency_hash', $idempotencyHash);
        $contribution->set('created', (int) $legacyRevision->get('created')->value);
        $contribution->set('changed', (int) $legacyRevision->get('changed')->value);
        $contribution->set('langcode', $legacyRevision->language()->getId());
        $contribution->set('revision_id', (int) $legacyRevisionId);
        $contribution->setRevisionCreationTime((int) $legacyRevision->getRevisionCreationTime());
        $contribution->setRevisionUserId((int) $legacyRevision->getRevisionUserId());
        $contribution->setRevisionLogMessage((string) $legacyRevision->getRevisionLogMessage());
        $contribution->isDefaultRevision($legacyRevision->isDefaultRevision());
        $contribution->save();

        if ((int) $contribution->getRevisionId() !== (int) $legacyRevisionId) {
          throw new \RuntimeException(sprintf(
            'Wiki proposal %d revision ID %d was not preserved.',
            $legacyId,
            $legacyRevisionId,
          ));
        }
        $this->database->insert('aculta_wiki_contribution_revision_map')
          ->fields([
            'legacy_id' => $legacyId,
            'legacy_revision_id' => (int) $legacyRevisionId,
            'contribution_id' => $legacyId,
            'contribution_revision_id' => (int) $contribution->getRevisionId(),
          ])
          ->execute();
      }

      $this->database->insert('aculta_wiki_contribution_migration')
        ->fields([
          'legacy_id' => $legacyId,
          'legacy_uuid' => $legacy->uuid(),
          'contribution_id' => $legacyId,
          'revision_count' => count($revisionIds),
          'migrated' => 1,
        ])
        ->execute();
      unset($transaction);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Finds an unexpired legacy idempotency key for this proposal.
   *
   * @return array<int, string>
   *   Legacy proposal IDs mapped to active idempotency hashes.
   */
  private function getIdempotencyHashes(): array {
    $hashes = [];
    foreach ($this->keyValueExpirableFactory
      ->get('aculta_wiki_proposal_submissions')
      ->getAll() as $key => $value) {
      if (is_numeric($value) && (int) $value > 0 && preg_match('/^[a-f0-9]{64}$/D', (string) $key)) {
        $hashes[(int) $value] = (string) $key;
      }
    }
    return $hashes;
  }

}
