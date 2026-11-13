<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
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
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $viewMode): void {
    if (!$entity instanceof EntityPublishedInterface || $entity->isPublished()) {
      return;
    }

    $settings = $this->themeSettings
      ->getSetting(UiStylesEntityStatusInterface::UNPUBLISHED_CLASSES_THEME_SETTING_KEY) ?? [];
    $selected = $settings['selected'] ?? [];
    if (!in_array('aculta-content-status--unpublished', $selected, TRUE)) {
      return;
    }

    $build['#attached']['library'][] = 'aculta420_v2/unpublished-preview';
  }

}
