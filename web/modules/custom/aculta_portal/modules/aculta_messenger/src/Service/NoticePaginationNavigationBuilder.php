<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

/**
 * Builds the accessible navigation wrapper for notice pagination links. */
final class NoticePaginationNavigationBuilder {

  /**
   * Builds a navigation landmark, or nothing when the list has no links.
   *
   * @param array<mixed> $links
   *   Dados de entrada (links).
   * @param mixed $label
   *   Contextual accessible label for the navigation landmark.
   *
   * @return array<mixed>
   *   Dados retornados.
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
