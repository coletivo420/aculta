<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta420_v2\Hook\PagePresentationHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies page-layout selection for the Studio dashboard and account.
 */
#[CoversClass(PagePresentationHooks::class)]
#[Group('aculta420_v2')]
final class PagePresentationHooksTest extends UnitTestCase {

  /**
   * Maps the Portal's context-tabs contract to the existing tabs SDC.
   */
  public function testContextTabsMapsToTabsWithItsRequiredContract(): void {
    $componentManager = $this->createMock(ComponentPluginManager::class);
    $componentManager->expects(self::once())
      ->method('hasDefinition')
      ->with('aculta420_v2:tabs')
      ->willReturn(TRUE);
    $hooks = $this->newHooks($componentManager);
    $items = ['#theme' => 'item_list', '#items' => []];
    $variables = [
      'name' => 'context-tabs',
      'props' => ['label' => 'Navegação do estúdio'],
      'slots' => ['items' => $items],
      'component' => ['#type' => 'container'],
    ];

    $hooks->preprocessPortalComponent($variables);

    self::assertSame([
      '#type' => 'component',
      '#component' => 'aculta420_v2:tabs',
      '#props' => ['label' => 'Navegação do estúdio'],
      '#slots' => ['items' => $items],
    ], $variables['component']);
  }

  /**
   * Applies the global accent to the node wrapper and cache dependency.
   */
  public function testRevistaAccentIsAppliedToTeaserWrapperAndCache(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('aculta_revista_accent.selected.revista_accent')
      ->willReturn('revista-teaser--accent-green');
    $config->method('getCacheTags')
      ->willReturn(['config:aculta420_v2.settings']);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('aculta420_v2.settings')
      ->willReturn($config);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $node->method('get')->willReturnCallback(function (string $fieldName): FieldItemListInterface {
      $field = $this->createMock(FieldItemListInterface::class);
      $field->method('isEmpty')->willReturn(FALSE);
      return $field;
    });
    $node->method('label')->willReturn('Publicação de teste');
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('/revista/teste');
    $node->method('toUrl')->willReturn($url);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $hooks = new PagePresentationHooks(
      $configFactory,
      $this->createMock(ExtensionList::class),
      $this->createMock(AccountProxyInterface::class),
    );
    $variables = [
      'node' => $node,
      'view_mode' => 'teaser',
      'attributes' => new Attribute(),
      'elements' => ['#type' => 'container'],
    ];

    $hooks->preprocessNode($variables);

    self::assertSame('green', $variables['aculta_revista_accent']);
    self::assertTrue($variables['attributes']->hasClass('revista-teaser--accent-green'));
    self::assertContains('config:aculta420_v2.settings', $variables['elements']['#cache']['tags']);
  }

  /**
   * Maps empty-state only when the corresponding SDC is discoverable.
   */
  public function testEmptyStateMapsToItsSdc(): void {
    $componentManager = $this->createMock(ComponentPluginManager::class);
    $componentManager->expects(self::once())
      ->method('hasDefinition')
      ->with('aculta420_v2:empty-state')
      ->willReturn(TRUE);
    $hooks = $this->newHooks($componentManager);
    $variables = [
      'name' => 'empty-state',
      'props' => ['compact' => TRUE],
      'slots' => ['message' => ['#plain_text' => 'Sem resultados']],
      'component' => ['#type' => 'container'],
    ];

    $hooks->preprocessPortalComponent($variables);

    self::assertSame('aculta420_v2:empty-state', $variables['component']['#component']);
    self::assertSame($variables['props'], $variables['component']['#props']);
    self::assertSame($variables['slots'], $variables['component']['#slots']);
  }

  /**
   * Maps only the identity portion of the Studio profile to author-card.
   */
  public function testProfileIdentityMapsToAuthorCardAndKeepsOwnerData(): void {
    $componentManager = $this->createMock(ComponentPluginManager::class);
    $componentManager->expects(self::once())
      ->method('hasDefinition')
      ->with('aculta420_v2:author-card')
      ->willReturn(TRUE);
    $hooks = $this->newHooks($componentManager);
    $avatar = ['#theme' => 'image', '#uri' => 'public://avatar.png', '#alt' => 'Avatar'];
    $actions = ['#theme' => 'item_list', '#items' => ['Editar apelido']];
    $publicLink = ['#type' => 'link', '#title' => 'Ver perfil público'];
    $variables = [
      'name' => 'profile-identity',
      'props' => ['display_name' => 'Marley', 'hint' => 'Apelido público'],
      'slots' => ['avatar' => $avatar, 'actions' => $actions, 'public_link' => $publicLink],
      'attributes' => ['id' => 'profile-identity', 'data-portal-ui' => 'studio-profile-identity'],
    ];

    $hooks->preprocessPortalComponent($variables);

    self::assertSame('aculta420_v2:author-card', $variables['component']['#component']);
    self::assertSame([
      'variant' => 'profile_full',
      'size' => 'md',
      'display_name' => 'Marley',
      'designation' => 'Apelido público',
      'has_avatar' => TRUE,
      'has_actions' => TRUE,
    ], $variables['component']['#props']);
    self::assertSame($avatar, $variables['component']['#slots']['avatar']);
    self::assertSame($actions, $variables['component']['#slots']['actions']['edit_actions']);
    self::assertSame($publicLink, $variables['component']['#slots']['actions']['public_link']);
    self::assertSame($variables['attributes'], $variables['component']['#attributes']);
  }

