<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lms\Entity\AnswerInterface;

/** Tightens uploaded-answer downloads after a student's course access ends. */
final class UploadedAnswerAccessCheck {

  /**
   * Checks LMS answer access and revalidates the owner's current course access.
   */
  public function access(AnswerInterface $lms_answer, AccountInterface $account): AccessResultInterface {
    $access = $lms_answer->access('see_details', $account, TRUE);
    if (!$access->isAllowed()) {
      return $access;
    }

    if ((string) $account->id() !== $lms_answer->getUserId()) {
      return $access;
    }

    // LMS currently lets an answer owner see their own answer regardless of
    // current enrollment. Uploaded files need the live course permission too.
    $course = $lms_answer->getLessonStatus()->getCourseStatus()->getCourse();
    return $access->andIf($course->takeAccess($account))
      ->addCacheableDependency($lms_answer)
      ->addCacheableDependency($course)
      ->addCacheContexts(['user']);
  }

}
