<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ui_patterns_library\StoryDefault;
use Drupal\ui_patterns_library\StoryInterface;
use Drupal\ui_patterns_library\StoryPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves executable story plugins and keeps component discovery fresh.
 */
final class UiPatternsStoryHooks {

  public function __construct(
    #[Autowire(service: 'plugin.manager.component_story')]
    private readonly StoryPluginManager $storyPluginManager,
  ) {}

  /**
   * Prepares overview page metadata and navigation anchors without eager story execution.
   *
   * @param array<mixed> $variables
   *   UI Patterns Library page variables.
   */
  #[Hook('preprocess_ui_patterns_overview_page')]
  public function preprocessOverview(array &$variables): void {
    $this->attachLibrary($variables, 'aculta420_v2/ui-library-admin');
    // The official Library loads story HTML inside Shadow DOM, where the SDC's
    // script asset from the fetched page is not executed. Load its behavior on
    // the Library document so attachBehaviors(wrap) can initialize each story.
    $this->attachLibrary($variables, 'aculta420_v2/color-mode-switcher');
    // Âncora de cada grupo: as abas de navegação levam ao cabeçalho do grupo.
    $variables['aculta_group_anchors'] = [];
    $groups = $variables['groups'] ?? NULL;
    if (is_array($groups)) {
      foreach (array_keys($groups) as $groupName) {
        $variables['aculta_group_anchors'][$groupName] = 'grupo-' . Html::getClass((string) $groupName);
      }
    }
    if (!is_array($groups)) {
      return;
    }

    // Total de componentes para o contador da aba "Todos".
    $variables['aculta_total_components'] = array_sum(array_map('count', array_filter($groups, 'is_array')));

    // Componentes em ordem alfabética (pelo nome exibido) dentro de cada grupo/aba.
    $collator = class_exists(\Collator::class) ? new \Collator('pt_BR') : NULL;
    foreach ($groups as $groupName => $items) {
      if (!is_array($items)) {
        continue;
      }
      uasort($items, static function (mixed $a, mixed $b) use ($collator): int {
        $nameA = is_array($a) && is_string($a['name'] ?? NULL) ? $a['name'] : '';
        $nameB = is_array($b) && is_string($b['name'] ?? NULL) ? $b['name'] : '';
        return $collator ? ($collator->compare($nameA, $nameB) ?: 0) : strnatcasecmp($nameA, $nameB);
      });
      $groups[$groupName] = $items;
    }
    $variables['groups'] = $groups;
  }

  /**
   * Hydrates custom story props on detail without clearing discovery caches.
   *
   * @param array<mixed> $variables
   *   UI Patterns Library page variables.
   */
  #[Hook('preprocess_ui_patterns_single_page')]
  public function preprocessSingle(array &$variables): void {
    if (isset($variables['component']) && is_array($variables['component'])) {
      $this->hydrateStories($variables['component']);
    }
  }

  /**
   * Replaces custom story definitions with props and slots from their plugin.
   *
   * @param array<mixed> $component
   *   Component definition from UI Patterns Library.
   */
  private function hydrateStories(array &$component): void {
    if (!is_array($component['stories'] ?? NULL)) {
      return;
    }
    foreach ($component['stories'] as &$story) {
      if (!is_array($story)) {
        continue;
      }
      $class = $story['class'] ?? StoryDefault::class;
      if ($class === StoryDefault::class || !is_string($story['id'] ?? NULL)) {
        continue;
      }
      $plugin = $this->storyPluginManager->createInstance($story['id']);
      if (!$plugin instanceof StoryInterface) {
        continue;
      }
      $story['props'] = $plugin->props();
      $story['slots'] = $plugin->slots();
    }
  }

  /**
   * Attaches a theme library while preserving other render attachments.
   *
   * @param array<mixed> $variables
   *   UI Patterns Library page variables.
   * @param string $library
   *   Theme library identifier.
   */
  private function attachLibrary(array &$variables, string $library): void {
    $attached = is_array($variables['#attached'] ?? NULL) ? $variables['#attached'] : [];
    $libraries = is_array($attached['library'] ?? NULL) ? $attached['library'] : [];
    $libraries[] = $library;
    $attached['library'] = $libraries;
    $variables['#attached'] = $attached;
  }

}
