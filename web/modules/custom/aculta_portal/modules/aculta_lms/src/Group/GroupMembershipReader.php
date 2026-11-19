<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Group;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;

/**
 * Reads generic Group memberships without the removed Group 3 loader service. */
final class GroupMembershipReader implements GroupMembershipReaderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc} */
  public function loadByUser(AccountInterface $account): array {
    $storage = $this->entityTypeManager->getStorage('group_relationship');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_id', $account->id())
      ->condition('plugin_id', 'group_membership')
      ->execute();

    return array_values(array_filter(
      $storage->loadMultiple($ids),
      static fn ($membership): bool => $membership instanceof GroupRelationshipInterface,
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function loadByGroup(GroupInterface $group): array {
    $storage = $this->entityTypeManager->getStorage('group_relationship');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('gid', $group->id())
      ->condition('plugin_id', 'group_membership')
      ->execute();

    return array_values(array_filter(
      $storage->loadMultiple($ids),
      static fn ($membership): bool => $membership instanceof GroupRelationshipInterface
        && $membership->getPluginId() === 'group_membership',
    ));
  }

}
