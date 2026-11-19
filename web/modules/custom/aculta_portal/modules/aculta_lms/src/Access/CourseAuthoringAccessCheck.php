<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Checks educator authority over a course curriculum and its descendants.
 */
final class CourseAuthoringAccessCheck {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Grants curriculum access only to an educator of the matching course.
   */
  public function access(
    GroupInterface $course,
    ?ContentEntityInterface $lesson = NULL,
    ?ContentEntityInterface $activity = NULL,
  ): AccessResult {
    return $this->checkAccess($course, $lesson, $activity, TRUE);
  }

  /**
   * Grants curriculum ordering access without unlocking answered activities.
   *
   * Reordering changes presentation order only; it does not change question or
   * answer configuration, so historical answers remain safe to move.
   */
  public function accessForReorder(
    GroupInterface $course,
    ?ContentEntityInterface $lesson = NULL,
    ?ContentEntityInterface $activity = NULL,
  ): AccessResult {
    return $this->checkAccess($course, $lesson, $activity, FALSE);
  }

  /**
   * Checks the course ancestry and educator permission for an operation. */
  private function checkAccess(
    GroupInterface $course,
    ?ContentEntityInterface $lesson,
    ?ContentEntityInterface $activity,
    bool $lockAnsweredActivity,
  ): AccessResult {
    $validCourse = $course->bundle() === 'lms_course';
    $validLesson = $lesson === NULL
      || ($lesson->getEntityTypeId() === 'aculta_lms_lesson'
        && (int) $lesson->get('course_id')->value === (int) $course->id());
    $validActivity = $activity === NULL
      || ($activity->getEntityTypeId() === 'aculta_lms_activity'
        && $lesson instanceof ContentEntityInterface
        && (int) $activity->get('lesson_id')->value === (int) $lesson->id());
    // Once answers exist the question and answer key form part of their audit
    // context. Editing them in place would make the historical score opaque.
    $activityHasAnswers = $lockAnsweredActivity && $activity instanceof ContentEntityInterface
      && $this->entityTypeManager->getStorage('aculta_lms_answer')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('activity_id', (int) $activity->id())
        ->range(0, 1)
        ->count()
        ->execute() > 0;

    $allowed = $validCourse && $validLesson && $validActivity
      && (!$lockAnsweredActivity || !$activityHasAnswers)
      && ($this->currentUser->hasPermission('administer lms')
        || $course->hasPermission('grade students', $this->currentUser));

    $result = AccessResult::allowedIf($allowed)
      ->addCacheableDependency($course)
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheContexts(['user.group_permissions']);
    if ($activity instanceof ContentEntityInterface) {
      $result->setCacheMaxAge(0);
    }
    return $result;
  }

}
