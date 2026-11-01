<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Notice;

use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\user\UserInterface;

/**
 * Educational eligibility rules used by producers and Messenger consumers.
 */
final class LearningEligibility implements LearningEligibilityInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClassActivityDeadlineManager $deadlines,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function courseForAnswer(AnswerInterface $answer): ?CourseInterface {
    $lessonStatus = $answer->get('lesson_status')->entity;
    if (!$lessonStatus instanceof LessonStatusInterface) {
      return NULL;
    }
    $courseStatus = $lessonStatus->get('course_status')->entity;
    if (!$courseStatus instanceof CourseStatusInterface) {
      return NULL;
    }
    $course = $courseStatus->get(CourseStatusInterface::COURSE_FIELD)->entity;
    return $course instanceof CourseInterface && $course->bundle() === 'lms_course'
      ? $course
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function coursesForContent(EntityPublishedInterface $content): array {
    if ($content instanceof GroupInterface && $content->bundle() === 'lms_course') {
      return [$content];
    }
    $lessonIds = [];
    if ($content instanceof LessonInterface) {
      $lessonIds[] = (int) $content->id();
    }
    elseif ($content instanceof ActivityInterface) {
      $lessonIds = array_map('intval', array_keys($this->entityTypeManager->getStorage('lms_lesson')->getQuery()
        ->accessCheck(FALSE)
        ->condition(LessonInterface::ACTIVITIES . '.target_id', (int) $content->id())
        ->execute()));
    }
    if ($lessonIds === []) {
      return [];
    }
    $courseIds = $this->entityTypeManager->getStorage('group')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'lms_course')
      ->condition('lessons.target_id', $lessonIds, 'IN')
      ->execute();
    return array_values(array_filter(
      $this->entityTypeManager->getStorage('group')->loadMultiple($courseIds),
      static fn ($course): bool => $course instanceof GroupInterface,
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function hasCurrentEnrollment(CourseInterface $course, AccountInterface $account): bool {
    if (!$course->hasPermission('take course', $account)) {
      return FALSE;
    }
    $classRelationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'entity_id' => $course->id(),
      'plugin_id' => 'lms_classes',
    ]);
    foreach ($classRelationships as $relationship) {
      $class = $relationship instanceof GroupRelationshipInterface ? $relationship->getEntity() : NULL;
      if ($class === NULL || $class->bundle() !== 'lms_class') {
        continue;
      }
      foreach (GroupMembership::loadByGroup($class) as $membership) {
        $member = $membership->getEntity();
        if ($membership->getPluginId() === 'group_membership'
          && $member instanceof UserInterface && $member->isActive()
          && (int) $member->id() === (int) $account->id()) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function memberCanTakeCourseInClass(GroupInterface $class, AccountInterface $account): bool {
    $course = $this->deadlines->courseForClass($class);
    if (!$course instanceof CourseInterface || !$course->hasPermission('take course', $account)) {
      return FALSE;
    }
    foreach (GroupMembership::loadByGroup($class) as $membership) {
      $member = $membership->getEntity();
      if ($member instanceof UserInterface && $member->isActive()
        && (int) $member->id() === (int) $account->id()
        && $membership->getPluginId() === 'group_membership') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function courseForClass(GroupInterface $class): ?CourseInterface {
    return $this->deadlines->courseForClass($class);
  }

  /**
   * {@inheritdoc}
   */
  public function activityBelongsToCourse(ActivityInterface $activity, CourseInterface $course): bool {
    return $this->deadlines->activityBelongsToCourse($activity, $course);
  }

}
