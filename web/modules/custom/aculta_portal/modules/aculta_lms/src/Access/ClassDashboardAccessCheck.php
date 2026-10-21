<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\group\Entity\GroupInterface;
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
  public function access(GroupInterface $group): AccessResult {
    return $this->dashboards->classDashboardAccess($group, $this->currentUser);
  }

}
