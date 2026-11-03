<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\EventSubscriber;

use Drupal\aculta_lms\Routing\LmsFriendlySlugConverter;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Serves LMS lesson and activity pages under Portuguese course and lesson slugs.
 *
 * The LMS controller keeps its signature; the parameter converters turn the slugs
 * into the course entity and the lesson and activity positions it expects. The old
 * numeric path is not kept as an alias (see DT-P21).
 */
final class LmsFriendlyRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('lms.group.answer_form');
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
