<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Component\Utility\Html;
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
   * Prepares overview page metadata and navigation anchors without eager story execution.
   */
  #[Hook('preprocess_ui_patterns_overview_page')]
  public function preprocessOverview(array &$variables): void {
    $variables['#attached']['library'][] = 'aculta420_v2/ui-library-admin';
    // Âncora de cada grupo: as abas de navegação levam ao cabeçalho do grupo.
    $variables['aculta_group_anchors'] = [];
    if (is_array($variables['groups'] ?? NULL)) {
      foreach (array_keys($variables['groups']) as $groupName) {
        $variables['aculta_group_anchors'][$groupName] = 'grupo-' . Html::getClass((string) $groupName);
      }
    }
    if (!is_array($variables['groups'] ?? NULL)) {
      return;
    }

    // Ordem fixa dos cartões de post do Fórum (grupo "Comunidade"): postagem de abertura, cartão, cartão espelhado.
    // Só essas três posições são reordenadas; os demais componentes do grupo mantêm a posição original.
    $order = [
      'aculta420_v2:forum-opening-post',
      'aculta420_v2:forum-post-card',
      'aculta420_v2:forum-reply-mirror',
    ];
    $group = 'Comunidade';
    if (isset($variables['groups'][$group]) && is_array($variables['groups'][$group])) {
      $items = $variables['groups'][$group];
      // Cartões do Fórum ordenados, para ocupar as mesmas posições que já tinham no grupo.
      $posts = array_intersect_key($items, array_flip($order));
      uksort($posts, static fn(string $a, string $b): int => array_search($a, $order, TRUE) <=> array_search($b, $order, TRUE));
      $reordered = [];
      foreach ($items as $key => $item) {
        if (in_array($key, $order, TRUE)) {
          $postKey = array_key_first($posts);
          $reordered[$postKey] = $posts[$postKey];
          unset($posts[$postKey]);
        }
        else {
          $reordered[$key] = $item;
        }
      }
      $variables['groups'][$group] = $reordered;
    }
  }

  /**
   * Hydrates custom story props on detail without clearing discovery caches.
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
