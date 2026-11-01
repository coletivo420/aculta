<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Presentation;

/**
 * Contract for the Wiki search form injected into the Portal presentation.
 *
 * The implementation lives in the optional `aculta_wiki` submodule. The Portal
 * only depends on this interface and injects the service optionally, so the
 * presentation keeps working when the submodule is absent or disabled.
 */
interface WikiSearchFormBuilderInterface {

  /**
   * Returns a render array for the canonical Wiki search route.
   */
  public function build(): array;

}
