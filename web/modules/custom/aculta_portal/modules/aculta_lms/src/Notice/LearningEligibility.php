<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Notice;

use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRelationshipInterface;
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
  public function courseForAnswer(EntityInterface $answer): ?GroupInterface {
    assert($answer instanceof ContentEntityInterface);
    if ($answer->getEntityTypeId() !== 'aculta_lms_answer') {
      return NULL;
    }
    $activity = $this->entityTypeManager->getStorage('aculta_lms_activity')->load((int) $answer->get('activity_id')->value);
    if (!$activity instanceof ContentEntityInterface) {
      return NULL;
    }
    $lesson = $this->entityTypeManager->getStorage('aculta_lms_lesson')->load((int) $activity->get('lesson_id')->value);
    if (!$lesson instanceof ContentEntityInterface) {
      return NULL;
    }
    $course = $this->entityTypeManager->getStorage('group')->load((int) $lesson->get('course_id')->value);
    return $course instanceof GroupInterface && $course->bundle() === 'lms_course' ? $course : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function coursesForContent(EntityInterface $content): array {
    assert($content instanceof ContentEntityInterface);
    if ($content instanceof GroupInterface && $content->bundle() === 'lms_course') {
      return [$content];
    }
    $lesson = NULL;
    if ($content->getEntityTypeId() === 'aculta_lms_lesson') {
      $lesson = $content;
    }
    elseif ($content->getEntityTypeId() === 'aculta_lms_activity') {
      $lesson = $this->entityTypeManager->getStorage('aculta_lms_lesson')->load((int) $content->get('lesson_id')->value);
    }
    if (!$lesson instanceof ContentEntityInterface) {
      return [];
    }
    $course = $this->entityTypeManager->getStorage('group')->load((int) $lesson->get('course_id')->value);
    return $course instanceof GroupInterface && $course->bundle() === 'lms_course' ? [$course] : [];
  }

  /**
   * {@inheritdoc} */
  public function isContentAvailable(EntityInterface $content): bool {
    assert($content instanceof ContentEntityInterface);
    if ($content instanceof GroupInterface && $content->bundle() === 'lms_course') {
      return $content->isPublished();
    }
    if ($content->getEntityTypeId() === 'aculta_lms_lesson') {
      return (bool) $content->get('published')->value;
    }
    if ($content->getEntityTypeId() === 'aculta_lms_activity') {
      $lesson = $this->entityTypeManager->getStorage('aculta_lms_lesson')->load((int) $content->get('lesson_id')->value);
      return $lesson instanceof ContentEntityInterface && (bool) $lesson->get('published')->value;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCurrentEnrollment(GroupInterface $course, AccountInterface $account): bool {
    if (!$course->hasPermission('take course', $account)) {
      return FALSE;
    }
    foreach ($course->getRelationships('lms_classes') as $relationship) {
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
   * {@inheritdoc} */
  public function courseEnrollmentCacheTags(GroupInterface $course): array {
    $tags = ['group_relationship_list:plugin:lms_classes:group:' . $course->id()];
    foreach ($course->getRelationships('lms_classes') ?? [] as $relationship) {
      $class = $relationship instanceof GroupRelationshipInterface ? $relationship->getEntity() : NULL;
      if ($class instanceof GroupInterface && $class->bundle() === 'lms_class') {
        $tags[] = 'group_relationship_list:plugin:group_membership:group:' . $class->id();
      }
    }

    return array_values(array_unique($tags));
  }

  /**
   * {@inheritdoc}
   */
  public function memberCanTakeCourseInClass(GroupInterface $class, AccountInterface $account): bool {
    $course = $this->deadlines->courseForClass($class);
    if (!$course->hasPermission('take course', $account)) {
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
  public function courseForClass(GroupInterface $class): ?GroupInterface {
    return $this->deadlines->courseForClass($class);
  }

  /**
   * {@inheritdoc}
   */
  public function activityBelongsToCourse(ContentEntityInterface $activity, GroupInterface $course): bool {
    return $this->deadlines->activityBelongsToCourse($activity, $course);
  }

}
