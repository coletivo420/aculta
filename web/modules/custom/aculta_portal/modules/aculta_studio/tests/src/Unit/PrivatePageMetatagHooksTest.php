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
  public function testPrivateStudioRouteIsNoindex(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('aculta_studio.account_data');
    $hook = new PrivatePageMetatagHooks($route_match);
    $tags = [
      'robots' => 'index, follow',
      'canonical_url' => '/conta/dados',
      'og_url' => '/conta/dados',
      'schema_web_page_url' => '/conta/dados',
    ];
    $context = [];

    $hook->metatagsAlter($tags, $context);

    self::assertSame('noindex, nofollow', $tags['robots']);
    self::assertArrayNotHasKey('canonical_url', $tags);
    self::assertArrayNotHasKey('og_url', $tags);
    self::assertArrayNotHasKey('schema_web_page_url', $tags);
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
