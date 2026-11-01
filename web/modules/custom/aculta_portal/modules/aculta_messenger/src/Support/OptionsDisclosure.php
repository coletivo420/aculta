<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Support;

use Drupal\Core\Template\Attribute;

/**
 * The "Opções" accordion: a native details element whose summary is the label with the menu icon.
 *
 * The conversation drawer and the public profile use the same box. Every part is an element the theme maps by
 * data-portal-ui (see PortalUiHooks), so no presentation class is written here.
 */
final class OptionsDisclosure {

  /**
   * @param string $label
   *   The visible and accessible label ("Opções").
   * @param array<string, mixed> $panel
   *   The menu items (render arrays).
   * @param bool $open
   *   Open at first render (after an action the menu stays open, so its result is what the member sees).
   *
   * @return array<string, mixed>
   *   The render array: a core details element, whose summary is the icon and the label.
   */
  public static function build(string $label, array $panel, bool $open = FALSE): array {
    return [
      '#type' => 'details',
      '#open' => $open,
      '#attributes' => ['data-portal-ui' => 'messenger-options'],
      '#summary_attributes' => new Attribute(['data-portal-ui' => 'messenger-options-summary', 'aria-label' => $label]),
      '#title' => [
        'icon' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'i',
          '#element_attributes' => new Attribute(['data-portal-ui' => 'messenger-options-icon', 'aria-hidden' => 'true']),
          '#value' => '',
        ],
        'label' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute(['data-portal-ui' => 'messenger-options-label']),
          '#value' => $label,
        ],
      ],
      'panel' => ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'messenger-options-panel']] + $panel,
    ];
  }

}
