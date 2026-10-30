<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\EventSubscriber\DomainRouteSubscriber;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests that an authoritative route purpose survives the MAIN defaults.
 */
#[Group('aculta_portal')]
final class DomainRouteSubscriberAuthoritativePurposeTest extends UnitTestCase {

  /**
   * Authoritative admin routes keep their own purpose; others stay MAIN.
   */
  public function testAuthoritativePurposeIsNotOverriddenByAdminDefaults(): void {
    $routes = new RouteCollection();

    $events = new Route('/painel-administrativo/eventos/adicionar', [], [], [
      '_admin_route' => TRUE,
      '_aculta_domain_purpose' => 'eventos',
      '_aculta_domain_purpose_authoritative' => TRUE,
    ]);
    $routes->add('aculta_events.event_add', $events);

    $plainAdmin = new Route('/painel-administrativo/outra-area', [], [], [
      '_admin_route' => TRUE,
    ]);
    $routes->add('other.admin_route', $plainAdmin);

    $oauth = new Route('/integracoes/openstreetmap/autorizar', [], [], [
      '_aculta_domain_purpose' => 'eventos',
    ]);
    $routes->add('aculta_events_openstreetmap.oauth_authorize', $oauth);

    $subscriber = new DomainRouteSubscriber($this->createMock(ConfigFactoryInterface::class));
    $method = new \ReflectionMethod($subscriber, 'alterRoutes');
    $method->invoke($subscriber, $routes);

    self::assertSame('eventos', $events->getOption('_aculta_domain_purpose'));
    self::assertSame('main', $plainAdmin->getOption('_aculta_domain_purpose'));
    self::assertSame('eventos', $oauth->getOption('_aculta_domain_purpose'));
  }

}
