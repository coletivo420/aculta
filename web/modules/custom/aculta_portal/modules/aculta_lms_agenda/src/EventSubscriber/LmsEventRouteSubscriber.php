<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Applies enrollment access to direct event pages and individual event ICS.
 */
final class LmsEventRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach (['aculta_events.page', 'aculta_events.date_ics'] as $routeName) {
      $route = $collection->get($routeName);
      if ($route !== NULL) {
        $route->setRequirement('_custom_access', 'aculta_lms_agenda.event_route_access::access');
      }
    }
  }

}
