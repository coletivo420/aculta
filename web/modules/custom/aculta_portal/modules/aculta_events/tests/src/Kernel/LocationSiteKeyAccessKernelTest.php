<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_portal\Domain\ContentSiteKeyResolver;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_portal\EventSubscriber\DomainSiteRequestSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

/**
 * Public location catalog routes are confined to the eventos site key.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class LocationSiteKeyAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'options', 'link', 'text', 'filter', 'taxonomy',
    'datetime', 'datetime_range', 'address', 'geofield', 'aculta_calendar',
    'aculta_events',
  ];

  /**
   * Location page is confined to its canonical site key.
   */
  public function testLocationCatalogIsNotServedFromAnotherSiteKey(): void {
    $this->assertRouteIsConfinedToEventsSiteKey('aculta_events.location_canonical', '/locais/1');
  }

  /**
   * The contextual ICS endpoint is also confined to the eventos purpose.
   */
  public function testLocationAgendaIcsIsNotServedFromAnotherSiteKey(): void {
    $this->assertRouteIsConfinedToEventsSiteKey('aculta_events.location_ics', '/locais/1/agenda.ics');
  }

  /**
   * Assert that a public route returns 404 outside its canonical site key.
   */
  private function assertRouteIsConfinedToEventsSiteKey(string $routeName, string $path): void {
    $route = $this->container->get('router.route_provider')->getRouteByName($routeName);
    $this->assertSame('eventos', $route->getOption('_aculta_site_key'));

    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getCurrentSiteKey')->willReturn(SiteKey::Main);
    $matcher = $this->createMock(RequestMatcherInterface::class);
    $matcher->method('matchRequest')->willReturn(['_route' => $routeName]);
    $routes = $this->createMock(RouteProviderInterface::class);
    $routes->method('getRouteByName')->with($routeName)->willReturn($route);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('isAuthenticated')->willReturn(FALSE);
    $resolver = new ContentSiteKeyResolver($this->container->get('entity_type.manager'), $sites);
    $subscriber = new DomainSiteRequestSubscriber(
      $sites,
      $matcher,
      $currentUser,
      $routes,
      $resolver,
    );
    $request = Request::create($path);
    $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

    $subscriber->onRequestBeforeRouter($event);

    $this->assertSame(404, $event->getResponse()?->getStatusCode());
  }

}
