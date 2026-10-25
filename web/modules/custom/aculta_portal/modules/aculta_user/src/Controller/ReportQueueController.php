<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\LocalRedirectResponse;

/**
 * Redirects the retired Forum report queue path to the canonical admin page.
 */
final class ReportQueueController extends ControllerBase {

  /**
   * Redirects the Forum-owned administrative path to its central page.
   */
  public function view(): LocalRedirectResponse {
    return new LocalRedirectResponse('/painel-administrativo/forum', 302);
  }

}
