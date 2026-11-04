<?php

declare(strict_types=1);

namespace Drupal\aculta_linkweed\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registers the neutral public microsite render contract. */
final class AcultaLinksThemeHooks {

  /**
 * Declares the template used by public micropages. */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'aculta_linkweed_page' => [
        'variables' => [
          'title' => NULL,
          'description' => NULL,
          'owner_identity' => [],
          'theme_preset' => 'classic',
          'links' => [],
          'has_links' => FALSE,
        ],
        'template' => 'aculta-links-page',
      ],
      'aculta_linkweed_landing' => [
        'variables' => [
          'title' => NULL,
          'description' => NULL,
          'links' => [],
          'has_links' => FALSE,
          'theme_preset' => 'classic',
        ],
        'template' => 'aculta-links-landing',
      ],
    ];
  }

}
