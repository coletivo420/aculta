<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;

/**
 * Applies the explicit color mode resolved by ACULTA Portal.
 */
final class ColorModeHooks {

  /**
   * Applies the Portal preference to the root element for the token stylesheet.
   *
   * Automatic system detection is intentionally disabled in this theme. The
   * legacy selector value `auto` resolves to light until the Portal contract is
   * changed; the existing light/dark selector remains the source of choice.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $mode = ($variables['aculta_color_mode'] ?? NULL) === 'dark' ? 'dark' : 'light';
    $attributes = $variables['html_attributes'] ?? NULL;
    if ($attributes instanceof Attribute) {
      $attributes->setAttribute('data-theme', $mode);
    }
  }

}
