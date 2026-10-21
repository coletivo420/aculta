<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DateTimeItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\user\UserInterface;

/**
 * Shows the class-specific deadline on the LMS activity answer form. */
final class ActivityDeadlineFormHooks {

  public function __construct(
    private readonly ClassActivityDeadlineManager $deadlines,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
 * Adds the deadline label without changing the answer or its validation. */
  #[Hook('form_lms_answer_form_alter')]
  public function answerFormAlter(array &$form, FormStateInterface $form_state): void {
    $formObject = $form_state->getFormObject();
    if (!$formObject instanceof EntityFormInterface || !$formObject->getEntity() instanceof AnswerInterface) {
      return;
    }
    $answer = $formObject->getEntity();
    $course = $answer->getLessonStatus()->getCourseStatus()->getCourse();
    $learner = $answer->getOwner();
    if (!$course instanceof CourseInterface || !$learner instanceof UserInterface
      || !$answer->getActivity() instanceof EntityPublishedInterface || !$answer->getActivity()->isPublished()) {
      return;
    }
    $deadline = $this->deadlines->deadlineForLearner($course, $answer->getActivity(), $learner);
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

}
