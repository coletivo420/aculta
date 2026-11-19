<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Keeps Core editor prerequisites ahead of dependent aggregates.
 */
final class CoreEditorAssetOrderingHooks {

  /**
   * Moves unaggregated prerequisites before dependent aggregates.
   *
   * Core leaves jQuery and the CKEditor DLL outside aggregates. The installed
   * jQuery UI files use weight -11, while CKEditor plugin bundles reference
   * the DLL at their default weight. Sorting assets by weight can therefore
   * place either prerequisite after its dependent bundle despite library
   * dependencies. These explicit weights preserve dependency order.
   *
   * @param array<string, mixed> $libraries
   *   Discovered library definitions for an extension.
   * @param string $extension
   *   The extension providing the libraries.
   */
  #[Hook('library_info_alter')]
  public function placeCoreEditorPrerequisites(array &$libraries, string $extension): void {
    if ($extension !== 'core') {
      return;
    }
    $weights = [
      'jquery' => -100,
      'ckeditor5.translations' => -91,
      'ckeditor5' => -90,
    ];
    foreach ($weights as $library => $weight) {
      if (!isset($libraries[$library]['js'])) {
        continue;
      }
      foreach ($libraries[$library]['js'] as &$asset) {
        if (is_array($asset)) {
          $asset['weight'] = $weight;
        }
      }
      unset($asset);
    }
  }

}