  /**
   * Maps profile bio and social render arrays without flattening owner data.
   */
  public function testProfileDetailsMapsBioAndSocialSlots(): void {
    foreach ([
      'profile-bio' => ['Biografia', 'body', 'bio', 'studio-profile-bio-title'],
      'profile-social' => ['Redes sociais', 'items', 'social_links', 'studio-profile-social-title'],
    ] as $name => [$title, $sourceSlot, $targetSlot, $titleId]) {
      $componentManager = $this->createMock(ComponentPluginManager::class);
      $componentManager->expects(self::once())
        ->method('hasDefinition')
        ->with('aculta420_v2:profile-details')
        ->willReturn(TRUE);
      $hooks = $this->newHooks($componentManager);
      $ownerContent = ['#theme' => 'item_list', '#items' => ['Rede autorizada']];
      $actions = ['#theme' => 'item_list', '#items' => ['Editar']];
      $variables = [
        'name' => $name,
        'props' => ['title' => $title],
        'slots' => [$sourceSlot => $ownerContent, 'actions' => $actions],
      ];

      $hooks->preprocessPortalComponent($variables);

      self::assertSame('aculta420_v2:profile-details', $variables['component']['#component']);
      self::assertSame([
        'title' => $title,
        'title_id' => $titleId,
        'heading_level' => 2,
      ], $variables['component']['#props']);
      self::assertSame([$targetSlot => $ownerContent, 'actions' => $actions], $variables['component']['#slots']);
    }
  }

  /**
   * Leaves components outside the approved map on the Portal fallback.
   */
  public function testOtherComponentsKeepTheirExistingFallback(): void {
    $componentManager = $this->createMock(ComponentPluginManager::class);
    $componentManager->expects(self::never())->method('hasDefinition');
    $hooks = $this->newHooks($componentManager);
    $fallback = ['#type' => 'container', 'content' => ['#plain_text' => 'Perfil']];
    $variables = [
      'name' => 'unmapped-component',
      'props' => [],
      'slots' => [],
      'component' => $fallback,
    ];

    $hooks->preprocessPortalComponent($variables);

    self::assertSame($fallback, $variables['component']);
  }

  /**
   * Keeps the Portal fallback when the tabs slot contract is incomplete.
   */
  public function testContextTabsWithoutItemsKeepsFallback(): void {
    $componentManager = $this->createMock(ComponentPluginManager::class);
    $componentManager->expects(self::once())
      ->method('hasDefinition')
      ->with('aculta420_v2:tabs')
      ->willReturn(TRUE);
    $hooks = $this->newHooks($componentManager);
    $fallback = ['#type' => 'container'];
    $variables = [
      'name' => 'context-tabs',
      'props' => ['label' => 'Navegação'],
      'slots' => [],
      'component' => $fallback,
    ];

    $hooks->preprocessPortalComponent($variables);

    self::assertSame($fallback, $variables['component']);
  }

  /**
   * Dashboard on the purpose root keeps its aside layout before front-page logic.
   */
  public function testDashboardAndAccountUseAsideLayout(): void {
    foreach ([
      'aculta_studio.dashboard' => TRUE,
      'aculta_studio.account' => FALSE,
    ] as $routeName => $isFrontPage) {
      $routeMatch = $this->createMock(CurrentRouteMatch::class);
      $routeMatch->method('getRouteName')->willReturn($routeName);
      $pathMatcher = $this->createMock(PathMatcherInterface::class);
      $pathMatcher->method('isFrontPage')->willReturn($isFrontPage);

      $hooks = new PagePresentationHooks(
        $this->createMock(ConfigFactoryInterface::class),
        $this->createMock(ExtensionList::class),
        $this->createMock(AccountProxyInterface::class),
        $routeMatch,
        $pathMatcher,
      );
      $method = new \ReflectionMethod(PagePresentationHooks::class, 'getPageLayoutVariant');

      self::assertSame('aside', $method->invoke($hooks), $routeName);
    }
  }

  /**
   * UI Patterns library pages use the page-wide documentation layout.
   */
  public function testComponentLibraryUsesFullLayout(): void {
    $routeMatch = $this->createMock(CurrentRouteMatch::class);
    $routeMatch->method('getRouteName')->willReturn('ui_patterns_library.overview');
    $pathMatcher = $this->createMock(PathMatcherInterface::class);
    $pathMatcher->method('isFrontPage')->willReturn(FALSE);

    $hooks = new PagePresentationHooks(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(ExtensionList::class),
      $this->createMock(AccountProxyInterface::class),
      $routeMatch,
      $pathMatcher,
    );
    $method = new \ReflectionMethod(PagePresentationHooks::class, 'getPageLayoutVariant');

    self::assertSame('full', $method->invoke($hooks));
  }

  /**
   * Builds the hook object with its optional component discovery dependency.
   */
  private function newHooks(ComponentPluginManager $componentManager): PagePresentationHooks {
    return new PagePresentationHooks(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(ExtensionList::class),
      $this->createMock(AccountProxyInterface::class),
      NULL,
      NULL,
      $componentManager,
    );
  }

}
