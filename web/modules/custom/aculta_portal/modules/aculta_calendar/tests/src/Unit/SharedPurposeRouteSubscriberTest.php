<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Unit;

use Drupal\aculta_calendar\EventSubscriber\SharedPurposeRouteSubscriber;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests route metadata for the shared calendar purposes.
 */
final class SharedPurposeRouteSubscriberTest extends UnitTestCase {

  /**
   * Ensures only the intended calendar routes are shared.
   */
  public function testOnlyCalendarRoutesAreSharedWithMain(): void {
    $routes = new RouteCollection();
    $calendarRoutes = [
      'aculta_events.agenda' => '/calendario',
      'aculta_events.month' => '/calendario/mes/{ano}/{mes}',
      'aculta_events.day' => '/calendario/dia/{ano}/{mes}/{dia}',
      'aculta_events.ics' => '/calendario/exportar.ics',
      'aculta_events.embed' => '/calendario/incorporar',
      'aculta_events.interactive' => '/calendario/interativo',
    ];
    foreach ($calendarRoutes as $name => $path) {
      $routes->add($name, new Route($path, [], [], ['_aculta_domain_purpose' => 'eventos']));
    }
    $routes->add('aculta_events.date_add', new Route('/painel-administrativo/eventos/datas/adicionar', [], [], ['_aculta_domain_purpose' => 'eventos']));
    $subscriber = new SharedPurposeRouteSubscriber();
    $alter = new \ReflectionMethod($subscriber, 'alterRoutes');
    $alter->invoke($subscriber, $routes);

    self::assertSame('eventos', $routes->get('aculta_events.agenda')->getOption('_aculta_canonical_purpose'));
    foreach (array_keys($calendarRoutes) as $name) {
      self::assertSame(['eventos', 'main'], $routes->get($name)->getOption('_aculta_domain_purposes'));
    }
    self::assertNull($routes->get('aculta_events.date_add')->getOption('_aculta_domain_purposes'));
  }

}
