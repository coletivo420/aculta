<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\ui_patterns_library\StoryDefault;
use Drupal\ui_patterns_library\StoryPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves executable story plugins before the UI Patterns library renders.
 */
final class UiPatternsStoryHooks {

  public function __construct(
    #[Autowire(service: 'plugin.manager.component_story')]
    private readonly StoryPluginManager $storyPluginManager,
  ) {}

  /**
   * Hydrates custom story props and slots on the overview and provider pages.
   */
  #[Hook('preprocess_ui_patterns_overview_page')]
  public function preprocessOverview(array &$variables): void {
    if (!is_array($variables['groups'] ?? NULL)) {
      return;
    }
    foreach ($variables['groups'] as &$components) {
      foreach ($components as &$component) {
        $this->hydrateStories($component);
      }
    }
    unset($components, $component);
  }

  /**
   * Hydrates custom story props and slots on a component's detail page.
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
   * @param array<string, mixed> $component
   *   Component definition from UI Patterns Library.
   */
  private function hydrateStories(array &$component): void {
    if (!is_array($component['stories'] ?? NULL)) {
      return;
    }
    foreach ($component['stories'] as &$story) {
      $class = $story['class'] ?? StoryDefault::class;
      if ($class === StoryDefault::class || !is_string($story['id'] ?? NULL)) {
        continue;
      }
      $plugin = $this->storyPluginManager->createInstance($story['id']);
      $story['props'] = $plugin->props();
      $story['slots'] = $plugin->slots();
    }
  }

}
