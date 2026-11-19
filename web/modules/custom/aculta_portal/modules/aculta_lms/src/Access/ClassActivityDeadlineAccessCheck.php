<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;

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
  public function access(GroupInterface $course, GroupInterface $group, ContentEntityInterface $activity): AccessResult {
    $classCourse = $this->courseForClass($group);
    if ($course->bundle() !== 'lms_course'
      || !$classCourse instanceof GroupInterface || (string) $course->id() !== (string) $classCourse->id()
      || !$this->activityBelongsToCourse($activity, $classCourse)) {
      $result = AccessResult::forbidden()
        ->addCacheableDependency($course)
        ->addCacheableDependency($group)
        ->addCacheableDependency($activity)
        // A missing parent-course relationship can be added later. Invalidate
        // this negative access result when that Group relationship is saved.
        ->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . $group->id()]);
      if ($classCourse instanceof GroupInterface) {
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
   * Checks access to the published course that owns a class.
   *
   * Keep Drupal LMS course semantics inside this adapter. Consumers receive
   * only a cacheable access result and do not need CourseInterface.
   */
  public function courseAccessForClass(GroupInterface $class, AccountInterface $account): AccessResult {
    $cacheTags = ['group_relationship_list:plugin:lms_classes:entity:' . $class->id()];
    if ($class->bundle() !== 'lms_class') {
      return AccessResult::forbidden()
        ->cachePerUser()
        ->addCacheContexts(['user.permissions', 'user.group_permissions'])
        ->addCacheableDependency($class)
        ->addCacheTags($cacheTags);
    }

    $course = $this->courseForClass($class);
    if (!$course instanceof GroupInterface) {
      return AccessResult::forbidden()
        ->cachePerUser()
        ->addCacheContexts(['user.permissions', 'user.group_permissions'])
        ->addCacheableDependency($class)
        ->addCacheTags($cacheTags);
    }
    if (!$course->isPublished()) {
      return AccessResult::forbidden()
        ->cachePerUser()
        ->addCacheContexts(['user.permissions', 'user.group_permissions'])
        ->addCacheableDependency($class)
        ->addCacheableDependency($course)
        ->addCacheTags($cacheTags);
    }

    return AccessResult::allowedIf($course->hasPermission('take course', $account))
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheTags([
        ...$cacheTags,
        'group_relationship_list:plugin:group_membership:group:' . $course->id(),
      ])
      ->addCacheableDependency($class)
      ->addCacheableDependency($course);
  }

  /**
   * Finds the parent course relationship for a class.
   */
  public function courseForClass(GroupInterface $class): ?GroupInterface {
    if ($class->bundle() !== 'lms_class') {
      return NULL;
    }
    $relationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'entity_id' => $class->id(),
      'plugin_id' => 'lms_classes',
    ]);
    foreach ($relationships as $relationship) {
      $course = $relationship instanceof GroupRelationshipInterface ? $relationship->getGroup() : NULL;
      if ($course instanceof GroupInterface && $course->bundle() === 'lms_course') {
        return $course;
      }
    }
    return NULL;
  }

  /**
   * Checks that the activity is reachable through one of the course lessons. */
  public function activityBelongsToCourse(ContentEntityInterface $activity, GroupInterface $course): bool {
    if ($activity->getEntityTypeId() !== 'aculta_lms_activity' || !$activity->hasField('lesson_id')) {
      return FALSE;
    }
    $lessonId = $activity->get('lesson_id')->first()?->getValue()['value'] ?? NULL;
    $lesson = $this->entityTypeManager->getStorage('aculta_lms_lesson')->load((int) $lessonId);
    if (!$lesson instanceof ContentEntityInterface || !$lesson->hasField('published') || !$lesson->hasField('course_id')) {
      return FALSE;
    }
    $published = $lesson->get('published')->first()?->getValue()['value'] ?? FALSE;
    $courseId = $lesson->get('course_id')->first()?->getValue()['value'] ?? NULL;
    if (!(bool) $published || (string) $courseId !== (string) $course->id()) {
      return FALSE;
    }
    return TRUE;
  }

}
