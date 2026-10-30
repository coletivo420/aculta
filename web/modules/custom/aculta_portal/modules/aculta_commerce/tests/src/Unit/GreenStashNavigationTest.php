<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Navigation\GreenStashNavigation;
use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('aculta_commerce')]
/**
 * Tests canonical Green Stash Studio routes and contextual navigation.
 *
 * @coversDefaultClass \\Drupal\\aculta_commerce\\Navigation\\GreenStashNavigation
 */
final class GreenStashNavigationTest extends UnitTestCase {

  /**
   * Tests canonical paths and the absence of the retired route family.
   */
  public function testCanonicalRoutesUseOfficialStudioPathAndNoLegacyAliases(): void {
    $moduleRoot = dirname(__DIR__, 3);
    $routes = Yaml::decode((string) file_get_contents($moduleRoot . '/aculta_commerce.routing.yml'));

    self::assertSame('/studio/green-stash', $routes['aculta_commerce.green_stash']['path']);
    self::assertSame('/studio/green-stash/{secao}', $routes['aculta_commerce.green_stash_area']['path']);
    self::assertSame('ACULTA STUDIO GREEN STASH', $routes['aculta_commerce.green_stash']['defaults']['_title']);
    self::assertSame('/studio/green-stash/produtos/novo', $routes['aculta_commerce.green_stash_product_create']['path']);
    self::assertSame('/studio/green-stash/produtos/{produto}', $routes['aculta_commerce.green_stash_product_detail']['path']);
    self::assertSame('/studio/green-stash/envios/{aculta_shipment}/transicao/{transition}', $routes['aculta_commerce.green_stash_shipment_transition']['path']);

    foreach ($routes as $route) {
      self::assertFalse(str_starts_with($route['path'], '/estudio/loja'));
    }
  }

  /**
   * Tests that every area has read-only contextual navigation.
   */
  public function testEachAreaIsReadOnlyAndHasItsOwnRoute(): void {
    $navigation = new GreenStashNavigation();
    foreach (GreenStashNavigation::SLUGS as $slug => $key) {
      $label = GreenStashNavigation::AREAS[$key];
      $render = $navigation->build($slug);
      self::assertSame('green-stash-entry', $render['#attributes']['data-portal-ui']);
      self::assertSame($label, $render['heading']['#value']);
      self::assertCount(count(GreenStashNavigation::AREAS), $render['navigation']['items']);
      self::assertSame('page', $render['navigation']['items'][$slug]['#attributes']['aria-current']);
      self::assertSame('aculta_commerce.green_stash_area', $render['navigation']['items'][$slug]['#url']->getRouteName());
      self::assertArrayNotHasKey('orders', $render);
      self::assertArrayNotHasKey('products', $render);
    }
  }

  /**
   * Tests that unknown area slugs are rejected.
   */
  public function testUnknownAreaIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new GreenStashNavigation())->build('unknown');
  }

}
