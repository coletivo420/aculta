<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Maps the Portal's neutral Domain Presentation contract to theme variables.
 */
final class PagePresentationHooks {

  public function __construct(
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Exposes the legacy homepage showcase prepared by ACULTA Portal.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $variables['aculta_account_menu'] = [];
    // The account menu block carries user.roles cacheability; this flag only
    // chooses the presentation (dropdown for signed-in users, links otherwise).
    $variables['aculta_account_authenticated'] = $this->currentUser->isAuthenticated();
    // Identity and avatar are Portal-owned render data; the theme only maps
    // their neutral contract into the institutional bar.
    $variables['aculta_account_avatar'] = $variables['aculta_account_avatar'] ?? NULL;
    $this->moveAccountMenuToUtilityBar($variables);
    $this->wrapPrimaryMenuWithNavigationComponent($variables);
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
  private function moveAccountMenuToUtilityBar(array &$variables): void {
    if (!isset($variables['page']['header']) || !is_array($variables['page']['header'])) {
      return;
    }

    foreach (Element::children($variables['page']['header']) as $key) {
      $block = $variables['page']['header'][$key];
      if (!is_array($block)) {
        continue;
      }
      if ($this->getPluginId($block) === 'system_menu_block:account') {
        if ($this->currentUser->isAuthenticated()) {
          $variables['aculta_account_menu'][$key] = $block;
        }
        unset($variables['page']['header'][$key]);
      }
    }
  }

  /**
   * Wraps the primary menu with its SDC in the same header region position.
   *
   * The menu block stays at its configured weight among the domain header
   * blocks, preserving the shell's order while the SDC adds progressive
   * enhancement. The original block remains the slot render array so Drupal
   * retains its access checks and cacheability metadata.
   *
   * @param array<string, mixed> $variables
   *   Page theme variables.
   */
  private function wrapPrimaryMenuWithNavigationComponent(array &$variables): void {
    if (!isset($variables['page']['header']) || !is_array($variables['page']['header'])) {
      return;
    }

    foreach (Element::children($variables['page']['header']) as $key) {
      $block = $variables['page']['header'][$key];
      if (!is_array($block) || $this->getPluginId($block) !== 'system_menu_block:main') {
        continue;
      }

      $component = [
        '#type' => 'component',
        '#component' => 'aculta420_v2:navigation-bar',
        '#props' => [],
        '#slots' => ['menu_items' => $block],
      ];
      foreach (['#access', '#attached', '#cache', '#weight'] as $property) {
        if (array_key_exists($property, $block)) {
          $component[$property] = $block[$property];
        }
      }
      $variables['page']['header'][$key] = $component;
    }
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
