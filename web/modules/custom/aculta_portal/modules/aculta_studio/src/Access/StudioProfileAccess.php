<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Ensures optional User forms never resolve without their owning module.
 */
final class StudioProfileAccess {

  public function __construct(private readonly ModuleHandlerInterface $modules) {}

  /**
   * Applies availability and current-account permission to every editor.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($this->modules->moduleExists('aculta_user'))
      ->andIf(AccessResult::allowedIf($account->isAuthenticated()))
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access aculta portal'))
      ->cachePerUser();
  }

}
