<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controls access to the owner-scoped Links dashboard.
 */
final class LinksDashboardAccess {

  /**
   * Allows link owners and administrators to open the dashboard.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'manage own aculta links')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'administer aculta links'));
  }

}
