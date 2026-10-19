<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Adds the current-enrollment check to private LMS answer downloads. */
final class UploadedAnswerRouteSubscriber extends RouteSubscriberBase {

  /** {@inheritdoc} */
  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('lms_file_upload.answer_file_download');
    if ($route !== NULL) {
      $route->setPath('/resposta/{lms_answer}/baixar-arquivo');
      $route->setRequirement('_custom_access', 'aculta_lms.uploaded_answer_access::access');
    }
  }

}
