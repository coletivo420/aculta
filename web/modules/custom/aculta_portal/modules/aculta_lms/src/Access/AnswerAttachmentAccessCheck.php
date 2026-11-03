<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\aculta_lms\Learning\AnswerPayload;
use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;

/**
 * Checks current course access before streaming an ACULTA answer attachment. */
final class AnswerAttachmentAccessCheck {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LearningEligibilityInterface $eligibility,
  ) {}

  /**
   * Allows an enrolled answer owner or a current course grader to download.
   */
  public function access(EntityInterface $answer, AccountInterface $account): AccessResultInterface {
    $denied = AccessResult::forbidden()->cachePerUser()->addCacheableDependency($answer);
    if ($answer->getEntityTypeId() !== 'aculta_lms_answer' || !$account->isAuthenticated()) {
      return $denied;
    }

    $uid = (int) $answer->get('uid')->value;
    $state = (string) $answer->get('state')->value;
    if ($uid <= 0 || !in_array($state, [CourseAnswer::STATE_SUBMITTED, CourseAnswer::STATE_GRADED], TRUE)) {
      return $denied;
    }
    $student = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$student instanceof UserInterface) {
      return $denied;
    }
    if (!$student->isActive()) {
      return $denied->addCacheableDependency($student);
    }

    $activity = $this->entityTypeManager->getStorage('aculta_lms_activity')->load((int) $answer->get('activity_id')->value);
    if (!$activity instanceof EntityInterface) {
      return $denied->addCacheableDependency($student);
    }
    if ((string) $activity->get('type')->value !== 'file_upload') {
      return $denied->addCacheableDependency($student)->addCacheableDependency($activity);
    }

    try {
      $payload = AnswerPayload::fromJson((string) $answer->get('answer_data')->value);
      $fileId = $payload->fileId();
    }
    catch (\InvalidArgumentException | \UnexpectedValueException) {
      return $denied->addCacheableDependency($student)->addCacheableDependency($activity);
    }
    if ($fileId === NULL) {
      return $denied->addCacheableDependency($student)->addCacheableDependency($activity);
    }

    $file = $this->entityTypeManager->getStorage('file')->load($fileId);
    if (!$file instanceof FileInterface || $file->isTemporary()
      || !str_starts_with($file->getFileUri(), 'private://')) {
      return $denied->addCacheableDependency($student)
        ->addCacheableDependency($activity)
        ->addCacheableDependency($file);
    }

    $course = $this->eligibility->courseForAnswer($answer);
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      return $denied->addCacheableDependency($student)
        ->addCacheableDependency($activity)
        ->addCacheableDependency($file);
    }

    $owner = (string) $account->id() === (string) $uid;
    $allowed = $owner
      ? $this->eligibility->hasCurrentEnrollment($course, $student)
      : $course->hasPermission('grade students', $account);

    return AccessResult::allowedIf($allowed)
      ->cachePerUser()
      ->addCacheableDependency($answer)
      ->addCacheableDependency($student)
      ->addCacheableDependency($activity)
      ->addCacheableDependency($file)
      ->addCacheableDependency($course)
      ->addCacheTags(['group_relationship_list:plugin:lms_classes:group:' . $course->id()]);
  }

}
