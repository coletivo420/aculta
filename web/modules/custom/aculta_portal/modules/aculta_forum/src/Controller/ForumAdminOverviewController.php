<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Builds the central administration page for the public ACULTA Forum.
 */
final class ForumAdminOverviewController {

  /**
   * Renders the canonical landing page for Forum administration.
   *
   * @return array
   *   A minimal render array identifying the central Forum administration path.
   */
  public function overview(): array {
    return [
      '#plain_text' => new TranslatableMarkup('Painel central do Fórum Growroom420.'),
    ];
  }

  /**
   * Sends Forum administrative tool paths to the central Forum panel.
   */
  public function redirectToOverview(): LocalRedirectResponse {
    return new LocalRedirectResponse('/painel-administrativo/forum', 302);
  }

}
