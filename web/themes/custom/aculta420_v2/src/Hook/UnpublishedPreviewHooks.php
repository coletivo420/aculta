<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\ui_styles_entity_status\UiStylesEntityStatusInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Loads the editorial preview cue only for unpublished entities using it.
 */
final class UnpublishedPreviewHooks {

  public function __construct(
    #[Autowire(service: ThemeSettingsProvider::class)]
    private readonly ThemeSettingsProvider $themeSettings,
  ) {}

  /**
   * Attaches the preview style when UI Styles selected the ACULTA marker.
   *
   * @param array<string, mixed> $variables
   *   Preprocess variables for the node template.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->isPublished()) {
      return;
    }

    $settings = $this->themeSettings
      ->getSetting(UiStylesEntityStatusInterface::UNPUBLISHED_CLASSES_THEME_SETTING_KEY) ?? [];
    if (!is_array($settings)) {
      return;
    }
    $selected = $settings['selected'] ?? [];
    if (!is_array($selected) || !in_array('aculta-content-status--unpublished', $selected, TRUE)) {
      return;
    }

    $elements = $variables['elements'] ?? NULL;
    if (!is_array($elements)) {
      return;
    }
    $attached = is_array($elements['#attached'] ?? NULL) ? $elements['#attached'] : [];
    $libraries = is_array($attached['library'] ?? NULL) ? $attached['library'] : [];
    $libraries[] = 'aculta420_v2/unpublished-preview';
    $attached['library'] = $libraries;
    $elements['#attached'] = $attached;
    $variables['elements'] = $elements;
  }

}
