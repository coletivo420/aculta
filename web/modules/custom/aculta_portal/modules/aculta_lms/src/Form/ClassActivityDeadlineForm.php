<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds, changes, or clears the deadline for one activity in a class. */
final class ClassActivityDeadlineForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly CurrentRouteMatch $deadlineRouteMatch,
    protected readonly AccountProxyInterface $account,
  ) {}

  /**
   * Creates the form with route, account, and entity services.
   */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('current_user'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * Returns the stable form ID.
   */
  public function getFormId(): string {
    return 'aculta_lms_class_activity_deadline';
  }

  /**
   * Builds the deadline editor for the activity in the requested class.
   *
   * @param array<array-key, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   *
   * @return array<array-key, mixed>
   *   Render array returned by the method.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    [$course, $class, $activity, $relationship] = $this->routeEntities();
    $timezone = $this->accountTimezone();
    $default = NULL;
    if ($relationship instanceof GroupRelationshipInterface && !$relationship->get('field_activity_due_at')->isEmpty()) {
      $storedDueAt = $relationship->get('field_activity_due_at')->value;
      if (is_string($storedDueAt) && $storedDueAt !== '') {
        try {
          $parsed = DrupalDateTime::createFromFormat(
            DateTimeItemInterface::DATETIME_STORAGE_FORMAT,
            $storedDueAt,
            new \DateTimeZone('UTC'),
          );
          $default = $parsed->setTimezone(new \DateTimeZone($timezone));
        }
        catch (\InvalidArgumentException | \UnexpectedValueException) {
          // Ignore malformed persisted dates and leave the form field empty.
        }
      }
    }

    $classLabel = self::stringLabel($class->label());
    $form['intro'] = [
      '#type' => 'item',
      '#title' => $this->t('Prazo da atividade na turma @class', ['@class' => $classLabel]),
      '#description' => $this->t('A mesma atividade pode ter prazos diferentes em cada turma. O horário usa o fuso da sua conta: @timezone.', ['@timezone' => $timezone]),
    ];
    $form['activity'] = [
      '#type' => 'item',
      '#title' => $this->t('Atividade'),
      '#plain_text' => $activity->label(),
    ];
    $form['due_at'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Data e horário de entrega'),
      '#default_value' => $default,
      '#date_timezone' => $timezone,
      '#required' => FALSE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar prazo'),
      '#button_type' => 'primary',
    ];
    if ($relationship instanceof GroupRelationshipInterface) {
      $form['actions']['clear'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remover prazo'),
        '#submit' => ['::clearDeadline'],
        '#limit_validation_errors' => [],
        '#attributes' => ['data-portal-ui' => 'button-danger'],
      ];
    }
    $form['actions']['cancel'] = [
      '#type' => 'portal_button',
      '#props' => [
        'text' => (string) $this->t('Voltar ao painel da turma'),
        'url' => Url::fromRoute('aculta_lms.class_dashboard', ['course' => $course->id(), 'group' => $class->id()])->toString(),
        'variant' => 'secondary',
      ],
    ];
    return $form;
  }

  /**
   * Rejects invalid dates and dates that have already passed.
   *
   * @param array<array-key, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $date = $form_state->getValue(['due_at', 'object']);
    if ($date === NULL) {
      return;
    }
    if (!$date instanceof DrupalDateTime || $date->hasErrors()) {
      $form_state->setErrorByName('due_at', $this->t('Informe uma data e um horário válidos.'));
      return;
    }
    if ($date->getTimestamp() <= time()) {
      $form_state->setErrorByName('due_at', $this->t('O prazo precisa estar no futuro.'));
    }
  }

  /**
   * Persists the date on the Group relationship for this class and activity.
   *
   * @param array<array-key, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [$course, $class, $activity, $relationship] = $this->routeEntities();
    $date = $form_state->getValue(['due_at', 'object']);
    if (!$date instanceof DrupalDateTime || $date->hasErrors()) {
      $this->messenger()->addError($this->t('Informe a data e o horário para salvar o prazo.'));
      return;
    }
    $date->setTimezone(new \DateTimeZone('UTC'));
    $storedValue = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    if ($relationship instanceof GroupRelationshipInterface) {
      $relationship->set('field_activity_due_at', $storedValue)->save();
    }
    else {
      $class->addRelationship($activity, 'lms_class_activity_due', [
        'field_activity_due_at' => $storedValue,
      ]);
    }
    $this->messenger()->addStatus($this->t('O prazo da atividade foi salvo.'));
    $form_state->setRedirect('aculta_lms.class_dashboard', ['course' => $course->id(), 'group' => $class->id()]);
  }

  /**
   * Clears the deadline relation while preserving the LMS activity.
   *
   * @param array<array-key, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function clearDeadline(array &$form, FormStateInterface $form_state): void {
    [$course, $class, , $relationship] = $this->routeEntities();
    if ($relationship instanceof GroupRelationshipInterface) {
      $relationship->delete();
      $this->messenger()->addStatus($this->t('O prazo foi removido.'));
    }
    $form_state->setRedirect('aculta_lms.class_dashboard', ['course' => $course->id(), 'group' => $class->id()]);
  }

  /**
   * Returns the route's class, activity, and existing due-date relation.
   *
   * @return array{0: \Drupal\group\Entity\GroupInterface, 1: \Drupal\group\Entity\GroupInterface, 2: \Drupal\Core\Entity\ContentEntityInterface, 3: ?\Drupal\group\Entity\GroupRelationshipInterface}
   *   The course, class, activity, and optional existing deadline relation.
   */
  private function routeEntities(): array {
    $class = $this->deadlineRouteMatch->getParameter('group');
    $course = $this->deadlineRouteMatch->getParameter('course');
    $activity = $this->deadlineRouteMatch->getParameter('activity');
    if (!$course instanceof GroupInterface || !$class instanceof GroupInterface || !$activity instanceof ContentEntityInterface
      || $activity->getEntityTypeId() !== 'aculta_lms_activity') {
      throw new \LogicException('The class activity deadline route requires a course, class and activity.');
    }
    $relationships = $class->getRelationshipsByEntity($activity, 'lms_class_activity_due');
    $candidate = $relationships === [] ? NULL : reset($relationships);
    $relationship = $candidate instanceof GroupRelationshipInterface ? $candidate : NULL;
    return [$course, $class, $activity, $relationship];
  }

  /**
   * Resolves the current account's preferred timezone. */
  private function accountTimezone(): string {
    $account = $this->entityTypeManager->getStorage('user')->load($this->account->id());
    return $account && $account->getTimeZone() ? $account->getTimeZone() : 'UTC';
  }

  /**
   * Returns a safe text value for labels from mixed entity APIs.
   */
  private static function stringLabel(mixed $label): string {
    if (is_string($label)) {
      return $label;
    }
    return $label instanceof \Stringable ? (string) $label : '';
  }

}
