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
 * Integration routes exist only on the MAIN domain.
 */
#[Group('aculta_portal')]
final class DomainRouteSubscriberIntegrationPurposeTest extends UnitTestCase {

  /**
   * Every /integracoes/ route is bound to MAIN.
   */
  public function testIntegrationRoutesBelongToMain(): void {
    $routes = new RouteCollection();
    $integration = new Route('/integracoes/openstreetmap/autorizar');
    $routes->add('aculta_events_openstreetmap.oauth_authorize', $integration);
    $public = new Route('/eventos');
    $routes->add('aculta_events.catalogue', $public);

    $subscriber = new DomainRouteSubscriber($this->createMock(ConfigFactoryInterface::class));
    $method = new \ReflectionMethod($subscriber, 'alterRoutes');
    $method->invoke($subscriber, $routes);

    self::assertSame('main', $integration->getOption('_aculta_domain_purpose'));
    self::assertNull($public->getOption('_aculta_domain_purpose'));
  }

}
