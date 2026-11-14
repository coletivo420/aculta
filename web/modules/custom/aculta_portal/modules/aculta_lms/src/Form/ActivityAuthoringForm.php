<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Learning\ActivityConfiguration;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Creates or edits supported activities through their existing JSON contract.
 */
final class ActivityAuthoringForm extends FormBase {

  private const ACTIVITY_TYPES = ['true_false', 'select', 'fill_blanks', 'free_text', 'file_upload'];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly CurrentRouteMatch $currentRouteMatch,
    protected readonly CourseAuthoringAccessCheck $accessCheck,
  ) {}

  /**
   * Creates the form from the Drupal service container.
   */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('aculta_lms.course_authoring_access'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * Returns the stable form identifier.
   */
  public function getFormId(): string {
    return 'aculta_lms_activity_authoring';
  }

  /**
   * Builds the activity authoring form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    [$course, $lesson, $activity] = $this->routeEntities();
    $form['#attributes']['data-aculta-unsaved-changes'] = TRUE;
    $form['#attached']['library'][] = 'aculta_lms/unsaved_changes';
    $configuration = $activity ? $this->decodeConfiguration((string) $activity->get('answer_config')->value) : [];
    $type = (string) ($activity?->get('type')->value ?? 'free_text');
    $typeOptions = [
      'true_false' => $this->t('Verdadeiro ou falso'),
      'select' => $this->t('Seleção'),
      'fill_blanks' => $this->t('Preencher lacunas'),
      'free_text' => $this->t('Resposta discursiva'),
      'file_upload' => $this->t('Envio de arquivo'),
    ];
    if ($activity !== NULL) {
      $form['type_display'] = [
        '#type' => 'item',
        '#title' => $this->t('Tipo de atividade'),
        '#plain_text' => $typeOptions[$type] ?? $this->t('Tipo legado não disponível'),
        '#description' => $this->t('O tipo não pode ser alterado depois que a atividade foi criada, para preservar compatibilidade com as respostas.'),
      ];
      $form['type'] = ['#type' => 'value', '#value' => $type];
    }
    else {
      $form['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Tipo de atividade'),
        '#options' => $typeOptions,
        '#default_value' => in_array($type, self::ACTIVITY_TYPES, TRUE) ? $type : 'free_text',
        '#required' => TRUE,
      ];
    }
    $form['question'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enunciado'),
      '#required' => TRUE,
      '#rows' => 4,
      '#default_value' => (string) ($configuration['question'] ?? ''),
    ];
    $form['correct_boolean'] = [
      '#type' => 'radios',
      '#title' => $this->t('Resposta correta'),
      '#options' => ['true' => $this->t('Verdadeiro'), 'false' => $this->t('Falso')],
      '#default_value' => isset($configuration['correct']) && is_bool($configuration['correct']) ? ($configuration['correct'] ? 'true' : 'false') : NULL,
      '#states' => ['visible' => [':input[name="type"]' => ['value' => 'true_false']]],
    ];
    $form['options'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Opções de resposta'),
      '#description' => $this->t('Uma opção por linha. A ordem exibida ao estudante acompanha esta lista.'),
      '#default_value' => implode("\n", array_map('strval', array_values((array) ($configuration['options'] ?? [])))),
      '#states' => ['visible' => [':input[name="type"]' => ['value' => 'select']]],
    ];
    $form['correct_values'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Opções corretas'),
      '#description' => $this->t('Informe cada opção correta em uma linha, exatamente como aparece acima. Seleção permite mais de uma resposta correta.'),
      '#default_value' => implode("\n", array_map('strval', (array) ($configuration['correct'] ?? []))),
      '#states' => ['visible' => [':input[name="type"]' => ['value' => 'select']]],
    ];
    $form['blank_answers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Respostas aceitas por lacuna'),
      '#description' => $this->t('Uma lacuna por linha; use | para separar sinônimos aceitos, por exemplo: cannabis|maconha.'),
      '#default_value' => implode("\n", array_map(static fn(array $answers): string => implode('|', $answers), (array) ($configuration['correct'] ?? []))),
      '#states' => ['visible' => [':input[name="type"]' => ['value' => 'fill_blanks']]],
    ];
    $form['minimum_characters'] = [
      '#type' => 'number',
      '#title' => $this->t('Mínimo de caracteres'),
      '#min' => 0,
      '#max' => 10000,
      '#step' => 1,
      '#default_value' => (int) ($configuration['minimum_characters'] ?? 0),
      '#states' => ['visible' => [':input[name="type"]' => ['value' => 'free_text']]],
    ];
    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Posição nesta aula'),
      '#min' => 0,
      '#step' => 1,
      '#required' => TRUE,
      '#default_value' => $activity?->get('weight')->value ?? $this->nextWeight((int) $lesson->id()),
    ];
    $form['graded'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Incluir no cálculo de nota'),
      '#default_value' => (bool) ($activity?->get('graded')->value ?? ($type !== 'free_text' && $type !== 'file_upload')),
      '#description' => $this->t('Respostas discursivas e arquivos aguardam avaliação do educador; o indicador formativo não altera a nota final.'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $activity ? $this->t('Salvar atividade') : $this->t('Criar atividade'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'aculta_portal_button',
      '#props' => [
        'text' => (string) $this->t('Voltar à estrutura do curso'),
        'url' => Url::fromRoute('aculta_lms.course_structure', ['course' => $course->id()])->toString(),
        'variant' => 'secondary',
      ],
    ];
    return $form;
  }

  /**
   * Validates activity configuration before save.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $type = (string) $form_state->getValue('type');
    try {
      ActivityConfiguration::fromFormValues($type, $form_state->getValues());
    }
    catch (\InvalidArgumentException $exception) {
      $field = match ($type) {
        'true_false' => 'correct_boolean',
        'select' => 'correct_values',
        'fill_blanks' => 'blank_answers',
        'free_text' => 'minimum_characters',
        default => 'question',
      };
      $form_state->setErrorByName($field, $exception->getMessage());
    }
    $weight = filter_var($form_state->getValue('weight'), FILTER_VALIDATE_INT);
    if ($weight === FALSE || $weight < 0) {
      $form_state->setErrorByName('weight', $this->t('A posição precisa ser um inteiro não negativo.'));
    }
  }

  /**
   * Saves the activity and returns to course structure.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [$course, $lesson, $activity] = $this->routeEntities();
    $type = (string) $form_state->getValue('type');
    try {
      $configuration = ActivityConfiguration::fromFormValues($type, $form_state->getValues());
      $answerConfig = json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    catch (\JsonException | \InvalidArgumentException) {
      $this->messenger()->addError($this->t('A configuração da atividade está inválida. Revise os campos e tente novamente.'));
      return;
    }
    $values = [
      'lesson_id' => (int) $lesson->id(),
      'type' => $type,
      'graded' => (bool) $form_state->getValue('graded'),
      'weight' => (int) $form_state->getValue('weight'),
      'answer_config' => $answerConfig,
    ];
    if ($activity === NULL) {
      $activity = $this->entityTypeManager->getStorage('aculta_lms_activity')->create($values);
    }
    else {
      foreach ($values as $field => $value) {
        $activity->set($field, $value);
      }
    }
    $activity->save();
    $this->messenger()->addStatus($this->t('A atividade foi salva.'));
    $form_state->setRedirect('aculta_lms.course_structure', ['course' => $course->id()]);
  }

  /**
   * Resolves and authorizes the route entities.
   *
   * @return array{0: \Drupal\group\Entity\GroupInterface, 1: \Drupal\Core\Entity\ContentEntityInterface, 2: ?\Drupal\Core\Entity\ContentEntityInterface}
   *   The course, lesson, and optional activity.
   */
  private function routeEntities(): array {
    $course = $this->currentRouteMatch->getParameter('course');
    $lesson = $this->currentRouteMatch->getParameter('lesson');
    $activity = $this->currentRouteMatch->getParameter('activity');
    if (!$course instanceof GroupInterface || !$lesson instanceof ContentEntityInterface
      || ($activity !== NULL && !$activity instanceof ContentEntityInterface)
      || !$this->accessCheck->access($course, $lesson, $activity instanceof ContentEntityInterface ? $activity : NULL)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return [$course, $lesson, $activity];
  }

  /**
   * Finds the next activity position in a lesson.
   */
  private function nextWeight(int $lessonId): int {
    $activities = array_values(array_filter(
      $this->entityTypeManager->getStorage('aculta_lms_activity')->loadByProperties(['lesson_id' => $lessonId]),
      static fn($activity): bool => $activity instanceof ContentEntityInterface,
    ));
    $weights = array_map(static fn(ContentEntityInterface $activity): int => (int) $activity->get('weight')->value, $activities);
    return $weights === [] ? 0 : max($weights) + 1;
  }

  /**
   * Decodes stored learner-facing activity settings.
   */
  private function decodeConfiguration(string $json): array {
    try {
      $configuration = json_decode($json === '' ? '{}' : $json, TRUE, 512, JSON_THROW_ON_ERROR);
      return is_array($configuration) ? $configuration : [];
    }
    catch (\JsonException) {
      throw new \UnexpectedValueException('A configuração armazenada da atividade não é JSON válido.');
    }
  }

}
