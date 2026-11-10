<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Entity\AnswerEntity;
use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\aculta_lms\EducatorFeedbackPolicy;
use Drupal\Component\Serialization\Json;
use Drupal\comment\CommentInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stores an educator's grade and feedback for an ACULTA answer. */
final class AnswerReviewForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly Connection $database,
  ) {}

  /**
   * Creates the answer review form with its required services. */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'), $container->get('current_user'), $container->get('database'));
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_lms_answer_review';
  }

  /**
   * Builds the educator's answer grading form. */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $course = NULL, ?GroupInterface $group = NULL, ?AnswerEntity $answer = NULL): array {
    if (!$course instanceof GroupInterface || !$group instanceof GroupInterface || !$answer instanceof AnswerEntity) {
      return $form;
    }

    // Keep the entity identity in server-side form state. Do not trust a hidden
    // answer ID when a grader submits the evaluation.
    $form_state->set('aculta_lms_answer_id', (int) $answer->id());
    $form_state->set('aculta_lms_course_id', (int) $course->id());
    $form_state->set('aculta_lms_group_id', (int) $group->id());

    $user = $this->entityTypeManager->getStorage('user')->load((int) $answer->get('uid')->value);
    $activity = $this->entityTypeManager->getStorage('aculta_lms_activity')->load((int) $answer->get('activity_id')->value);
    $lesson = $activity ? $this->entityTypeManager->getStorage('aculta_lms_lesson')->load((int) $activity->get('lesson_id')->value) : NULL;
    $payload = Json::encode($answer->getAnswerPayload()->toArray());

    $form['attempt'] = [
      '#type' => 'details',
      '#title' => $this->t('Resposta enviada'),
      '#open' => TRUE,
    ];
    $form['attempt']['student'] = [
      '#type' => 'item',
      '#title' => $this->t('Estudante'),
      '#plain_text' => $user instanceof UserInterface ? $user->getDisplayName() : (string) $answer->get('uid')->value,
    ];
    $form['attempt']['lesson'] = [
      '#type' => 'item',
      '#title' => $this->t('Aula'),
      '#plain_text' => $lesson ? (string) $lesson->get('title')->value : (string) $this->t('Aula removida'),
    ];
    $form['attempt']['activity'] = [
      '#type' => 'item',
      '#title' => $this->t('Tipo de atividade'),
      '#plain_text' => $activity ? str_replace('_', ' ', (string) $activity->get('type')->value) : (string) $this->t('Atividade removida'),
    ];
    $form['attempt']['payload'] = [
      '#type' => 'item',
      '#title' => $this->t('Conteúdo enviado'),
      '#plain_text' => $payload,
    ];

    try {
      $fileId = $answer->getAnswerPayload()->fileId();
    }
    catch (\UnexpectedValueException) {
      // The payload remains visible to the educator, but a malformed file
      // reference must not break the grading page or become a direct link.
      $fileId = NULL;
    }
    if ($fileId !== NULL) {
      $form['attempt']['attachment'] = [
        '#type' => 'link',
        '#title' => $this->t('Baixar arquivo enviado'),
        '#url' => Url::fromRoute('aculta_lms.answer_attachment_download', ['answer' => $answer->id()]),
      ];
    }

    $form['score'] = [
      '#type' => 'number',
      '#title' => $this->t('Nota (0 a 100)'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
      '#required' => TRUE,
      '#default_value' => $answer->get('score')->value,
    ];
    $form['feedback'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Feedback para o estudante'),
      '#default_value' => (string) ($answer->get('feedback')->value ?? ''),
      '#description' => $this->t('O feedback é registrado como comentário publicado da resposta e pode gerar uma notificação ao estudante.'),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar avaliação'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'aculta_portal_button',
      '#props' => [
        'text' => (string) $this->t('Voltar às respostas'),
        'url' => Url::fromRoute('aculta_lms.answer_reviews', ['course' => $course->id(), 'group' => $group->id()])->toString(),
        'variant' => 'secondary',
      ],
    ];

    return $form;
  }

  /**
   * Validates the submitted score. */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $score = $form_state->getValue('score');
    if (!is_numeric($score) || !is_finite((float) $score) || (float) $score < 0 || (float) $score > 100) {
      $form_state->setErrorByName('score', $this->t('Informe uma nota entre 0 e 100.'));
    }
  }

  /**
   * Saves the grade and educator feedback. */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $answer = $this->entityTypeManager->getStorage('aculta_lms_answer')->load((int) $form_state->get('aculta_lms_answer_id'));
    if (!$answer instanceof AnswerEntity) {
      $this->messenger()->addError($this->t('A resposta não está mais disponível.'));
      return;
    }

    $feedback = trim((string) $form_state->getValue('feedback'));
    $transaction = $this->database->startTransaction();
    try {
      $answer->set('score', (float) $form_state->getValue('score'));
      $answer->set('feedback', $feedback);
      $answer->set('state', CourseAnswer::STATE_GRADED)->save();

      if ($feedback !== '') {
        $comment = $this->entityTypeManager->getStorage('comment')->create([
          'entity_type' => 'aculta_lms_answer',
          'entity_id' => $answer->id(),
          'field_name' => 'comments',
          'comment_type' => EducatorFeedbackPolicy::COMMENT_TYPE,
          'uid' => (int) $this->currentUser->id(),
          'comment_body' => ['value' => $feedback, 'format' => 'plain_text'],
          'status' => CommentInterface::PUBLISHED,
        ]);
        $comment->save();
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
    $transaction->commitOrRelease();
    unset($transaction);

    $this->messenger()->addStatus($this->t('Avaliação gravada.'));
    $form_state->setRedirect('aculta_lms.answer_reviews', [
      'course' => (int) $form_state->get('aculta_lms_course_id'),
      'group' => (int) $form_state->get('aculta_lms_group_id'),
    ]);
  }

}
