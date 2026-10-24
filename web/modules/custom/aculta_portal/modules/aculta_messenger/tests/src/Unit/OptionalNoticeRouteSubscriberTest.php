<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\EventSubscriber\OptionalNoticeRouteSubscriber;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests routes for disabled optional notice integrations.
 */
#[Group('aculta_messenger')]
final class OptionalNoticeRouteSubscriberTest extends TestCase {

  /**
   * Removes only routes whose owner module is disabled.
   */
  public function testDisabledIntegrationRoutesAreRemoved(): void {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->willReturnCallback(
      static fn (string $module): bool => $module === 'aculta_wiki',
    );
    $routes = new RouteCollection();
    foreach ([
      'aculta_messenger.lms_notice_open',
      'aculta_messenger.wiki_notice_open',
      'aculta_messenger.commerce_notice_open',
      'aculta_messenger.donation_notice_open',
      'unrelated.route',
    ] as $routeName) {
      $routes->add($routeName, new Route('/' . $routeName));
    }

    $subscriber = new OptionalNoticeRouteSubscriber($moduleHandler);
    $subscriber->onAlterRoutes(new RouteBuildEvent($routes));

    self::assertNull($routes->get('aculta_messenger.lms_notice_open'));
    self::assertTrue($routes->get('aculta_messenger.wiki_notice_open') instanceof Route);
    self::assertNull($routes->get('aculta_messenger.commerce_notice_open'));
    self::assertNull($routes->get('aculta_messenger.donation_notice_open'));
    self::assertTrue($routes->get('unrelated.route') instanceof Route);
  }

}
