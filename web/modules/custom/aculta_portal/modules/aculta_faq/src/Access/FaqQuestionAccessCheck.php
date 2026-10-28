<?php

declare(strict_types=1);

namespace Drupal\aculta_faq\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Restricts FAQ edit routes to FAQ nodes and editorial administrators.
 */
final class FaqQuestionAccessCheck {

  /**
   * Checks that this is an FAQ node and the user can administer FAQ content.
   */
  public static function access(NodeInterface $node, AccountInterface $account, Request $request): AccessResult {
    return AccessResult::allowedIf(
      $node->bundle() === 'aculta_faq' && $account->hasPermission('administer aculta faq'),
    )->cachePerPermissions()->addCacheableDependency($node);
  }

}
