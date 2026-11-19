<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Group;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Defines access to generic Group membership relationships. */
interface GroupMembershipReaderInterface {

  /**
   * Loads generic membership relationships attached to an account.
   *
   * @return \Drupal\group\Entity\GroupRelationshipInterface[]
   *   The user's Group memberships.
   */
  public function loadByUser(AccountInterface $account): array;

  /**
   * Loads generic membership relationships attached to a group.
   *
   * @return \Drupal\group\Entity\GroupRelationshipInterface[]
   *   The group's generic memberships.
   */
  public function loadByGroup(GroupInterface $group): array;

}
