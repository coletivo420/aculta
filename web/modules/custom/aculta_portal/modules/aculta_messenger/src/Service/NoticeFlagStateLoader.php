<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Entity\Storage\FlaggingStorageInterface;

/**
 * Loads per-account Flag state for a batch of notices in one storage query.
 */
final class NoticeFlagStateLoader {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the available Flag IDs keyed by notice entity ID.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Notices of one entity type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account whose personal Flag state is requested.
   *
   * @return array<int|string, array<string, string>>
   *   Flag IDs keyed by entity ID and flag ID.
   */
  public function loadForAccount(array $entities, AccountInterface $account): array {
    if (!$account->isAuthenticated() || $entities === []) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('flagging');
    if (!$storage instanceof FlaggingStorageInterface) {
      return [];
    }

    return $storage->loadIsFlaggedMultiple($entities, $account);
  }

}
