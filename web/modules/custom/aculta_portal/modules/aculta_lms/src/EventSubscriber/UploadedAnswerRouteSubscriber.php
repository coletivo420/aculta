<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds the ACULTA enrollment check to a legacy file-download route.
 *
 * The route is optional: `lms_file_upload` is no longer a dependency of
 * `aculta_lms`, but old installations may still expose the route while they
 * complete their migration. Its absence must not affect route rebuilding.
 */
final class UploadedAnswerRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc} */
  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('lms_file_upload.answer_file_download');
    if ($route !== NULL) {
      $route->setPath('/resposta/{lms_answer}/baixar-arquivo');
      $route->setRequirement('_custom_access', 'aculta_lms.uploaded_answer_access::access');
    }
  }

}
