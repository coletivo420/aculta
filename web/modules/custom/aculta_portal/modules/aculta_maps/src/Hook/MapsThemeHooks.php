<?php

declare(strict_types=1);

namespace Drupal\aculta_maps\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registers neutral map popup templates for theme-level presentation.
 */
final class MapsThemeHooks {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'aculta_maps_popup_template' => [
        'variables' => [
          'popup_id' => NULL,
          'popup' => NULL,
        ],
      ],
      'aculta_maps_popup_content' => [
        'variables' => [
          'title' => NULL,
          'url' => NULL,
          'category' => NULL,
          'address' => NULL,
          'route_url' => NULL,
        ],
      ],
    ];
  }

}
