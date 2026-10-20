<?php

declare(strict_types=1);

namespace Drupal\aculta_user_activity_test\Controller;

/**
 * Supplies a route target so the account ActivityController can build its tabs.
 */
final class ActivityRouteController {

  /**
   * Returns an empty response; this route is used only to generate test URLs.
   */
  public function placeholder(): array {
    return ['#markup' => ''];
  }

}
