<?php

declare(strict_types=1);

namespace Drupal\aculta_faq\Access;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Restricts FAQ block edits to this module's bundle and editorial permission.
 */
final class EditorialBlockAccessCheck {

  /**
   * Checks bundle and dedicated editorial permission.
   */
  public static function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResult {
    $block = $route_match->getParameter('block_content');
    $access = AccessResult::allowedIf(
      $block instanceof BlockContentInterface
      && $block->bundle() === 'aculta_editorial'
      && $account->hasPermission('administer aculta faq'),
    )->cachePerPermissions();
    return $block instanceof BlockContentInterface ? $access->addCacheableDependency($block) : $access;
  }

}
