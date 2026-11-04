<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Keeps Core jQuery ahead of dependent libraries with negative asset weights.
 */
final class JqueryAssetOrderingHooks {

  /**
   * Moves the non-aggregated Core jQuery asset before dependent aggregates.
   *
   * Some installed jQuery UI libraries assign their assets weight -11 while
   * declaring Core jQuery as a library dependency. Since Core intentionally
   * excludes jquery.min.js from aggregates, those UI files could otherwise be
   * emitted before the separate jQuery script and fail during page startup.
   *
   * @param array<string, mixed> $libraries
   *   Discovered library definitions for an extension.
   * @param string $extension
   *   The extension providing the libraries.
   */
  #[Hook('library_info_alter')]
  public function placeCoreJqueryBeforeDependents(array &$libraries, string $extension): void {
    if ($extension !== 'core' || !isset($libraries['jquery']['js'])) {
      return;
    }
    foreach ($libraries['jquery']['js'] as &$asset) {
      if (is_array($asset)) {
        $asset['weight'] = -12;
      }
    }
    unset($asset);
  }

}
