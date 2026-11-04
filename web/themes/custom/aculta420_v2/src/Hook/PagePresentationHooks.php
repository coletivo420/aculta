<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Maps the Portal's neutral Domain Presentation contract to theme variables.
 */
final class PagePresentationHooks {

  public function __construct(
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Exposes the legacy homepage showcase prepared by ACULTA Portal.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $variables['aculta_account_menu'] = [];
    $this->moveAccountMenuToUtilityBar($variables);
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
      $pluginId = $block['#plugin_id'] ?? '';
      $lazyBuilder = $block['#lazy_builder'] ?? [];
      if ($pluginId === '' && ($lazyBuilder[0] ?? '') === 'Drupal\\block\\BlockViewBuilder::lazyBuilder') {
        $blockId = $lazyBuilder[1][0] ?? '';
        if (is_string($blockId) && $blockId !== '') {
          $pluginId = (string) ($this->configFactory->get('block.block.' . $blockId)->get('plugin') ?? '');
        }
      }

      if ($pluginId === 'system_menu_block:account') {
        $variables['aculta_account_menu'][$key] = $block;
        unset($variables['page']['header'][$key]);
      }
    }
  }

}
