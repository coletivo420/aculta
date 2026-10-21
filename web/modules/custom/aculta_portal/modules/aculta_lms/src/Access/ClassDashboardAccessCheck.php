<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\group\Entity\GroupInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Checks educator access to an LMS class dashboard.
 */
final class ClassDashboardAccessCheck {

  public function __construct(
    private readonly LearningDashboardManager $dashboards,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Checks course-scoped educator access for the requested class.
   */
  public function access(CourseInterface $course, GroupInterface $group): AccessResult {
    $parents = $this->dashboards->courseForClass($group);
    if ($course->bundle() !== 'lms_course' || !$parents instanceof CourseInterface || (string) $parents->id() !== (string) $course->id()) {
      return AccessResult::forbidden()->addCacheableDependency($course)->addCacheableDependency($group)->setCacheMaxAge(0);
    }
    return $this->dashboards->classDashboardAccess($group, $this->currentUser)->addCacheableDependency($course);
  }

}
