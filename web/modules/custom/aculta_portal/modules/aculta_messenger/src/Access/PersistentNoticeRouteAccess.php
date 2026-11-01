<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\node\NodeInterface;

/** Restricts notice-specific administrative form routes to notice nodes. */
final class PersistentNoticeRouteAccess {

  /** Allows only the ACULTA persistent-notice bundle on dedicated routes. */
  public function access(NodeInterface $node): AccessResultInterface {
    if ($node->bundle() !== 'aculta_notice') {
      return AccessResult::notFound()->addCacheableDependency($node);
    }
    return AccessResult::allowed()->addCacheableDependency($node);
  }

}
