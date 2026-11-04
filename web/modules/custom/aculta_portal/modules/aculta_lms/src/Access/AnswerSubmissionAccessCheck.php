<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\group\Entity\GroupInterface;

/**
 * Grants answer submission only to currently enrolled learners.
 */
final class AnswerSubmissionAccessCheck {

  public function __construct(
    private readonly LmsFriendlySlugs $slugs,
    private readonly LearningEligibilityInterface $eligibility,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Checks membership and resolves the current lesson and activity positions.
   */
  public function access(GroupInterface $group, mixed $lesson_delta, mixed $activity_delta): AccessResult {
    $enrollmentCacheTags = $this->eligibility->courseEnrollmentCacheTags($group);
    $result = AccessResult::forbidden()
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheableDependency($group)
      ->addCacheTags([
        ...$enrollmentCacheTags,
        LmsFriendlySlugs::LESSON_LIST_CACHE_TAG,
        LmsFriendlySlugs::ACTIVITY_LIST_CACHE_TAG,
      ]);
    if (!$this->currentUser->isAuthenticated() || $group->bundle() !== LmsFriendlySlugs::COURSE_BUNDLE || !$group->isPublished()) {
      return $result;
    }

    // Access checks run before route parameter conversion. The route therefore
    // supplies the public slugs here, although the form receives integer
    // deltas after the parameter converters have run.
    if (is_string($lesson_delta)) {
      $lesson_delta = $this->slugs->lessonDeltaFromSlug($group, $lesson_delta);
    }
    if (!is_int($lesson_delta) || $lesson_delta < 0) {
      return $result;
    }
    if (is_string($activity_delta)) {
      $lesson = $this->slugs->lessonAt($group, $lesson_delta);
      $activity_delta = $lesson instanceof ContentEntityInterface
        ? $this->slugs->activityDeltaFromSlug($lesson, $activity_delta)
        : NULL;
    }
    if (!is_int($activity_delta) || $activity_delta < 0) {
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
      ->addCacheTags([
        ...$enrollmentCacheTags,
        LmsFriendlySlugs::LESSON_LIST_CACHE_TAG,
        LmsFriendlySlugs::ACTIVITY_LIST_CACHE_TAG,
      ]);
  }

}
