<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;

/**
 * Grants published lesson access only to currently enrolled learners.
 */
final class LessonViewAccessCheck {

  public function __construct(
    private readonly LmsFriendlySlugs $slugs,
    private readonly LearningEligibilityInterface $eligibility,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Checks that the requested lesson is published and belongs to enrollment.
   */
  public function access(GroupInterface $group, mixed $lesson_delta): AccessResult {
    $membership_tags = ['group_relationship_list:plugin:lms_classes:group:' . $group->id()];
    foreach ($group->getRelationships('lms_classes') ?? [] as $relationship) {
      $class = $relationship instanceof GroupRelationshipInterface ? $relationship->getEntity() : NULL;
      if ($class instanceof GroupInterface) {
        $membership_tags[] = 'group_relationship_list:plugin:group_membership:group:' . $class->id();
      }
    }
    $result = AccessResult::forbidden()
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheableDependency($group)
      ->addCacheTags($membership_tags);
    if (!$this->currentUser->isAuthenticated()
      || $group->bundle() !== LmsFriendlySlugs::COURSE_BUNDLE
      || !$group->isPublished()) {
      return $result;
    }

    // Route access is evaluated before the parameter converter, so public
    // requests contain the lesson slug while controllers receive its delta.
    if (is_string($lesson_delta)) {
      $lesson_delta = $this->slugs->lessonDeltaFromSlug($group, $lesson_delta);
    }
    if (!is_int($lesson_delta) || $lesson_delta < 0) {
      return $result;
    }

    $lesson = $this->slugs->lessonAt($group, $lesson_delta);
    if (!$lesson instanceof ContentEntityInterface
      || !$this->eligibility->isContentAvailable($lesson)
      || !$this->eligibility->hasCurrentEnrollment($group, $this->currentUser)) {
      return $lesson instanceof ContentEntityInterface ? $result->addCacheableDependency($lesson) : $result;
    }

    return AccessResult::allowed()
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheableDependency($group)
      ->addCacheableDependency($lesson)
      ->addCacheTags($membership_tags);
  }

}
