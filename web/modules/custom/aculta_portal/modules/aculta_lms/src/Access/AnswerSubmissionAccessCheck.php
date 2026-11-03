<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\group\Entity\GroupInterface;

/** Grants answer submission only to currently enrolled learners. */
final class AnswerSubmissionAccessCheck {

  public function __construct(
    private readonly LmsFriendlySlugs $slugs,
    private readonly LearningEligibilityInterface $eligibility,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public function access(GroupInterface $group, int $lesson_delta, int $activity_delta): AccessResult {
    $result = AccessResult::forbidden()
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheableDependency($group)
      ->addCacheTags(['group_relationship_list:plugin:lms_classes:group:' . $group->id()]);
    if (!$this->currentUser->isAuthenticated() || $group->bundle() !== LmsFriendlySlugs::COURSE_BUNDLE || !$group->isPublished()) {
      return $result;
    }
    $activity = $this->slugs->activityAt($group, $lesson_delta, $activity_delta);
    if (!$activity instanceof ContentEntityInterface
      || !$this->eligibility->activityBelongsToCourse($activity, $group)
      || !$this->eligibility->isContentAvailable($activity)
      || !$this->eligibility->hasCurrentEnrollment($group, $this->currentUser)) {
      return $activity instanceof ContentEntityInterface ? $result->addCacheableDependency($activity) : $result;
    }
    return AccessResult::allowed()
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheableDependency($group)
      ->addCacheableDependency($activity)
      ->addCacheTags(['group_relationship_list:plugin:lms_classes:group:' . $group->id()]);
  }

}
