<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_events\Entity\Event;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Creates an event date and optionally preselects an editable event.
 *
 * E4 is a new ACULTA form. It uses no code from `nodePrepareForm` and copies
 * only the `field_evento` reference after checking update access.
 */
final class EventDateCreateForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $currentUserAccount,
    private readonly RequestStack $eventRequestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_date_create';
  }

  /**
   * Builds a date form with no extra copy beyond the checked event reference.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $event = $this->editableEventFromQuery();
    $form['event_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Evento'),
      '#target_type' => 'aculta_event',
      '#required' => TRUE,
      '#default_value' => $event,
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título da data'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['starts_at'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Início'),
      '#required' => TRUE,
    ];
    $form['ends_at'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Fim'),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Criar data'),
    ];
    return $form;
  }

  /**
   * Validates that the selected event is editable and the interval is valid.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $event = $this->selectedEditableEvent((string) $form_state->getValue('event_id'));
    if ($event === NULL) {
      $form_state->setErrorByName('event_id', $this->t('Selecione um evento que você possa editar.'));
      return;
    }
    $start = $this->utcValue($form_state->getValue('starts_at'));
    $end = $this->utcValue($form_state->getValue('ends_at'));
    if ($start === NULL || $end === NULL || $end <= $start) {
      $form_state->setErrorByName('ends_at', $this->t('O fim deve ser posterior ao início.'));
    }
  }

  /**
   * Saves only the selected event reference and the fields entered here.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $event = $this->selectedEditableEvent((string) $form_state->getValue('event_id'));
    $start = $this->utcValue($form_state->getValue('starts_at'));
    $end = $this->utcValue($form_state->getValue('ends_at'));
    if ($event === NULL || $start === NULL || $end === NULL || $end <= $start) {
      $this->messenger()->addError($this->t('Não foi possível validar o evento e o intervalo da data.'));
      return;
    }

    $date = $this->entities->getStorage('aculta_event_date')->create([
      'title' => (string) $form_state->getValue('title'),
      'field_evento' => $event->id(),
      'field_quando' => ['value' => $start, 'end_value' => $end],
    ]);
    $date->save();
    $this->messenger()->addStatus($this->t('A data foi criada.'));
    $form_state->setRedirect('aculta_events.date_add');
  }

  /**
   * Loads the query event only when this account may edit it.
   */
  private function editableEventFromQuery(): ?Event {
    $raw_id = $this->eventRequestStack->getCurrentRequest()?->query->get('event_id');
    if (!is_scalar($raw_id) || !ctype_digit((string) $raw_id) || (int) $raw_id < 1) {
      return NULL;
    }
    return $this->selectedEditableEvent((string) $raw_id);
  }

  /**
   * Loads an event and returns it only if update access is allowed.
   */
  private function selectedEditableEvent(string $raw_id): ?Event {
    if (!ctype_digit($raw_id) || (int) $raw_id < 1) {
      return NULL;
    }
    $event = $this->entities->getStorage('aculta_event')->load((int) $raw_id);
    if (!$event instanceof Event
      || !$event->access('update', $this->currentUserAccount, TRUE)->isAllowed()) {
      return NULL;
    }
    return $event;
  }

  /**
   * Normalizes a date element value to the entity's UTC storage form.
   */
  private function utcValue(mixed $value): ?string {
    if ($value instanceof DrupalDateTime) {
      return $value->getPhpDateTime()
        ->setTimezone(new \DateTimeZone('UTC'))
        ->format('Y-m-d\TH:i:s');
    }
    if (!is_string($value) || $value === '') {
      return NULL;
    }
    try {
      return (new \DateTimeImmutable(
        $value,
        new \DateTimeZone(date_default_timezone_get()),
      ))
        ->setTimezone(new \DateTimeZone('UTC'))
        ->format('Y-m-d\TH:i:s');
    }
    catch (\Exception) {
      return NULL;
    }
  }

}
