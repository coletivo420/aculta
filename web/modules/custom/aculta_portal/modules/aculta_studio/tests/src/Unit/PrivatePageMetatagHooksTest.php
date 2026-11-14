<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta_studio\Hook\PrivatePageMetatagHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Checks that private Studio pages are not exposed to search engines.
 */
#[CoversClass(PrivatePageMetatagHooks::class)]
#[Group('aculta_studio')]
final class PrivatePageMetatagHooksTest extends UnitTestCase {

  /**
   * Private Studio routes receive noindex and lose public canonical tags.
   */
  public function testPrivateStudioRoutesAreNoindex(): void {
    foreach ([
      'aculta_studio.dashboard',
      'aculta_studio.account',
      'aculta_studio.account_data',
      'aculta_studio.profile',
    ] as $route) {
      $route_match = $this->createMock(RouteMatchInterface::class);
      $route_match->method('getRouteName')->willReturn($route);
      $hook = new PrivatePageMetatagHooks($route_match);
      $tags = [
        'robots' => 'index, follow',
        'canonical_url' => '/conta',
        'og_url' => '/conta',
        'schema_web_page_url' => '/conta',
      ];
      $context = [];

      $hook->metatagsAlter($tags, $context);

      self::assertSame('noindex, nofollow', $tags['robots'], $route);
      self::assertArrayNotHasKey('canonical_url', $tags, $route);
      self::assertArrayNotHasKey('og_url', $tags, $route);
      self::assertArrayNotHasKey('schema_web_page_url', $tags, $route);
    }
  }

  /**
   * Routes outside the private Studio owner keep their existing metadata.
   */
  public function testPublicRoutesAreUnchanged(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('aculta_user.public_profile');
    $hook = new PrivatePageMetatagHooks($route_match);
    $tags = ['robots' => 'index, follow', 'canonical_url' => '/perfil/pessoa'];
    $context = [];

    $hook->metatagsAlter($tags, $context);

    self::assertSame('index, follow', $tags['robots']);
    self::assertSame('/perfil/pessoa', $tags['canonical_url']);
  }

}
