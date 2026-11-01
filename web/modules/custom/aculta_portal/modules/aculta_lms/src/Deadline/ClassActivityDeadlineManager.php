<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Deadline;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\user\UserInterface;

/**
 * Resolves deadlines stored on Group class to activity relationships. */
final class ClassActivityDeadlineManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClassActivityDeadlineAccessCheck $accessCheck,
  ) {}

  /**
   * Returns a visible deadline for a learner's current class in a course.
   *
   * If a learner has more than one current class for the same course, the
   * earliest deadline is shown so an active enrollment is never understated.
   */
  public function deadlineForLearner(CourseInterface $course, ActivityInterface $activity, AccountInterface $learner): ?GroupRelationshipInterface {
    if (!$this->accessCheck->activityBelongsToCourse($activity, $course)) {
      return NULL;
    }
    $matches = [];
    foreach ($course->getRelationships('lms_classes') as $courseClassRelationship) {
      $class = $courseClassRelationship instanceof GroupRelationshipInterface
        ? $courseClassRelationship->getEntity()
        : NULL;
      if (!$class instanceof GroupInterface || $class->bundle() !== 'lms_class' || !$class->isPublished()) {
        continue;
      }
      $isLearner = FALSE;
      foreach (GroupMembership::loadByGroup($class) as $membership) {
        $member = $membership->getEntity();
        if ($member instanceof UserInterface && $member->isActive()
          && (int) $member->id() === (int) $learner->id()
          && $course->hasPermission('take course', $learner)) {
          $isLearner = TRUE;
          break;
        }
      }
      if (!$isLearner) {
        continue;
      }
      foreach ($class->getRelationshipsByEntity($activity, 'lms_class_activity_due') as $deadline) {
        if (!$deadline instanceof GroupRelationshipInterface || $deadline->get('field_activity_due_at')->isEmpty()) {
          continue;
        }
        $matches[] = $deadline;
      }
    }
    usort($matches, static fn (GroupRelationshipInterface $a, GroupRelationshipInterface $b): int =>
      strcmp((string) $a->get('field_activity_due_at')->value, (string) $b->get('field_activity_due_at')->value)
    );
    return $matches[0] ?? NULL;
  }

  /**
   * Returns the parent course for a class.
   */
  public function courseForClass(GroupInterface $class): ?CourseInterface {
    return $this->accessCheck->courseForClass($class);
  }

  /**
   * Checks whether an activity belongs to a course through a published lesson. */
  public function activityBelongsToCourse(ActivityInterface $activity, CourseInterface $course): bool {
    return $this->accessCheck->activityBelongsToCourse($activity, $course);
  }

  /**
   * Returns the matching current relation for a class and activity.
   */
  public function deadlineForClass(GroupInterface $class, ActivityInterface $activity): ?GroupRelationshipInterface {
    $course = $this->courseForClass($class);
    if (!$course instanceof CourseInterface || !$this->accessCheck->activityBelongsToCourse($activity, $course)) {
      return NULL;
    }
    $relationships = $class->getRelationshipsByEntity($activity, 'lms_class_activity_due');
    $deadline = $relationships === [] ? NULL : reset($relationships);
    return $deadline instanceof GroupRelationshipInterface ? $deadline : NULL;
  }

}
