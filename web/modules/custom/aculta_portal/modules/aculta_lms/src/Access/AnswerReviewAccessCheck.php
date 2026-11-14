<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\aculta_lms\Group\GroupMembershipReaderInterface;
use Drupal\user\UserInterface;

/**
 * Grants answer review only to educators of the answer's course. */
final class AnswerReviewAccessCheck {

  public function __construct(
    private readonly LearningEligibilityInterface $eligibility,
    private readonly AccountProxyInterface $currentUser,
    private readonly GroupMembershipReaderInterface $memberships,
  ) {}

  /**
   * Checks course, class, grader, and answer membership access. */
  public function access(GroupInterface $course, GroupInterface $group, ?FieldableEntityInterface $answer = NULL): AccessResult {
    $allowed = $course->bundle() === 'lms_course'
      && $group->bundle() === 'lms_class'
      && $course->hasPermission('grade students', $this->currentUser);
    $courseHasClass = FALSE;
    $cacheTags = [
      'group_relationship_list:plugin:lms_classes:group:' . $course->id(),
      'group_relationship_list:plugin:group_membership:group:' . $group->id(),
    ];
    if ($allowed) {
      foreach ($course->getRelationships('lms_classes') as $relationship) {
        $class = $relationship->getEntity();
        if ($class instanceof GroupInterface && (string) $class->id() === (string) $group->id()) {
          $courseHasClass = TRUE;
          break;
        }
      }
      $allowed = $courseHasClass;
    }
    if ($allowed && $answer !== NULL) {
      $allowed = $answer->getEntityTypeId() === 'aculta_lms_answer'
        && ($answerCourse = $this->eligibility->courseForAnswer($answer)) instanceof GroupInterface
        && (string) $answerCourse->id() === (string) $course->id();
      if ($allowed) {
        $studentId = (int) $answer->get('uid')->value;
        $allowed = FALSE;
        foreach ($this->memberships->loadByGroup($group) as $membership) {
          if ($membership->getPluginId() !== 'group_membership') {
            continue;
          }
          $member = $membership->getEntity();
          if ($member instanceof UserInterface && $member->isActive() && (int) $member->id() === $studentId) {
            $allowed = TRUE;
            break;
          }
        }
      }
    }
    $result = AccessResult::allowedIf($allowed)
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions'])
      ->addCacheableDependency($course)
      ->addCacheableDependency($group)
      ->addCacheTags($cacheTags);
    if ($answer !== NULL) {
      $result->addCacheableDependency($answer);
    }
    return $result;
  }

}
