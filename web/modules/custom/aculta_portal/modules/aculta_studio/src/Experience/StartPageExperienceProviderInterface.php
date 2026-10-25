<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

/**
 * Optional start page for a Studio experience that separates landing/work.
 */
interface StartPageExperienceProviderInterface {

  /**
   * Builds the authorized start page.
   *
   * @return array<string, mixed>
   *   Start page render array.
   */
  public function buildStart(): array;

}
