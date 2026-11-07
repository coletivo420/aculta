<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;

/**
 * Adds the current learner's class deadline to the ACULTA answer form. */
final class ActivityDeadlineFormHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ClassActivityDeadlineManager $deadlines,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Mostra o prazo da atividade no formulário de resposta.
   *
   * @param array $form
   *   Estrutura do formulário, alterada no lugar.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado do formulário.
   */
  #[Hook('form_aculta_lms_answer_submission_alter')]
  public function answerFormAlter(array &$form, FormStateInterface $form_state): void {
    $course = $form_state->get('aculta_lms_course');
    $activity = $form_state->get('aculta_lms_activity');
    if (!$course instanceof GroupInterface || !$activity instanceof ContentEntityInterface
      || $activity->getEntityTypeId() !== 'aculta_lms_activity') {
      return;
    }
    $learner = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if (!$learner instanceof UserInterface || !$learner->isActive()) {
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

}
