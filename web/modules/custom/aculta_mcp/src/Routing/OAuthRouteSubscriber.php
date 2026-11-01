<?php

declare(strict_types=1);

namespace Drupal\aculta_mcp\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Restricts OAuth client enrollment to administrator-created consumers.
 *
 * MCP Server OAuth depends on Simple OAuth Client Registration. ACULTA uses
 * pre-registered OAuth consumers, so its public dynamic registration and
 * registration-management routes must not be exposed or advertised.
 */
final class OAuthRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ([
      'simple_oauth_client_registration.register',
      'simple_oauth_client_registration.manage',
    ] as $route_name) {
      $collection->remove($route_name);
    }
  }

}
