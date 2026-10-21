<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Hook;

use Drupal\aculta_forum_lms\Context\LmsActivityDiscussionResolver;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\user\UserInterface;

/**
 * Adds existing course discussion links to the LMS activity answer form.
 */
final class LmsActivityDiscussionHooks {

  public function __construct(
    private readonly LmsActivityDiscussionResolver $resolver,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_form_lms_answer_form_alter().
   */
  #[Hook('form_lms_answer_form_alter')]
  public function answerFormAlter(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof EntityFormInterface || !$formObject->getEntity() instanceof AnswerInterface) {
      return;
    }
    $answer = $formObject->getEntity();
    $activity = $answer->get(AnswerInterface::ACTIVITY_FIELD)->entity;
    $lessonStatus = $answer->get('lesson_status')->entity;
    if (!$activity instanceof ActivityInterface || !$lessonStatus instanceof LessonStatusInterface) {
      return;
    }
    $courseStatus = $lessonStatus->get('course_status')->entity;
    $course = $courseStatus instanceof CourseStatusInterface
      ? $courseStatus->get(CourseStatusInterface::COURSE_FIELD)->entity
      : NULL;
    $account = $answer->getOwner();
    if (!$course instanceof CourseInterface || !$account instanceof UserInterface
      || (int) $account->id() !== (int) $this->currentUser->id()) {
      return;
    }
    $discussions = $this->resolver->build($activity, $course, $account);
    if (!empty($discussions['links']['#items'])) {
      $form['aculta_forum_lms_activity_discussions'] = $discussions + [
        '#weight' => 100,
      ];
    }
  }

}
