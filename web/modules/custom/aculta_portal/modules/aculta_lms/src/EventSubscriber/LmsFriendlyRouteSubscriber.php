<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\EventSubscriber;

use Drupal\aculta_lms\Routing\LmsFriendlySlugConverter;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Serves LMS lesson and activity pages under Portuguese course and lesson slugs.
 *
 * Parameter converters resolve the Group course and ACULTA lesson/activity
 * positions consumed by the answer submission form.
 */
final class LmsFriendlyRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $lesson_route = $collection->get('aculta_lms.lesson_view');
    if ($lesson_route !== NULL) {
      $lesson_route->setPath('/curso/{group}/{lesson_delta}');
      $lesson_route->setOption('parameters', [
        'group' => ['type' => LmsFriendlySlugConverter::TYPE_COURSE],
        'lesson_delta' => ['type' => LmsFriendlySlugConverter::TYPE_LESSON],
      ]);
    }

    $route = $collection->get('aculta_lms.answer_submission');
    if ($route === NULL) {
      return;
    }
    $route->setPath('/curso/{group}/{lesson_delta}/{activity_delta}');
    $route->setOption('parameters', [
      'group' => ['type' => LmsFriendlySlugConverter::TYPE_COURSE],
      'lesson_delta' => ['type' => LmsFriendlySlugConverter::TYPE_LESSON],
      'activity_delta' => ['type' => LmsFriendlySlugConverter::TYPE_ACTIVITY],
    ]);
  }

}
