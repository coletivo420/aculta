<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_portal\Domain\ContentPurposeResolver;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\EventSubscriber\DomainPurposeRequestSubscriber;
use Drupal\domain\DomainNegotiatorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Public location catalog routes are confined to the eventos purpose.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class LocationPurposeAccessKernelTest extends KernelTestBase {

  /** {@inheritdoc} */
  protected static $modules = ['system', 'user', 'key', 'options', 'text', 'filter', 'taxonomy', 'datetime', 'datetime_range', 'address', 'geofield', 'aculta_calendar', 'aculta_events'];

  public function testLocationCatalogIsNotServedFromAnotherPurpose(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_events.locations');
    $this->assertSame('eventos', $route->getOption('_aculta_domain_purpose'));

    $domainNegotiator = $this->createMock(DomainNegotiatorInterface::class);
    $domainNegotiator->method('getActiveDomain')->willReturn(NULL);
    $purpose = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $domainNegotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $matcher = $this->createMock(RequestMatcherInterface::class);
    $matcher->method('matchRequest')->willReturn(['_route' => 'aculta_events.locations']);
    $routes = $this->createMock(RouteProviderInterface::class);
    $routes->method('getRouteByName')->with('aculta_events.locations')->willReturn($route);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('isAuthenticated')->willReturn(FALSE);
    $resolver = new ContentPurposeResolver($this->container->get('entity_type.manager'), $purpose);
    $subscriber = new DomainPurposeRequestSubscriber(
      $purpose,
      $matcher,
      $currentUser,
      $routes,
      $resolver,
    );
    $request = Request::create('/locais');
    $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

    $subscriber->onRequestBeforeRouter($event);

    $this->assertSame(404, $event->getResponse()?->getStatusCode());
  }

}
