<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\user\UserInterface;

/**
 * Shows the class-specific deadline on the LMS activity answer form. */
final class ActivityDeadlineFormHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ClassActivityDeadlineManager $deadlines,
    private readonly DateFormatterInterface $dateFormatter,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
 * Adds the deadline label without changing the answer or its validation. */
  #[Hook('form_lms_answer_form_alter')]
  public function answerFormAlter(array &$form, FormStateInterface $form_state): void {
    $formObject = $form_state->getFormObject();
    if (!$formObject instanceof EntityFormInterface || !$formObject->getEntity() instanceof AnswerInterface) {
      return;
    }
    $answer = $formObject->getEntity();
    $course = $this->courseForAnswer($answer);
    $learner = $answer->getOwner();
    if (!$course instanceof CourseInterface || !$learner instanceof UserInterface) {
      return;
    }
    $activity = $answer->get(AnswerInterface::ACTIVITY_FIELD)->entity;
    if (!$activity instanceof ActivityInterface || !$activity instanceof EntityPublishedInterface || !$activity->isPublished()) {
      return;
    }
    $deadline = $this->deadlines->deadlineForLearner($course, $activity, $learner);
    if ($deadline === NULL || $deadline->get('field_activity_due_at')->isEmpty()) {
      return;
    }
    $date = DrupalDateTime::createFromFormat(
      DateTimeItemInterface::DATETIME_STORAGE_FORMAT,
      (string) $deadline->get('field_activity_due_at')->value,
      new \DateTimeZone('UTC'),
    );
    if (!$date instanceof DrupalDateTime || $date->hasErrors()) {
      return;
    }
    $form['aculta_lms_activity_deadline'] = [
      '#type' => 'item',
      '#title' => $this->t('Prazo desta atividade'),
      '#markup' => $this->t('Envie sua resposta até @date.', [
        '@date' => $this->dateFormatter->format($date->getTimestamp(), 'long', NULL, $learner->getTimeZone()),
      ]),
      '#weight' => -100,
    ];
  }

  /**
   * Resolves the current course without typed getters on stale LMS references.
   */
  private function courseForAnswer(AnswerInterface $answer): ?CourseInterface {
    $lessonStatus = $answer->get('lesson_status')->entity;
    if (!$lessonStatus instanceof LessonStatusInterface) {
      return NULL;
    }
    $courseStatus = $lessonStatus->get('course_status')->entity;
    if (!$courseStatus instanceof CourseStatusInterface) {
      return NULL;
    }
    $course = $courseStatus->get(CourseStatusInterface::COURSE_FIELD)->entity;
    return $course instanceof CourseInterface && $course->bundle() === 'lms_course'
      ? $course
      : NULL;
  }

}
