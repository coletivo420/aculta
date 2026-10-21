<?php

declare(strict_types=1);

namespace Drupal\aculta_studio;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Decides what the account can see, at each level of the Studio.
 *
 * Three levels: area (top tabs, one per owner), section (left submenu of the area) and tab (the tabs of a
 * section). An action is shown only when the owner's route grants it. An empty level is removed, so an area
 * appears only when it has at least one allowed action in one of its sections and tabs.
 */
final class StudioPresenter {

  public function __construct(
    private readonly PluginManagerInterface $tabs,
  ) {}

  /**
   * Visible areas keyed by plugin id, in weight order.
   *
   * @return array<string, \Drupal\aculta_studio\StudioTabInterface>
   */
  public function visibleTabs(AccountInterface $account): array {
    $visible = [];
    foreach ($this->sortedDefinitions() as $id => $definition) {
      $tab = $this->tabs->createInstance($id);
      if (!$tab instanceof StudioTabInterface || !$tab->isAvailable()) {
        continue;
      }
      if ($this->sectionsFor($tab, $account) !== []) {
        $visible[$id] = $tab;
      }
    }
    return $visible;
  }

  /**
   * The sections of an area the account can use: only sections with tabs, and tabs with allowed actions.
   *
   * @return array<string, array{label: \Stringable|string, weight: int, tabs: array<string, array{label: \Stringable|string, weight: int, actions: array<int, array{title: \Stringable|string, url: \Drupal\Core\Url, access: bool}>}>}>
   */
  public function sectionsFor(StudioTabInterface $tab, AccountInterface $account): array {
    if (!$tab->access($account)->isAllowed()) {
      return [];
    }
    $sections = [];
    foreach ($tab->sections($account) as $sectionId => $section) {
      $tabs = [];
      foreach ($section['tabs'] ?? [] as $tabId => $item) {
        $actions = array_values(array_filter($item['actions'] ?? [], static fn (array $action): bool => (bool) ($action['access'] ?? FALSE)));
        // A tab is kept when it has an allowed action or a listing; a listing still applies the node access.
        if ($actions !== [] || isset($item['listing'])) {
          $tabs[$tabId] = ['label' => $item['label'], 'weight' => $item['weight'] ?? 0, 'actions' => $actions, 'listing' => $item['listing'] ?? NULL];
        }
      }
      if ($tabs !== []) {
        $sections[$sectionId] = ['label' => $section['label'], 'weight' => $section['weight'] ?? 0, 'tabs' => $this->byWeight($tabs)];
      }
    }
    return $this->byWeight($sections);
  }

  /**
   * Definitions ordered by their declared weight, then by id.
   *
   * @return array<string, array<string, mixed>>
   */
  private function sortedDefinitions(): array {
    $definitions = $this->tabs->getDefinitions();
    uasort($definitions, static fn (array $a, array $b): int => [$a['weight'] ?? 0, $a['id']] <=> [$b['weight'] ?? 0, $b['id']]);
    return $definitions;
  }

  /**
   * Keeps the keys and orders the items by their weight.
   */
  private function byWeight(array $items): array {
    uasort($items, static fn (array $a, array $b): int => $a['weight'] <=> $b['weight']);
    return $items;
  }

}
