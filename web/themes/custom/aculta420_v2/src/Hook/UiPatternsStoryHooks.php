<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ui_patterns\ComponentPluginManager;
use Drupal\ui_patterns_library\StoryDefault;
use Drupal\ui_patterns_library\StoryPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves executable story plugins and keeps component discovery fresh.
 */
final class UiPatternsStoryHooks {

  public function __construct(
    #[Autowire(service: 'plugin.manager.component_story')]
    private readonly StoryPluginManager $storyPluginManager,
    #[Autowire(service: 'plugin.manager.sdc')]
    private readonly ComponentPluginManager $componentPluginManager,
  ) {}

  /**
   * Refreshes discovery caches and hydrates custom story props on overview.
   */
  #[Hook('preprocess_ui_patterns_overview_page')]
  public function preprocessOverview(array &$variables): void {
    // Invalida cache de descoberta para que qualquer alteração nos SDCs,
    // templates Twig, CSS ou stories reflita automaticamente no overview.
    $sdcManager = $this->componentPluginManager;
    $sdcManager->clearCachedDefinitions();
    $this->storyPluginManager->clearCachedDefinitions();

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
   * Refreshes discovery caches and hydrates custom story props on detail.
   */
  #[Hook('preprocess_ui_patterns_single_page')]
  public function preprocessSingle(array &$variables): void {
    $sdcManager = $this->componentPluginManager;
    $sdcManager->clearCachedDefinitions();
    $this->storyPluginManager->clearCachedDefinitions();

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
