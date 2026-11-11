<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hooks for the Events module.
 */
final class UpcomingEventsThemeHooks {

  /**
   * Registers the neutral upcoming-events block template.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'aculta_events_upcoming_block' => [
        'variables' => [
          'items' => [],
          'empty_text' => '',
        ],
      ],
    ];
  }

}
