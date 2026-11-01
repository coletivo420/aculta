<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Declares calendar pages shared by the events and main purposes. */
final class SharedPurposeRouteSubscriber extends RouteSubscriberBase {

  /**
   * Calendar routes supplied by aculta_events and aculta_calendar. */
  private const ROUTES = [
    'aculta_events.agenda',
    'aculta_events.month',
    'aculta_events.day',
    'aculta_events.ics',
    'aculta_events.embed',
    'aculta_events.interactive',
  ];

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [RoutingEvents::ALTER => [['onAlterRoutes', -2050]]];
  }

  /**
   * {@inheritdoc} */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach (self::ROUTES as $routeName) {
      $route = $collection->get($routeName);
      if ($route === NULL || $route->getOption('_aculta_domain_purpose') !== 'eventos') {
        continue;
      }
      $route->setOption('_aculta_domain_purposes', ['eventos', 'main']);
      $route->setOption('_aculta_canonical_purpose', 'eventos');
    }
  }

}
