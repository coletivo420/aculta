<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Declares the neutral button theme hook.
 */
final class PortalButtonThemeHooks {

  /**
   * Implements hook_theme().
   *
   * @return array<mixed>
   *   Dados retornados.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'portal_button' => [
        'variables' => [
          'props' => [],
          'attributes' => NULL,
          'component' => NULL,
        ],
      ],
    ];
  }

}
