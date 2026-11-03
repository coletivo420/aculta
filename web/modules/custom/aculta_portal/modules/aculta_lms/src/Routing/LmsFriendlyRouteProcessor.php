<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\RouteProcessor\OutboundRouteProcessorInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\Routing\Route;

/**
 * Replaces LMS positions with friendly slugs when a URL is generated.
 *
 * Runs before the default outbound processors while the course is available.
 * Values that are already slugs are left unchanged.
 */
final class LmsFriendlyRouteProcessor implements OutboundRouteProcessorInterface {

  private const ROUTE = 'lms.group.answer_form';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LmsFriendlySlugs $slugs,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processOutbound($route_name, Route $route, array &$parameters, ?BubbleableMetadata $bubbleable_metadata = NULL): void {
    if ($route_name !== self::ROUTE || !isset($parameters['group'])) {
      return;
    }
    $course = $this->course($parameters['group']);
    if ($course === NULL) {
      return;
    }
    $bubbleable_metadata?->addCacheableDependency($course);

    $lesson = NULL;
    if (isset($parameters['lesson_delta']) && $this->isPosition($parameters['lesson_delta'])) {
      $lessonDelta = (int) $parameters['lesson_delta'];
      $lesson = $this->slugs->lessonAt($course, $lessonDelta);
      $parameters['lesson_delta'] = $this->slugs->lessonSlug($course, $lessonDelta) ?? $parameters['lesson_delta'];
    }
    if ($lesson !== NULL) {
      $bubbleable_metadata?->addCacheableDependency($lesson);
      if (isset($parameters['activity_delta']) && $this->isPosition($parameters['activity_delta'])) {
        $parameters['activity_delta'] = $this->slugs->activitySlug($lesson, (int) $parameters['activity_delta']) ?? $parameters['activity_delta'];
      }
    }

    $parameters['group'] = $this->slugs->courseSlug($course) ?? $parameters['group'];
  }

  /**
   * Accepts a loaded course or a course ID; anything else is left to Core.
   */
  private function course(mixed $value): ?GroupInterface {
    if ($value instanceof GroupInterface && $value->bundle() === LmsFriendlySlugs::COURSE_BUNDLE) {
      return $value;
    }
    if (!$this->isPosition($value)) {
      return NULL;
    }
    $course = $this->entityTypeManager->getStorage('group')->load($value);
    return $course instanceof GroupInterface && $course->bundle() === LmsFriendlySlugs::COURSE_BUNDLE ? $course : NULL;
  }

  /**
   * Determines whether a route value is a numeric position.
   */
  private function isPosition(mixed $value): bool {
    return is_int($value) || (is_string($value) && ctype_digit($value));
  }

}
