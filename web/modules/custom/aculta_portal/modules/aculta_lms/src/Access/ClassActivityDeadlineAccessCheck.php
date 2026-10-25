<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;

/**
 * Checks educator access to a class-specific activity deadline. */
final class ClassActivityDeadlineAccessCheck {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Grants access only to an educator who can grade the class's course.
   */
  public function access(CourseInterface $course, GroupInterface $group, ActivityInterface $activity): AccessResult {
    $classCourse = $this->courseForClass($group);
    if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course'
      || !$classCourse instanceof CourseInterface || (string) $course->id() !== (string) $classCourse->id()
      || !$this->activityBelongsToCourse($activity, $classCourse)) {
      $result = AccessResult::forbidden()
        ->addCacheableDependency($course)
        ->addCacheableDependency($group)
        ->addCacheableDependency($activity)
        // A missing parent-course relationship can be added later. Invalidate
        // this negative access result when that Group relationship is saved.
        ->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . $group->id()]);
      if ($classCourse instanceof CourseInterface) {
        // Course lesson references can also make the activity eligible later.
        $result->addCacheableDependency($classCourse);
      }
      return $result;
    }
    $account = $this->currentUser;
    return AccessResult::allowedIf(
      $account->hasPermission('administer lms') || $classCourse->hasPermission('grade students', $account),
    )
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheTags([
        'group_relationship_list:plugin:lms_classes:group:' . $classCourse->id(),
        'group_relationship_list:plugin:group_membership:group:' . $group->id(),
      ])
      ->addCacheableDependency($group)
      ->addCacheableDependency($activity)
      ->addCacheableDependency($classCourse);
  }

  /**
   * Finds the parent course relationship for a class. */
  public function courseForClass(GroupInterface $class): ?CourseInterface {
    if ($class->bundle() !== 'lms_class') {
      return NULL;
    }
    $relationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'entity_id' => $class->id(),
      'plugin_id' => 'lms_classes',
    ]);
    foreach ($relationships as $relationship) {
      $course = $relationship instanceof GroupRelationshipInterface ? $relationship->getGroup() : NULL;
      if ($course instanceof CourseInterface && $course->bundle() === 'lms_course') {
        return $course;
      }
    }
    return NULL;
  }

  /**
   * Checks that the activity is reachable through one of the course lessons. */
  public function activityBelongsToCourse(ActivityInterface $activity, CourseInterface $course): bool {
    if (!$activity instanceof EntityPublishedInterface || !$activity->isPublished()) {
      return FALSE;
    }
    foreach ($course->get(CourseInterface::LESSONS)->referencedEntities() as $lesson) {
      if (!$lesson instanceof EntityPublishedInterface || !$lesson->isPublished() || !$lesson->hasField('activities')) {
        continue;
      }
      foreach ($lesson->get('activities')->referencedEntities() as $courseActivity) {
        if ((string) $courseActivity->id() === (string) $activity->id()) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
