<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Prevents new shared proposals from colliding with an unfinished Wiki copy.
 */
final class WikiContributionMigrationGate {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Whether every legacy proposal has a durable reconciliation row.
   */
  public function isComplete(): bool {
    if (!$this->database->schema()->tableExists('aculta_wiki_contribution_migration')) {
      return FALSE;
    }

    $legacyStorage = $this->entityTypeManager->getStorage('aculta_wiki_proposal');
    foreach ($legacyStorage->getQuery()->accessCheck(FALSE)->execute() as $legacyId) {
      $migrated = $this->database->select('aculta_wiki_contribution_migration', 'migration')
        ->fields('migration', ['legacy_id'])
        ->condition('legacy_id', (int) $legacyId)
        ->execute()
        ->fetchField();
      if ($migrated === FALSE || $migrated === NULL) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
