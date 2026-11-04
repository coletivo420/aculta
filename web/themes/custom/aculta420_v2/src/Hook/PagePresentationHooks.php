<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Maps the Portal's neutral Domain Presentation contract to theme variables.
 */
final class PagePresentationHooks {

  /**
   * Exposes the legacy homepage showcase prepared by ACULTA Portal.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $links = $variables['domain_presentation']['regions']['showcase'] ?? [];
    if (!is_array($links)) {
      $variables['multidomain_network_links'] = [];
      return;
    }

    $variables['multidomain_network_links'] = array_values(array_filter(
      $links,
      static fn (mixed $link): bool => is_array($link)
        && is_string($link['title'] ?? NULL)
        && $link['title'] !== ''
        && is_string($link['url'] ?? NULL)
        && $link['url'] !== '',
    ));
  }

}
