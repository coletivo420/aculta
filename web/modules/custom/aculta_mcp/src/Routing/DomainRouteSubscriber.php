<?php

declare(strict_types=1);

namespace Drupal\aculta_mcp\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Restricts the HTTP MCP endpoint to the main domain purpose.
 */
final class DomainRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Apply after the portal's purpose assignment pass so the authoritative
    // purpose cannot be overwritten by a broad route family rule.
    return [RoutingEvents::ALTER => [['onAlterRoutes', -2048]]];
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('mcp_server.handle');
    if ($route === NULL) {
      return;
    }

    $route->setOption('_aculta_domain_purpose', 'main');
    $route->setOption('_aculta_domain_purpose_authoritative', TRUE);
  }

}
