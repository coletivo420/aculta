<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Entity\AnswerEntity;
use Drupal\aculta_lms\Learning\AnswerPayload;
use Drupal\aculta_lms\Learning\CourseActivity;
use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\aculta_lms\Plugin\ActivityAnswer\FreeTextReviewFeedback;
use Drupal\aculta_lms\Plugin\ActivityAnswer\ObjectiveAnswerPluginResolver;
use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Persists an immutable answer attempt against an ACULTA activity. */
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

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_answer_submission';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   Form API render array being built.
   *
   * @return array<array-key, mixed>
   *   Completed answer form render array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    [$course, $activity] = $this->routeEntities();
    $form_state->set('aculta_lms_course', $course);
    $form_state->set('aculta_lms_activity', $activity);
    $configuration = $this->configuration($activity);
    $ownAnswers = $this->entityTypeManager->getStorage('aculta_lms_answer')->loadByProperties([
      'activity_id' => (int) $activity->id(),
      'uid' => (int) $this->currentUser->id(),
    ]);
    usort($ownAnswers, static fn(EntityInterface $a, EntityInterface $b): int => (int) $b->id() <=> (int) $a->id());
    $latest = $ownAnswers[0] ?? NULL;
    if ($latest instanceof ContentEntityInterface && $this->fieldString($latest, 'state') === CourseAnswer::STATE_GRADED) {
      $form['evaluation'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Avaliação da sua resposta'),
        'score' => [
          '#type' => 'item',
          '#title' => $this->t('Nota'),
          '#plain_text' => $this->fieldString($latest, 'score'),
        ],
        'feedback' => [
          '#type' => 'item',
          '#title' => $this->t('Feedback'),
          '#plain_text' => $this->fieldString($latest, 'feedback'),
        ],
      ];
    }
    if ($form_state->has('aculta_lms_formative_feedback')) {
      $formativeAnswer = $form_state->get('aculta_lms_formative_answer');
      $answer = is_string($formativeAnswer) ? $formativeAnswer : '';
      $form['formative_feedback'] = $this->freeText->formativeFeedback($answer, $configuration);
      $form['formative_score'] = [
        '#type' => 'item',
        '#title' => $this->t('Indicador formativo, sem efeito na nota final'),
        '#plain_text' => (string) $this->freeText->formativeScore($answer, $configuration),
      ];
      return $form;
    }
    if (($configuration['question'] ?? '') !== '') {
      $form['question'] = [
        '#type' => 'item',
        '#title' => $this->t('Atividade'),
        '#plain_text' => $configuration['question'],
      ];
    }
    $type = $this->fieldString($activity, 'type');
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
        '#upload_validators' => $this->uploadPolicy->extensionValidators('lms'),
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
    $actions = is_array($form['actions'] ?? NULL) ? $form['actions'] : [];
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar resposta'),
      '#button_type' => 'primary',
    ];
    $form['actions'] = $actions;
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   Submitted Form API render array.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    [, $activity] = $this->routeEntities();
    $type = $this->fieldString($activity, 'type');
    if ($type !== $this->freeText->activityType()) {
      if ($type === 'file_upload') {
        $file = $this->submittedFile($form_state->getValue('file_upload'));
        if (!$this->validUpload($file)) {
          $form_state->setErrorByName('file_upload', $this->t('O arquivo escolhido não está disponível para esta resposta.'));
        }
      }
      elseif ($type === 'fill_blanks' && !$this->hasOnlyTextValues($form_state->getValue('answer'))) {
        $form_state->setErrorByName('answer', $this->t('Informe um texto para cada lacuna.'));
      }
      return;
    }
    $configuration = $this->freeText->decodeConfiguration($this->fieldString($activity, 'answer_config', '{}'));
    $answer = $form_state->getValue('answer');
    if (!is_string($answer)) {
      $form_state->setErrorByName('answer', $this->t('Informe uma resposta em texto.'));
      return;
    }
    $error = $this->freeText->validate($answer, $configuration);
    if ($error !== NULL) {
      $form_state->setErrorByName('answer', $this->t($error));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   Submitted Form API render array.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [, $activity] = $this->routeEntities();
    $type = $this->fieldString($activity, 'type');
    $configuration = $this->configuration($activity);
    $value = $form_state->getValue($type === 'file_upload' ? 'file_upload' : 'answer');
    $file = NULL;
    $formativeAnswer = NULL;
    if ($type === 'free_text') {
      if (!is_string($value)) {
        $form_state->setErrorByName('answer', $this->t('Informe uma resposta em texto.'));
        return;
      }
      $formativeAnswer = trim($value);
      $answerData = ['answer' => $formativeAnswer];
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
      if ($type === 'fill_blanks' && !$this->hasOnlyTextValues($value)) {
        $form_state->setErrorByName('answer', $this->t('Informe um texto para cada lacuna.'));
        return;
      }
      $answerData = $this->objectiveAnswer($type, $value, $configuration);
      $activityValue = new CourseActivity((int) $activity->id(), $this->fieldInteger($activity, 'lesson_id'), $type, (bool) $activity->get('graded')->value);
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
      if (!$file instanceof FileInterface) {
        throw new \LogicException('A resposta de arquivo foi validada sem um arquivo acessível.');
      }
      $payload = $payload->withFileId((int) $file->id());
    }
    if (!$answer instanceof AnswerEntity) {
      throw new \LogicException('The answer storage returned an unexpected entity.');
    }
    $answer->setAnswerPayload($payload);
    $answer->save();
    if ($file instanceof FileInterface) {
      $file->setPermanent();
      $file->save();
      $this->fileUsage->add($file, 'aculta_lms', 'aculta_lms_answer', (string) $answer->id());
    }
    if ($formativeAnswer !== NULL) {
      $form_state->set('aculta_lms_formative_answer', $formativeAnswer);
      $form_state->set('aculta_lms_formative_feedback', TRUE);
      $form_state->setRebuild(TRUE);
    }
    $this->messenger()->addStatus($this->t('Sua resposta foi enviada.'));
  }

  /**
   * Monta o elemento de resposta para atividades de seleção.
   *
   * @param array<string, mixed> $configuration
   *   Configuração validada da atividade.
   *
   * @return array<string, mixed>
   *   Elemento de formulário com as opções de resposta.
   */
  private function selectElement(array $configuration): array {
    $options = $configuration['options'] ?? NULL;
    if (!is_array($options) || $options === []) {
      throw new \UnexpectedValueException('Atividade de seleção sem opções configuradas.');
    }
    return ['#type' => 'checkboxes', '#title' => $this->t('Sua resposta'), '#options' => $options];
  }

  /**
   * Monta o elemento de resposta para atividades de preenchimento de lacunas.
   *
   * @param array<string, mixed> $configuration
   *   Configuração validada da atividade.
   *
   * @return array<array-key, mixed>
   *   Elemento de formulário com um campo por lacuna.
   */
  private function fillBlanksElement(array $configuration): array {
    $blanks = $configuration['correct'] ?? NULL;
    if (!is_array($blanks) || $blanks === []) {
      throw new \UnexpectedValueException('Atividade de lacunas sem gabarito configurado.');
    }
    $element = ['#type' => 'fieldset', '#title' => $this->t('Sua resposta'), '#tree' => TRUE];
    foreach ($blanks as $index => $_accepted) {
      $element['blank_' . $index] = [
        '#type' => 'textfield',
        '#title' => $this->t('Lacuna @number', ['@number' => (string) ((int) $index + 1)]),
        '#required' => FALSE,
      ];
    }
    return $element;
  }

  /**
   * Normaliza o valor enviado em dados de resposta de atividade objetiva.
   *
   * @param string $type
   *   Tipo da atividade objetiva.
   * @param mixed $value
   *   Valor enviado pelo formulário.
   * @param array<string, mixed> $configuration
   *   Configuração validada da atividade.
   *
   * @return array{answer: mixed}
   *   Dados de resposta normalizados.
   */
  private function objectiveAnswer(string $type, mixed $value, array $configuration): array {
    return match ($type) {
      'true_false' => ['answer' => $value === 'true'],
      'select' => ['answer' => array_values(array_filter((array) $value, static fn(mixed $selected): bool => $selected !== 0 && $selected !== '0' && $selected !== FALSE))],
      'fill_blanks' => ['answer' => $this->blankValues((array) $value, $configuration)],
      default => throw new \UnexpectedValueException('Tipo objetivo desconhecido.'),
    };
  }

  /**
   * Extrai os valores enviados para cada lacuna na ordem do gabarito.
   *
   * @param array<array-key, mixed> $value
   *   Valores enviados pelo formulário.
   * @param array<string, mixed> $configuration
   *   Configuração da atividade com o gabarito.
   *
   * @return array<array-key, string>
   *   Valores das lacunas indexados pela posição.
   */
  private function blankValues(array $value, array $configuration): array {
    $answers = [];
    foreach (array_keys((array) ($configuration['correct'] ?? [])) as $index) {
      $answer = $value['blank_' . $index] ?? '';
      if (!is_string($answer)) {
        throw new \InvalidArgumentException(sprintf('A resposta da lacuna %s precisa ser texto.', (string) $index));
      }
      $answers[$index] = $answer;
    }
    return $answers;
  }

  /**
   * Checks that every submitted fill-in-the-blank value is text.
   */
  private function hasOnlyTextValues(mixed $value): bool {
    if (!is_array($value)) {
      return FALSE;
    }
    foreach ($value as $answer) {
      if (!is_string($answer)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Carrega o arquivo enviado quando há exatamente um identificador válido.
   *
   * @param mixed $value
   *   Valor enviado pelo elemento de arquivo.
   *
   * @return \Drupal\file\FileInterface|null
   *   Arquivo carregado ou nulo quando ausente ou ambíguo.
   */
  private function submittedFile(mixed $value): ?FileInterface {
    $ids = is_array($value)
      ? array_values(array_filter($value, static fn(mixed $id): bool => $id !== NULL && $id !== '' && $id !== 0 && $id !== '0' && $id !== FALSE))
      : [$value];
    if (count($ids) !== 1) {
      return NULL;
    }
    $submittedId = $ids[0];
    if (!is_int($submittedId) && (!is_string($submittedId) || $submittedId === '' || !ctype_digit($submittedId))) {
      return NULL;
    }
    $id = (int) $submittedId;
    if ($id < 1) {
      return NULL;
    }
    $file = $this->entityTypeManager->getStorage('file')->load($id);
    return $file instanceof FileInterface ? $file : NULL;
  }

  /**
   * Verifica se o arquivo é privado, pertence ao usuário e respeita a política de upload.
   *
   * @param \Drupal\file\FileInterface|null $file
   *   Arquivo a validar.
   *
   * @return bool
   *   VERDADEIRO se o arquivo pode ser usado na resposta.
   */
  private function validUpload(?FileInterface $file): bool {
    if (!$file instanceof FileInterface || !$file->access('view', $this->currentUser)
      || (int) $file->getOwnerId() !== (int) $this->currentUser->id()) {
      return FALSE;
    }
    $uri = $file->getFileUri();
    $filename = $file->getFilename();
    if (!is_string($uri) || !str_starts_with($uri, 'private://') || !is_string($filename) || $filename === '') {
      return FALSE;
    }
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($extension, $this->uploadPolicy->imageExtensions('lms'), TRUE)) {
      return $this->uploadPolicy->isValidImage($file, 'lms');
    }
    return in_array($extension, $this->uploadPolicy->documentExtensions('lms'), TRUE)
      && $this->uploadPolicy->isValidDocument($file, 'lms');
  }

  /**
   * Decodifica a configuração de resposta da atividade.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $activity
   *   Atividade cuja configuração será lida.
   *
   * @return array<string, mixed>
   *   Configuração decodificada da atividade.
   */
  private function configuration(ContentEntityInterface $activity): array {
    $json = $this->fieldString($activity, 'answer_config', '{}');
    if ($this->fieldString($activity, 'type') === $this->freeText->activityType()) {
      return $this->stringKeyedConfiguration($this->freeText->decodeConfiguration($json));
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
    $decoded = json_decode($json === '' ? '{}' : $json, TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
      throw new \InvalidArgumentException('Configuração da atividade deve ser um objeto JSON.');
    }
    return $this->stringKeyedConfiguration($decoded);
  }

  /**
   * Obtém o curso e a atividade a partir da rota atual.
   *
   * @return array{GroupInterface, ContentEntityInterface}
   *   Curso na posição 0 e atividade na posição 1.
   */
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

  /**
   * Reads a scalar entity field value as text without coercing arrays/objects.
   */
  private function fieldString(ContentEntityInterface $entity, string $fieldName, string $default = ''): string {
    $value = $entity->get($fieldName)->value;
    if ($value === NULL) {
      return $default;
    }
    if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
      throw new \UnexpectedValueException(sprintf('O campo %s precisa conter um valor escalar.', $fieldName));
    }
    return (string) $value;
  }

  /**
   * Reads an integer entity field value.
   */
  private function fieldInteger(ContentEntityInterface $entity, string $fieldName): int {
    $value = $entity->get($fieldName)->value;
    if (is_int($value)) {
      return $value;
    }
    if (!is_string($value) || !preg_match('/^-?\\d+$/D', $value)) {
      throw new \UnexpectedValueException(sprintf('O campo %s precisa conter um inteiro.', $fieldName));
    }
    return (int) $value;
  }

  /**
   * Validates that a decoded configuration has string keys at its root.
   *
   * @param array<array-key, mixed> $configuration
   *   Decoded configuration from a JSON object.
   *
   * @return array<string, mixed>
   *   Configuration keyed by its string property names.
   */
  private function stringKeyedConfiguration(array $configuration): array {
    $stringKeyed = [];
    foreach ($configuration as $key => $value) {
      if (!is_string($key)) {
        throw new \InvalidArgumentException('As propriedades da configuração precisam ter nomes textuais.');
      }
      $stringKeyed[$key] = $value;
    }
    return $stringKeyed;
  }

}
