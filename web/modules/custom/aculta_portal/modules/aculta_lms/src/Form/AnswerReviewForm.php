<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Entity\AnswerEntity;
use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Stores a teacher's score and feedback for an ACULTA answer. */
final class AnswerReviewForm extends ConfirmFormBase {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function getFormId(): string { return 'aculta_lms_answer_review'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $course = NULL, ?GroupInterface $group = NULL, ?AnswerEntity $answer = NULL): array {
    $form['score'] = ['#type' => 'number', '#title' => $this->t('Nota'), '#min' => 0, '#max' => 100, '#step' => 0.01, '#required' => TRUE, '#default_value' => $answer?->get('score')->value];
    $form['feedback'] = ['#type' => 'textarea', '#title' => $this->t('Feedback'), '#default_value' => $answer?->get('feedback')->value ?? ''];
    $form['course'] = ['#type' => 'hidden', '#value' => $course?->id()];
    $form['group'] = ['#type' => 'hidden', '#value' => $group?->id()];
    $form['answer'] = ['#type' => 'hidden', '#value' => $answer?->id()];
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): string { return (string) $this->t('Avaliar resposta'); }
  public function getCancelUrl(): Url {
    return Url::fromRoute('aculta_lms.answer_reviews', [
      'course' => $this->getRequest()->attributes->get('course')->id(),
      'group' => $this->getRequest()->attributes->get('group')->id(),
    ]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $answer = $this->entityTypeManager->getStorage('aculta_lms_answer')->load((int) $form_state->getValue('answer'));
    if (!$answer instanceof AnswerEntity) { return; }
    $answer->set('score', (float) $form_state->getValue('score'));
    $answer->set('feedback', (string) $form_state->getValue('feedback'));
    $answer->set('state', CourseAnswer::STATE_GRADED)->save();
    $this->messenger()->addStatus($this->t('Avaliação gravada.'));
  }
}
