<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Account;

use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Loads the current User entity for a session account.
 */
interface AccountUserLoaderInterface {

  /**
   * Loads the entity for an account ID when the account is only a session.
   */
  public function load(AccountInterface $account): ?UserInterface;

}
