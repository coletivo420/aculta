<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Unit;

use Drupal\aculta420\Hook\ThemeHooks;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ACULTA420 theme attachment behavior for shared Messenger components.
 */
#[CoversClass(ThemeHooks::class)]
#[Group('aculta420')]
final class ThemeHooksTest extends UnitTestCase {

  /**
   * Notice grouping styles are declared by the theme metadata, not by a hook.
   *
   * The theme may only register preprocess hooks, so the library is loaded
   * through the theme's libraries list.
   */
  public function testMessengerNoticeStylesAreDeclaredByTheme(): void {
    $info = Yaml::decode((string) file_get_contents(dirname(__DIR__, 3) . '/aculta420.info.yml'));

    self::assertContains('aculta420/messenger-notices', $info['libraries']);
    self::assertFalse(method_exists(ThemeHooks::class, 'attachMessengerNoticeStyles'));
  }

  /**
   * Event detail keeps a single H1 by removing only the page-title block.
   */
  public function testEventDetailSuppressesTheDuplicatePageTitleBlock(): void {
    $hooks = $this->themeHooksForRoute('aculta_events.page');
    $variables = [
      'domain_presentation' => ['regions' => []],
      'page' => [
        'header' => [
          'page_title' => ['#plugin_id' => 'page_title_block'],
        ],
      ],
    ];

    $hooks->preprocessPage($variables);

    self::assertArrayNotHasKey('page_title', $variables['page']['header']);
    self::assertSame([], $variables['aculta_page_title']);
  }

  /**
   * Builds theme hooks with the requested route name.
   */
  private function themeHooksForRoute(string $routeName): ThemeHooks {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRouteName')->willReturn($routeName);
    return new ThemeHooks(
      $this->createMock(PathMatcherInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $routeMatch,
      $this->createMock(ExtensionList::class),
    );
  }

}
