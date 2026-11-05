<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Render\Element;
use Drupal\Core\Hook\Attribute\Hook;
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
  ) {}

  /**
   * Exposes the legacy homepage showcase prepared by ACULTA Portal.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $variables['aculta_page_title'] = [];
    $variables['aculta_breadcrumb'] = [];
    $variables['aculta_help'] = [];
    $variables['aculta_account_menu'] = [];
    $variables['aculta_account_authenticated'] = $this->currentUser->isAuthenticated();
    $variables['aculta_account_avatar'] = $variables['aculta_account_avatar'] ?? NULL;
    $this->composeDomainHeader($variables);
    $variables['aculta_account_menu'] = $this->buildAccountMenuComponent(
      $variables['aculta_account_menu'],
      $variables['aculta_account_authenticated'],
      $variables['aculta_account_avatar'],
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
   * Creates the account SDC render array with explicit slots and user context.
   *
   * @param array<string, mixed> $menuItems
   *   Access-aware account menu supplied by Drupal.
   * @param bool $authenticated
   *   Whether the current visitor is authenticated.
   * @param array<string, mixed>|null $avatar
   *   Portal-owned avatar render array.
   *
   * @return array<string, mixed>
   *   Account-menu component render array.
   */
  private function buildAccountMenuComponent(array $menuItems, bool $authenticated, ?array $avatar): array {
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
      '#props' => ['authenticated' => $authenticated],
      '#slots' => $slots,
      '#cache' => ['contexts' => ['user']],
    ];
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
