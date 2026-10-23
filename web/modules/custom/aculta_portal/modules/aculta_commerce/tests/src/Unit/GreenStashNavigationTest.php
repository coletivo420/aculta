<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Navigation\GreenStashNavigation;
use Drupal\Tests\UnitTestCase;

/**
 * @group aculta_commerce
 */
final class GreenStashNavigationTest extends UnitTestCase {

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

  public function testUnknownAreaIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new GreenStashNavigation())->build('unknown');
  }

}
