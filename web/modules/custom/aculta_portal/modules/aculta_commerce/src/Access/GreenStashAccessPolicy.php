<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Permission gate for future Green Stash entrypoints.
 *
 * This does not grant access to any Commerce entity: operations must still
 * enforce Commerce's native per-entity and per-store access handlers.
 */
final class GreenStashAccessPolicy {

  public const ACCESS = 'access aculta green stash';

  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, self::ACCESS);
  }

}
