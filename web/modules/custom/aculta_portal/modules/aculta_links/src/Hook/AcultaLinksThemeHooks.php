<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registers the neutral public microsite render contract. */
final class AcultaLinksThemeHooks {

  /**
 * Declares the template used by public micropages. */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'aculta_links_page' => [
        'variables' => [
          'title' => NULL,
          'description' => NULL,
          'links' => [],
          'has_links' => FALSE,
        ],
        'template' => 'aculta-links-page',
      ],
    ];
  }

}
