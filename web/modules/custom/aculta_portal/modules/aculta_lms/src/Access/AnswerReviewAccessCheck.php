<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;

/** Grants answer review only to educators of the answer's course. */
final class AnswerReviewAccessCheck {

  public function __construct(
    private readonly LearningEligibilityInterface $eligibility,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public function access(GroupInterface $course, ?EntityInterface $answer = NULL): AccessResult {
    $allowed = $course->bundle() === 'lms_course'
      && $course->hasPermission('grade students', $this->currentUser);
    if ($allowed && $answer !== NULL) {
      $allowed = $answer->getEntityTypeId() === 'aculta_lms_answer'
        && ($answerCourse = $this->eligibility->courseForAnswer($answer)) instanceof GroupInterface
        && (string) $answerCourse->id() === (string) $course->id();
    }
    return AccessResult::allowedIf($allowed)
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheableDependency($course);
  }
}
