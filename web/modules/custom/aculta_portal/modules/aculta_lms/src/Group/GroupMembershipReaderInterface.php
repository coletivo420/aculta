<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Group;

use Drupal\Core\Session\AccountInterface;

/**
 * Loads the current account's generic Group memberships. */
interface GroupMembershipReaderInterface {

  /**
   * @return \Drupal\group\Entity\GroupRelationshipInterface[]
   *   The user's Group memberships.
   */
  public function loadByUser(AccountInterface $account): array;

}
