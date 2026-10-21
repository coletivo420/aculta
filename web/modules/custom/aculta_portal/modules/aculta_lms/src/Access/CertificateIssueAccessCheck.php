<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\group\Entity\GroupInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;

/** Checks contextual authority to issue or revoke certificates. */
final class CertificateIssueAccessCheck {

  public function __construct(private readonly AccountProxyInterface $currentUser) {}

  public function access(GroupInterface $group): AccessResult {
    if (!$group instanceof CourseInterface) {
      return AccessResult::forbidden()->addCacheableDependency($group);
    }
    $account = $this->currentUser;
    $allowed = $account->hasPermission('administer lms') || $group->hasPermission('grade students', $account);
    return AccessResult::allowedIf($allowed)
      ->addCacheableDependency($group)
      ->cachePerPermissions()
      ->cachePerUser();
  }

  /** Checks a certificate's course and authority before a revocation form. */
  public function revokeAccess(GroupInterface $group, CourseCertificate $certificate): AccessResult {
    if (!$group instanceof CourseInterface || (int) $group->id() !== (int) $certificate->get('course_id')->value) {
      return AccessResult::forbidden()
        ->addCacheableDependency($group)
        ->addCacheableDependency($certificate);
    }
    $allowed = $this->currentUser->hasPermission('administer lms') || $group->hasPermission('grade students', $this->currentUser);
    return AccessResult::allowedIf($allowed)
      ->addCacheableDependency($group)
      ->addCacheableDependency($certificate)
      ->cachePerPermissions()
      ->cachePerUser();
  }

}
