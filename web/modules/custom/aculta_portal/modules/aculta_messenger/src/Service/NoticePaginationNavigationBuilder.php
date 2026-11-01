<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

/**
 * Builds the accessible navigation wrapper for notice pagination links. */
final class NoticePaginationNavigationBuilder {

  /**
   * Builds a navigation landmark, or nothing when the list has no links.
   *
   * @param array $links
   *   Pager links built by PortalLinkElement.
   * @param mixed $label
   *   Contextual accessible label for the navigation landmark.
   *
   * @return array
   *   Render array for the navigation landmark, or an empty array.
   */
  public static function build(array $links, mixed $label): array {
    if ($links === []) {
      return [];
    }
    return [
      '#type' => 'container',
      '#attributes' => [
        'role' => 'navigation',
        'aria-label' => $label,
      ],
      'links' => [
        '#theme' => 'item_list',
        '#items' => $links,
      ],
    ];
  }

}
