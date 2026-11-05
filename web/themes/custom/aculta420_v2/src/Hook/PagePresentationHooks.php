<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Render\Element;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Maps the Portal's neutral Domain Presentation contract to theme variables.
 */
final class PagePresentationHooks {

  public function __construct(
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'extension.list.theme')]
    private readonly ExtensionList $themes,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'current_route_match')]
    private readonly ?CurrentRouteMatch $currentRouteMatch = NULL,
    #[Autowire(service: 'path.matcher')]
    private readonly ?PathMatcherInterface $pathMatcher = NULL,
    #[Autowire(service: 'plugin.manager.sdc')]
    private readonly ?ComponentPluginManager $componentManager = NULL,
  ) {}

  /**
   * Exposes the legacy homepage showcase prepared by ACULTA Portal.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $variables['aculta_layout_variant'] = $this->getPageLayoutVariant();
    $variables['aculta_lms_lesson_route'] = $this->currentRouteMatch?->getRouteName() === 'aculta_lms.lesson_view';
    $variables['aculta_layout_page_sdc_available'] = $this->componentAvailable('layout-page');
    $variables['aculta_lms_lesson_sdc_available'] = $this->componentAvailable('lms-lesson-page');
    $variables['aculta_page_title'] = [];
    $variables['aculta_breadcrumb'] = [];
    $variables['aculta_help'] = [];
    $variables['aculta_account_menu'] = [];
    $variables['aculta_account_authenticated'] = $this->currentUser->isAuthenticated();
    $variables['aculta_account_avatar'] = $variables['aculta_account_avatar'] ?? NULL;
    $variables['aculta_account_name'] = $variables['aculta_account_name'] ?? NULL;
    $this->composeDomainHeader($variables);
    if ($variables['aculta_lms_lesson_route']) {
      // The LMS render array owns the lesson H1; avoid a second route-title H1.
      $variables['aculta_page_title'] = [];
    }
    $variables['aculta_account_menu'] = $this->buildAccountMenuComponent(
      $variables['aculta_account_menu'],
      $variables['aculta_account_authenticated'],
      $variables['aculta_account_avatar'],
      $variables['aculta_account_name'],
      $variables['aculta_domain_sites'] ?? [],
    );
    $this->removeSearchDialogDependency($variables);

    $links = $variables['domain_presentation']['regions']['showcase'] ?? [];
    if (!is_array($links)) {
      $variables['multidomain_network_links'] = [];
      return;
    }

    $variables['multidomain_network_links'] = array_values(array_filter(
      $links,
      static fn (mixed $link): bool => is_array($link)
        && is_string($link['title'] ?? NULL)
        && $link['title'] !== ''
        && is_string($link['url'] ?? NULL)
        && $link['url'] !== '',
    ));
  }

  /**
   * Exposes whether a SDC is registered, not merely present as a source file.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $variables['aculta_accordion_sdc_available'] = $this->componentAvailable('accordion');
  }

  /**
   * Maps compatible Portal components to V2 SDCs.
   *
   * The Portal fallback preprocess runs before theme preprocess and fills a
   * generic render array. For the two supported names, replace that fallback
   * with the matching SDC while preserving props and slots. `context-tabs`
   * maps to `tabs`; there is no SDC named `context-tabs`.
   */
  #[Hook('preprocess_aculta_portal_component')]
  public function preprocessPortalComponent(array &$variables): void {
    $name = $variables['name'] ?? NULL;
    $component = match ($name) {
      'empty-state' => 'empty-state',
      'context-tabs' => 'tabs',
      default => NULL,
    };
    if ($component === NULL || !$this->componentAvailable($component)) {
      return;
    }

    $props = is_array($variables['props'] ?? NULL) ? $variables['props'] : [];
    $slots = is_array($variables['slots'] ?? NULL) ? $variables['slots'] : [];
    if ($component === 'tabs' && (!is_string($props['label'] ?? NULL) || trim($props['label']) === '' || !isset($slots['items']))) {
      return;
    }

    $variables['component'] = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:' . $component,
      '#props' => $props,
      '#slots' => $slots,
    ];
  }

  /**
   * Checks the component repository without rebuilding its persistent cache.
   */
  private function componentAvailable(string $component): bool {
    return $this->componentManager?->hasDefinition('aculta420_v2:' . $component) ?? FALSE;
  }

  /**
   * Selects the layout from the route's content type or named public route.
   *
   * Hostname and Portal purpose are deliberately not used for this mapping.
   */
  private function getPageLayoutVariant(): string {
    // Compiled hook discovery can call this class with the previous argument
    // list until Drupal rebuilds its container. Keep that state safe.
    if ($this->currentRouteMatch === NULL || $this->pathMatcher === NULL) {
      return 'stack';
    }

    $routeName = $this->currentRouteMatch->getRouteName();
    if ($routeName === 'aculta_studio.dashboard') {
      return 'aside';
    }

    if ($this->pathMatcher->isFrontPage()) {
      return 'full';
    }

    $node = $this->currentRouteMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      return match ($node->bundle()) {
        'wiki_entry', 'aculta_forum_topic' => 'aside',
        'revista' => 'full',
        default => 'stack',
      };
    }

    return match ($this->currentRouteMatch->getRouteName()) {
      'aculta_studio.dashboard',
      'aculta_studio.account',
      'aculta_studio.account_data',
      'aculta_studio.account_address',
      'aculta_studio.account_documents',
      'aculta_studio.account_security',
      'aculta_studio.account_password',
      'aculta_studio.account_connections',
      'aculta_studio.account_orders',
      'aculta_studio.account_support',
      'aculta_studio.account_preferences',
      'aculta_studio.we_sativa',
      'aculta_studio.we_sativa_start',
      'aculta_lms.student_dashboard',
      'aculta_lms.educator_dashboard',
      'aculta_lms.lesson_view',
      'aculta_events.organizer_dashboard' => 'aside',
      'aculta_events.catalogue', 'aculta_events.map' => 'full',
      default => 'stack',
    };
  }

  /**
   * Creates the account SDC render array with explicit slots and user context.
   *
   * @param array<string, mixed> $menuItems
   *   Access-aware account menu supplied by Drupal.
   * @param bool $authenticated
   *   Whether the current visitor is authenticated.
   * @param array<string, mixed>|null $avatar
   *   Portal-owned avatar render array.
   * @param string|null $accountName
   *   Display name prepared by the Portal.
   * @param mixed $domainSites
   *   Site links prepared by the Portal for each published purpose.
   *
   * @return array<string, mixed>
   *   Account-menu component render array.
   */
  private function buildAccountMenuComponent(
    array $menuItems,
    bool $authenticated,
    ?array $avatar,
    ?string $accountName,
    mixed $domainSites,
  ): array {
    $slots = [];
    if ($authenticated) {
      if ($menuItems !== []) {
        $slots['menu_items'] = $menuItems;
      }
      if ($avatar !== NULL) {
        $slots['avatar'] = $avatar;
      }
    }
    else {
      $slots['menu_items'] = [
        '#type' => 'link',
        '#title' => 'Entrar',
        '#url' => Url::fromUserInput('/entrar/janela'),
      ];
    }

    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:account-menu',
      '#props' => [
        'authenticated' => $authenticated,
        'account_name' => $authenticated && is_string($accountName) && trim($accountName) !== ''
          ? trim($accountName)
          : NULL,
        'sites' => $this->normalizeDomainSites($domainSites),
      ],
      '#slots' => $slots,
      '#cache' => ['contexts' => ['user']],
    ];
  }

  /**
   * Restricts the Portal sites contract to the fields consumed by the SDC.
   *
   * @param mixed $sites
   *   Candidate sites supplied by ACULTA Portal.
   *
   * @return array
   *   Valid absolute HTTP(S) site links.
   */
  private function normalizeDomainSites(mixed $sites): array {
    if (!is_array($sites)) {
      return [];
    }
    $normalized = [];
    foreach ($sites as $site) {
      if (!is_array($site)
        || !is_string($site['purpose'] ?? NULL)
        || trim($site['purpose']) === ''
        || !is_string($site['title'] ?? NULL)
        || trim($site['title']) === ''
        || !is_string($site['url'] ?? NULL)
        || !in_array(parse_url($site['url'], PHP_URL_SCHEME), ['http', 'https'], TRUE)
        || !is_string(parse_url($site['url'], PHP_URL_HOST))
        || !is_bool($site['current'] ?? NULL)) {
        continue;
      }
      $normalized[] = [
        'purpose' => trim($site['purpose']),
        'title' => trim($site['title']),
        'url' => $site['url'],
        'current' => $site['current'],
      ];
    }
    return $normalized;
  }

  /**
   * The V2 bar uses Fetch instead of Drupal's jQuery dialog.
   *
   * @param array<string, mixed> $variables
   *   Page theme variables.
   */
  private function removeSearchDialogDependency(array &$variables): void {
    if (!isset($variables['aculta_search_utility']['#attached']['library'])
      || !is_array($variables['aculta_search_utility']['#attached']['library'])) {
      return;
    }
    $variables['aculta_search_utility']['#attached']['library'] = array_values(array_filter(
      $variables['aculta_search_utility']['#attached']['library'],
      static fn (string $library): bool => $library !== 'core/drupal.dialog.ajax',
    ));
  }

  /**
   * Moves the access-aware account menu render array into the fixed bar.
   *
   * This preserves the Portal-owned links, access results and cache metadata.
   *
   * @param array<string, mixed> $variables
   *   Page theme variables.
   */
  private function composeDomainHeader(array &$variables): void {
    if (!isset($variables['page']['header']) || !is_array($variables['page']['header'])) {
      return;
    }

    $brand = NULL;
    $brand_key = NULL;
    $menu = NULL;
    $menu_key = NULL;
    $weights = [];
    foreach (Element::children($variables['page']['header']) as $key) {
      $block = $variables['page']['header'][$key];
      if (!is_array($block)) {
        continue;
      }
      $pluginId = $this->getPluginId($block);
      switch ($pluginId) {
        case 'page_title_block':
          $variables['aculta_page_title'][$key] = $block;
          unset($variables['page']['header'][$key]);
          break;

        case 'system_breadcrumb_block':
          $variables['aculta_breadcrumb'][$key] = $block;
          unset($variables['page']['header'][$key]);
          break;

        case 'help_block':
          $variables['aculta_help'][$key] = $block;
          unset($variables['page']['header'][$key]);
          break;

        case 'system_menu_block:account':
          if ($this->currentUser->isAuthenticated()) {
            $variables['aculta_account_menu'][$key] = $block;
          }
          unset($variables['page']['header'][$key]);
          break;

        case 'system_branding_block':
          $brand = $block;
          $brand_key = $key;
          $weights[] = (int) ($block['#weight'] ?? 0);
          break;

        case 'system_menu_block:main':
          $menu = $block;
          $menu_key = $key;
          $weights[] = (int) ($block['#weight'] ?? 0);
          break;
      }
    }

    $brandMedia = $variables['domain_presentation']['regions']['brand_media'] ?? NULL;
    $brandProps = is_array($brandMedia) ? $this->buildBrandMediaProps($brandMedia, $variables) : NULL;
    if ($brandProps !== NULL) {
      $brand = NULL;
      $brand_key = $brand_key ?? $menu_key;
      if ($brand_key !== NULL) {
        unset($variables['page']['header'][$brand_key]);
      }
    }

    $targetKey = $menu_key ?? $brand_key;
    if ($targetKey === NULL && $brandProps !== NULL) {
      $targetKey = 'aculta_domain_header';
    }
    if ($targetKey === NULL || ($brand === NULL && $menu === NULL && $brandProps === NULL)) {
      return;
    }

    if ($brand_key !== NULL && $brand_key !== $targetKey) {
      unset($variables['page']['header'][$brand_key]);
    }
    if ($menu_key !== NULL && $menu_key !== $targetKey) {
      unset($variables['page']['header'][$menu_key]);
    }

    $slots = [];
    if ($brand !== NULL) {
      $slots['brand'] = $brand;
    }
    if ($menu !== NULL) {
      $slots['menu_items'] = $menu;
    }
    $component = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:navigation-bar',
      '#props' => $brandProps ?? [],
      '#slots' => $slots,
    ];
    if ($weights !== []) {
      $component['#weight'] = min($weights);
    }
    $variables['page']['header'][$targetKey] = $component;
  }

  /**
   * Maps a neutral Portal brand key to a theme-owned responsive logo asset.
   *
   * @param array<string, mixed> $brandMedia
   *   Brand identity metadata prepared by ACULTA Portal.
   * @param array<string, mixed> $variables
   *   Page theme variables containing the neutral domain identity.
   *
   * @return array<string, string>|null
   *   Strict SDC props for a supported logo, or NULL for the default branding.
   */
  private function buildBrandMediaProps(array $brandMedia, array $variables): ?array {
    if (($brandMedia['#brand_key'] ?? NULL) !== 'wiki420') {
      return NULL;
    }

    $themePath = $this->themes->getPath('aculta420_v2');
    $assetPath = $themePath . '/assets/logos/wiki420-horizontal-960w.webp';
    if (!is_file(DRUPAL_ROOT . '/' . $assetPath)) {
      return NULL;
    }

    $identity = $variables['domain_presentation']['identity'] ?? [];
    $srcset = [];
    foreach (['480w' => 480, '720w' => 720, '960w' => 960] as $suffix => $width) {
      $srcset[] = Url::fromUri('base:' . $themePath . '/assets/logos/wiki420-horizontal-' . $suffix . '.webp')->toString() . ' ' . $width . 'w';
    }

    return [
      'brand_home_url' => is_string($identity['home_url'] ?? NULL) ? $identity['home_url'] : '/',
      'brand_title' => is_string($identity['title'] ?? NULL) ? $identity['title'] : 'Wiki420',
      'brand_logo_url' => Url::fromUri('base:' . $assetPath)->toString(),
      'brand_logo_srcset' => implode(', ', $srcset),
      'brand_logo_alt' => is_string($brandMedia['#alt'] ?? NULL) ? $brandMedia['#alt'] : '',
    ];
  }

  /**
   * Resolves a block plugin ID from a render array or its lazy builder.
   *
   * @param array<string, mixed> $block
   *   The block render array.
   *
   * @return string
   *   The configured block plugin ID, or an empty string.
   */
  private function getPluginId(array $block): string {
    $pluginId = $block['#plugin_id'] ?? '';
    $lazyBuilder = $block['#lazy_builder'] ?? [];
    if ($pluginId === '' && ($lazyBuilder[0] ?? '') === 'Drupal\\block\\BlockViewBuilder::lazyBuilder') {
      $blockId = $lazyBuilder[1][0] ?? '';
      if (is_string($blockId) && $blockId !== '') {
        $pluginId = (string) ($this->configFactory->get('block.block.' . $blockId)->get('plugin') ?? '');
      }
    }

    return is_string($pluginId) ? $pluginId : '';
  }

}
