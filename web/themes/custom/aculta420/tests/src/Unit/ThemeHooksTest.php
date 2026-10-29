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
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
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
   * Public event catalogue items map to the existing card SDC.
   */
  public function testEventCatalogueUsesEventCardComponent(): void {
    $hooks = $this->themeHooksForRoute('aculta_events.catalogue');
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('/eventos/encontro-cultural');
    self::assertInstanceOf(Url::class, $url);
    $variables = [
      'wrapper_attributes' => new Attribute(),
      'items' => [
        [
          'value' => [
            '#attributes' => ['data-portal-ui' => 'event-card event-card-cancelled'],
            '#cache' => ['tags' => ['aculta_event_list']],
            'title' => ['link' => ['#title' => 'Encontro Cultural', '#url' => $url]],
            'subtitle' => ['#value' => 'Cultura &amp; direitos'],
            'type' => ['#value' => 'Encontro'],
            'meta' => ['#items' => ['Próxima data: 29/10/2026 19:00']],
            'cancelled' => ['#access' => TRUE],
          ],
          'attributes' => new Attribute(),
        ],
        [
          '#attributes' => ['data-portal-ui' => 'event-card'],
          '#wrapper_attributes' => ['class' => ['event-list-item']],
          'title' => ['link' => ['#title' => 'Outro evento', '#url' => $url]],
        ],
      ],
    ];

    $hooks->preprocessEventCatalogue($variables);

    self::assertContains('event-catalogue__list', $variables['wrapper_attributes']->getClass());
    self::assertSame('aculta420:event_card', $variables['items'][0]['value']['#component']);
    self::assertSame('Encontro Cultural', $variables['items'][0]['value']['#props']['title']);
    self::assertSame('/eventos/encontro-cultural', $variables['items'][0]['value']['#props']['url']);
    self::assertSame('Cultura & direitos', $variables['items'][0]['value']['#props']['subtitle']);
    self::assertSame('Próxima data: 29/10/2026 19:00', $variables['items'][0]['value']['#props']['proxima']);
    self::assertTrue($variables['items'][0]['value']['#props']['cancelado']);
    self::assertSame(['tags' => ['aculta_event_list']], $variables['items'][0]['value']['#cache']);
    self::assertSame('aculta420:event_card', $variables['items'][1]['#component']);
    self::assertSame(['class' => ['event-list-item']], $variables['items'][1]['#wrapper_attributes']);
  }

  /**
   * Lists on other routes retain their existing render arrays.
   */
  public function testNonEventListsAreNotTransformed(): void {
    $hooks = $this->themeHooksForRoute('aculta_events.locations');
    $item = ['value' => ['#attributes' => ['data-portal-ui' => 'event-card']]];
    $variables = ['items' => [$item]];

    $hooks->preprocessEventCatalogue($variables);

    self::assertSame([$item], $variables['items']);
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
