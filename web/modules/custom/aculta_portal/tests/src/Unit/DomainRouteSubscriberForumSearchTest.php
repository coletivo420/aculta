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
 * Tests purpose classification for public Forum Search API routes.
 */
#[Group('aculta_portal')]
final class DomainRouteSubscriberForumSearchTest extends UnitTestCase {

  /**
   * The Forum search View is classified on the Forum purpose.
   */
  public function testForumSearchViewRouteUsesForumPurpose(): void {
    $routes = new RouteCollection();
    $route = new Route('/pesquisar');
    $routes->add('view.aculta_forum_search.page_search', $route);
    $subscriber = new DomainRouteSubscriber($this->createMock(ConfigFactoryInterface::class));

    $method = new \ReflectionMethod($subscriber, 'alterRoutes');
    $method->invoke($subscriber, $routes);

    self::assertSame('forum', $route->getOption('_aculta_domain_purpose'));
  }

}
