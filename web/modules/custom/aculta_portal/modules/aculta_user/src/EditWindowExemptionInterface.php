<?php

declare(strict_types=1);

namespace Drupal\aculta_user;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Lets a discussion consumer exempt accounts from the author edit window.
 *
 * Consumers register implementations with the aculta_user.edit_window_exemption
 * tag, so aculta_user never depends on the module that owns the context.
 */
interface EditWindowExemptionInterface {

  /**
   * Returns allowed when the account keeps its contextual edit rights.
   *
   * Any other result, including neutral, leaves the author window in force.
   */
  public function access(EntityInterface $entity, AccountInterface $account): AccessResultInterface;

}
