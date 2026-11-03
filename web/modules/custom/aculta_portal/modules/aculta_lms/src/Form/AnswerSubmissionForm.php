<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Learning\AnswerPayload;
use Drupal\aculta_lms\Learning\CourseActivity;
use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\aculta_lms\Plugin\ActivityAnswer\FreeTextReviewFeedback;
use Drupal\aculta_lms\Plugin\ActivityAnswer\ObjectiveAnswerPluginResolver;
use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Persists an immutable answer attempt against an ACULTA activity. */
final class AnswerSubmissionForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CurrentRouteMatch $currentRoute,
    protected LmsFriendlySlugs $slugs,
    protected FreeTextReviewFeedback $freeText,
    protected ObjectiveAnswerPluginResolver $objectivePlugins,
    protected AccountProxyInterface $currentUser,
    protected ConsumerUploadPolicy $uploadPolicy,
    protected FileUsageInterface $fileUsage,
  ) {}

  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('aculta_portal.lms_friendly_slugs'),
      $container->get('aculta_lms.free_text_answer'),
      $container->get('aculta_lms.objective_answer_plugins'),
      $container->get('current_user'),
      $container->get('aculta_file_uploader.consumer_policy'),
      $container->get('file.usage'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  public function getFormId(): string {
    return 'aculta_lms_answer_submission';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'aculta_lms/answer_form';
    [$course, $activity] = $this->routeEntities();
    $form_state->set('aculta_lms_course', $course);
    $form_state->set('aculta_lms_activity', $activity);
    $configuration = $this->configuration($activity);
    $ownAnswers = $this->entityTypeManager->getStorage('aculta_lms_answer')->loadByProperties([
      'activity_id' => (int) $activity->id(),
      'uid' => (int) $this->currentUser->id(),
    ]);
    usort($ownAnswers, static fn(ContentEntityInterface $a, ContentEntityInterface $b): int => (int) $b->id() <=> (int) $a->id());
    $latest = $ownAnswers[0] ?? NULL;
    if ($latest instanceof ContentEntityInterface && (string) $latest->get('state')->value === CourseAnswer::STATE_GRADED) {
      $form['evaluation'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Avaliação da sua resposta'),
        'score' => ['#type' => 'item', '#title' => $this->t('Nota'), '#plain_text' => (string) $latest->get('score')->value],
        'feedback' => ['#type' => 'item', '#title' => $this->t('Feedback'), '#plain_text' => (string) $latest->get('feedback')->value],
      ];
    }
    if ($form_state->has('aculta_lms_formative_feedback')) {
      $answer = (string) $form_state->get('aculta_lms_formative_answer');
      $form['formative_feedback'] = $this->freeText->formativeFeedback($answer, $configuration);
      $form['formative_score'] = [
        '#type' => 'item',
        '#title' => $this->t('Indicador formativo, sem efeito na nota final'),
        '#plain_text' => (string) $this->freeText->formativeScore($answer, $configuration),
      ];
      return $form;
    }
    if (($configuration['question'] ?? '') !== '') {
      $form['question'] = ['#type' => 'item', '#title' => $this->t('Atividade'), '#plain_text' => $configuration['question']];
    }
    $type = (string) $activity->get('type')->value;
    $answerElement = match ($type) {
      'free_text' => [
        '#type' => 'textarea',
        '#title' => $this->t('Sua resposta'),
        '#required' => TRUE,
        '#rows' => 10,
      ],
      'true_false' => [
        '#type' => 'radios',
        '#title' => $this->t('Sua resposta'),
        '#options' => ['true' => $this->t('Verdadeiro'), 'false' => $this->t('Falso')],
        '#required' => TRUE,
      ],
      'select' => $this->selectElement($configuration),
      'fill_blanks' => $this->fillBlanksElement($configuration),
      'file_upload' => [
        '#type' => 'managed_file',
        '#title' => $this->t('Arquivo da resposta'),
        '#upload_location' => 'private://lms/answers/',
        '#upload_validators' => ['FileExtension' => ['extensions' => implode(' ', array_merge($this->uploadPolicy->imageExtensions('lms'), $this->uploadPolicy->documentExtensions('lms')))]],
        '#required' => TRUE,
      ],
      default => throw new \UnexpectedValueException(sprintf('Tipo de atividade sem formulário ACULTA: %s.', $type)),
    };
    if ($type === 'file_upload') {
      $form['file_upload'] = $answerElement;
    }
    else {
      $form['answer'] = $answerElement;
    }
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Enviar resposta'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    [, $activity] = $this->routeEntities();
    if ((string) $activity->get('type')->value !== $this->freeText->activityType()) {
      if ((string) $activity->get('type')->value === 'file_upload') {
        $file = $this->submittedFile($form_state->getValue('file_upload'));
        if (!$this->validUpload($file)) {
          $form_state->setErrorByName('file_upload', $this->t('O arquivo escolhido não está disponível para esta resposta.'));
        }
      }
      return;
    }
    $configuration = $this->freeText->decodeConfiguration((string) ($activity->get('answer_config')->value ?? '{}'));
    $error = $this->freeText->validate((string) $form_state->getValue('answer'), $configuration);
    if ($error !== NULL) {
      $form_state->setErrorByName('answer', $this->t($error));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [, $activity] = $this->routeEntities();
    $type = (string) $activity->get('type')->value;
    $configuration = $this->configuration($activity);
    $value = $form_state->getValue($type === 'file_upload' ? 'file_upload' : 'answer');
    if ($type === 'free_text') {
      $answerData = ['answer' => trim((string) $value)];
      $state = CourseAnswer::STATE_SUBMITTED;
      $score = NULL;
    }
    elseif ($type === 'file_upload') {
      $file = $this->submittedFile($value);
      if (!$this->validUpload($file)) {
        $form_state->setErrorByName('file_upload', $this->t('O arquivo escolhido não está disponível para esta resposta.'));
        return;
      }
      $answerData = [];
      $state = CourseAnswer::STATE_SUBMITTED;
      $score = NULL;
    }
    else {
      $answerData = $this->objectiveAnswer($type, $value, $configuration);
      $activityValue = new CourseActivity((int) $activity->id(), (int) $activity->get('lesson_id')->value, $type, (bool) $activity->get('graded')->value);
      $score = $this->objectivePlugins->score($activityValue, $answerData['answer'], $configuration['correct'] ?? NULL);
      $state = CourseAnswer::STATE_GRADED;
    }
    $answer = $this->entityTypeManager->getStorage('aculta_lms_answer')->create([
      'activity_id' => (int) $activity->id(),
      'uid' => (int) $this->currentUser->id(),
      'state' => $state,
      'score' => $score,
    ]);
    $payload = new AnswerPayload($answerData);
    if ($type === 'file_upload') {
      $file = $this->submittedFile($value);
      $payload = $payload->withFileId((int) $file->id());
    }
    $answer->setAnswerPayload($payload);
    $answer->save();
    if ($type === 'file_upload') {
      $file->setPermanent();
      $file->save();
      $this->fileUsage->add($file, 'aculta_lms', 'aculta_lms_answer', (string) $answer->id());
    }
    if ($type === 'free_text') {
      $form_state->set('aculta_lms_formative_answer', $answerData['answer']);
      $form_state->set('aculta_lms_formative_feedback', TRUE);
      $form_state->setRebuild(TRUE);
    }
    $this->messenger()->addStatus($this->t('Sua resposta foi enviada.'));
  }

  private function selectElement(array $configuration): array {
    $options = $configuration['options'] ?? NULL;
    if (!is_array($options) || $options === []) {
      throw new \UnexpectedValueException('Atividade de seleção sem opções configuradas.');
    }
    return ['#type' => 'checkboxes', '#title' => $this->t('Sua resposta'), '#options' => $options];
  }

  private function fillBlanksElement(array $configuration): array {
    $blanks = $configuration['correct'] ?? NULL;
    if (!is_array($blanks) || $blanks === []) {
      throw new \UnexpectedValueException('Atividade de lacunas sem gabarito configurado.');
    }
    $element = ['#type' => 'fieldset', '#title' => $this->t('Sua resposta'), '#tree' => TRUE];
    foreach ($blanks as $index => $_accepted) {
      $element['blank_' . $index] = ['#type' => 'textfield', '#title' => $this->t('Lacuna @number', ['@number' => (string) ((int) $index + 1)]), '#required' => FALSE];
    }
    return $element;
  }

  private function objectiveAnswer(string $type, mixed $value, array $configuration): array {
    return match ($type) {
      'true_false' => ['answer' => $value === 'true'],
      'select' => ['answer' => array_values(array_filter((array) $value, static fn(mixed $selected): bool => $selected !== 0 && $selected !== '0' && $selected !== FALSE))],
      'fill_blanks' => ['answer' => $this->blankValues((array) $value, $configuration)],
      default => throw new \UnexpectedValueException('Tipo objetivo desconhecido.'),
    };
  }

  private function blankValues(array $value, array $configuration): array {
    $answers = [];
    foreach (array_keys((array) ($configuration['correct'] ?? [])) as $index) {
      $answers[$index] = (string) ($value['blank_' . $index] ?? '');
    }
    return $answers;
  }

  private function submittedFile(mixed $value): ?FileInterface {
    $ids = is_array($value) ? array_values(array_filter($value)) : [(int) $value];
    if (count($ids) !== 1) {
      return NULL;
    }
    $id = (int) ($ids[0] ?? 0);
    $file = $id > 0 ? $this->entityTypeManager->getStorage('file')->load($id) : NULL;
    return $file instanceof FileInterface ? $file : NULL;
  }

  private function validUpload(?FileInterface $file): bool {
    if (!$file instanceof FileInterface || !$file->access('view', $this->currentUser)
      || (int) $file->getOwnerId() !== (int) $this->currentUser->id()
      || !str_starts_with($file->getFileUri(), 'private://')) {
      return FALSE;
    }
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (in_array($extension, $this->uploadPolicy->imageExtensions('lms'), TRUE)) {
      return $this->uploadPolicy->isValidImage($file, 'lms');
    }
    return in_array($extension, $this->uploadPolicy->documentExtensions('lms'), TRUE)
      && $this->uploadPolicy->isValidDocument($file, 'lms');
  }

  private function configuration(ContentEntityInterface $activity): array {
    $json = (string) ($activity->get('answer_config')->value ?? '{}');
    if ((string) $activity->get('type')->value === $this->freeText->activityType()) {
      return $this->freeText->decodeConfiguration($json);
    }
    try {
      $root = json_decode($json === '' ? '{}' : $json, FALSE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \InvalidArgumentException('Configuração da atividade inválida.', 0, $exception);
    }
    if (!$root instanceof \stdClass) {
      throw new \InvalidArgumentException('Configuração da atividade deve ser um objeto JSON.');
    }
    return (array) json_decode($json === '' ? '{}' : $json, TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /** @return array{0: GroupInterface, 1: ContentEntityInterface} */
  private function routeEntities(): array {
    $course = $this->currentRoute->getParameter('group');
    $lessonDelta = $this->currentRoute->getParameter('lesson_delta');
    $activityDelta = $this->currentRoute->getParameter('activity_delta');
    if (!$course instanceof GroupInterface || !is_int($lessonDelta) || !is_int($activityDelta)) {
      throw new \LogicException('A rota de resposta exige curso e posições de aula/atividade.');
    }
    $activity = $this->slugs->activityAt($course, $lessonDelta, $activityDelta);
    if (!$activity instanceof ContentEntityInterface) {
      throw new \LogicException('A atividade solicitada não existe.');
    }
    return [$course, $activity];
  }

}
