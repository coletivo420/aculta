<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Fails closed for notice routes when an optional producer is disabled.
 */
final class UnavailableNoticeAdapterRouteHandler {

  /**
   * Denies access when the optional source adapter is unavailable.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    return AccessResult::forbidden()->cachePerUser();
  }

  /**
   * Returns not found when a disabled adapter route is invoked directly.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function open(): never {
    throw new NotFoundHttpException();
  }

}
