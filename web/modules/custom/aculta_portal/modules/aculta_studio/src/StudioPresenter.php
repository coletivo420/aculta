<?php

declare(strict_types=1);

namespace Drupal\aculta_studio;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Chooses the tabs an account can see, in weight order.
 *
 * A tab is shown only when its owner is available and the account has the
 * owner's permission. Actions are filtered the same way by the controller.
 */
final class StudioPresenter {

  public function __construct(
    private readonly PluginManagerInterface $tabs,
  ) {}

  /**
   * Returns the visible tabs keyed by plugin id.
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
      if ($tab->access($account)->isAllowed()) {
        $visible[$id] = $tab;
      }
    }
    return $visible;
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

}
