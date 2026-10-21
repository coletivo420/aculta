<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\user\UserInterface;

/** Tightens uploaded-answer downloads after a student's course access ends. */
final class UploadedAnswerAccessCheck {

  /**
   * Checks LMS answer access and revalidates the owner's current course access.
   */
  public function access(AnswerInterface $lms_answer, AccountInterface $account): AccessResultInterface {
    if ($account instanceof UserInterface && !$account->isActive()) {
      return AccessResult::forbidden()
        ->cachePerUser()
        ->addCacheableDependency($account)
        ->addCacheableDependency($lms_answer);
    }

    $access = $lms_answer->access('see_details', $account, TRUE);
    if (!$access->isAllowed()) {
      return $access;
    }

    if ((string) $account->id() !== $lms_answer->getUserId()) {
      return $access;
    }

    // LMS currently lets an answer owner see their own answer regardless of
    // current enrollment. Uploaded files need the live course permission too.
    // LMS deletes statuses asynchronously. During that queue interval, an
    // answer can temporarily reference a status that is already gone.
    $lesson_status = $lms_answer->get('lesson_status')->entity;
    if (!$lesson_status instanceof LessonStatusInterface) {
      return $this->denyBrokenCourseReference($access, $lms_answer);
    }
    $course_status = $lesson_status->get('course_status')->entity;
    if (!$course_status instanceof CourseStatusInterface) {
      return $this->denyBrokenCourseReference($access, $lms_answer);
    }
    $course = $course_status->get(CourseStatusInterface::COURSE_FIELD)->entity;
    if (!$course instanceof CourseInterface) {
      return $this->denyBrokenCourseReference($access, $lms_answer);
    }

    return $access->andIf($course->takeAccess($account))
      ->addCacheableDependency($lms_answer)
      ->addCacheableDependency($course)
      ->addCacheContexts(['user']);
  }

  /**
   * Denies a download when its current LMS course context cannot be resolved.
   */
  private function denyBrokenCourseReference(AccessResultInterface $access, AnswerInterface $answer): AccessResultInterface {
    return AccessResult::forbidden()
      ->addCacheableDependency($access)
      ->addCacheableDependency($answer)
      ->addCacheContexts(['user']);
  }

}
