<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registro dos templates do fluxo de doação. O módulo entrega dados neutros; o tema escolhe a apresentação.
 */
class DonationThemeHooks {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'aculta_donation_quick' => [
        'variables' => [
          'links' => [],
        ],
      ],
      'aculta_donation_donate' => [
        'variables' => [
          'label' => NULL,
          'href' => NULL,
        ],
      ],
      'aculta_donation_back' => [
        'variables' => [
          'label' => NULL,
          'href' => NULL,
        ],
      ],
      'aculta_donation_summary' => [
        'variables' => [
          'content' => NULL,
          'order_item' => NULL,
          'attributes' => NULL,
        ],
      ],
      'aculta_donation_summary_container' => [
        'variables' => [
          'content' => NULL,
          'attributes' => NULL,
        ],
      ],
      'commerce_checkout_pane__commerce_donation_pane' => [
        'base hook' => 'commerce_checkout_pane',
      ],
      'commerce_checkout_pane__commerce_memorial_pane' => [
        'base hook' => 'commerce_checkout_pane',
      ],
    ];
  }

}
