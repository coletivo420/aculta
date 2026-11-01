<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\user\UserInterface;

/**
 * Enforces D-15 access for events linked to LMS classes.
 */
final class EventClassAccess {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly ClassActivityDeadlineAccessCheck $classAccess,
  ) {}

  /**
   * Checks membership, published class/course, and course access.
   */
  public function access(EntityInterface $event, AccountInterface $account): AccessResult {
    $class = $event->get('field_turma')->entity;
    if (!$class instanceof GroupInterface || $class->bundle() !== 'lms_class' || !$class->isPublished()) {
      return $this->result(FALSE, $event, $class instanceof GroupInterface ? $class : NULL, NULL);
    }

    $course = $this->classAccess->courseForClass($class);
    if (!$course instanceof CourseInterface || !$course->isPublished()) {
      return $this->result(FALSE, $event, $class, $course instanceof CourseInterface ? $course : NULL);
    }

    $membership = NULL;
    if ($account->isAuthenticated()) {
      foreach (GroupMembership::loadByGroup($class) as $candidate) {
        $member = $candidate->getEntity();
        if ($member instanceof UserInterface && $member->isActive()
          && (string) $member->id() === (string) $account->id()) {
          $membership = $candidate;
          break;
        }
      }
    }

    $courseAccess = $course->takeAccess($account);
    $result = AccessResult::forbiddenIf($membership === NULL || !$courseAccess->isAllowed())
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheTags([
        'group_relationship_list:plugin:group_membership:group:' . $class->id(),
        'group_relationship_list:plugin:group_membership:group:' . $course->id(),
        'group_relationship_list:plugin:lms_classes:entity:' . $class->id(),
      ])
      ->addCacheableDependency($event)
      ->addCacheableDependency($class)
      ->addCacheableDependency($course)
      ->addCacheableDependency($courseAccess);

    if ($membership !== NULL) {
      $result->addCacheableDependency($membership);
    }

    return $result;
  }

  /**
   * Creates a user-specific result for unusable class/course references.
   */
  private function result(bool $allowed, EntityInterface $event, ?GroupInterface $class, ?CourseInterface $course): AccessResult {
    $result = AccessResult::forbiddenIf(!$allowed)
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheableDependency($event);
    if ($class !== NULL) {
      $result
        ->addCacheableDependency($class)
        ->addCacheTags(['group_relationship_list:plugin:group_membership:group:' . $class->id()]);
    }
    if ($course !== NULL) {
      $result
        ->addCacheableDependency($course)
        ->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . ($class?->id() ?? '')]);
    }
    return $result;
  }

}
