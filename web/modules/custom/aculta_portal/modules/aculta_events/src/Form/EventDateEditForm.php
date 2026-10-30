<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Plugin\Field\FieldWidget\EventLocationModalWidget;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Edita uma Data a partir do Evento ao qual ela pertence.
 *
 * A rota exige acesso de atualização ao Evento e o vínculo Data > Evento é
 * verificado no servidor. Ao concluir, a pessoa retorna ao Evento de origem.
 */
final class EventDateEditForm extends EventDateCreateForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_date_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Event $aculta_event = NULL, ?EventDate $aculta_event_date = NULL): array {
    $event = $aculta_event ?? $this->eventFromState($form_state);
    $date = $aculta_event_date ?? $this->dateFromState($form_state);
    if (!$event instanceof Event || !$date instanceof EventDate || !$this->canEditEvent($event)
      || (int) $date->get('field_evento')->target_id !== (int) $event->id()) {
      throw new NotFoundHttpException();
    }

    $form_state->set('event_id', (int) $event->id());
    $form_state->set('date_id', (int) $date->id());

    $form['event_id'] = ['#type' => 'hidden', '#value' => (string) $event->id()];
    $form['date_id'] = ['#type' => 'hidden', '#value' => (string) $date->id()];

    $form['event_summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Evento'),
      '#markup' => (string) $event->label(),
    ];

    $quando = $date->get('field_quando')->first();
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título da data'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => (string) $date->label(),
    ];
    $form['starts_at'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Início'),
      '#required' => TRUE,
      '#default_value' => $quando?->value ? (new DrupalDateTime($quando->value, 'UTC'))->setTimezone(new \DateTimeZone(date_default_timezone_get())) : NULL,
    ];
    $form['ends_at'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Fim'),
      '#required' => TRUE,
      '#default_value' => $quando?->end_value ? (new DrupalDateTime($quando->end_value, 'UTC'))->setTimezone(new \DateTimeZone(date_default_timezone_get())) : NULL,
    ];

    $form['local_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Local do evento'),
      '#open' => TRUE,
    ];

    $localId = 0;
    $rawLocal = $form_state->getValue('local_id');
    if (is_array($rawLocal)) {
      $localId = (int) ($rawLocal['target_id'] ?? 0);
    }
    elseif (is_numeric($rawLocal)) {
      $localId = (int) $rawLocal;
    }
    else {
      $localId = (int) ($date->get('field_local')->target_id ?? 0);
    }

    $wrapperId = 'event-date-local-wrapper';
    $form['local_section']['local_id'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapperId, 'data-portal-ui' => 'event-location-widget'],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ] + EventLocationModalWidget::buildCardOrEmpty($localId, 'local_id', $wrapperId);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar data'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $event = $this->eventFromState($form_state);
    $date = $this->dateFromState($form_state);
    if (!$event instanceof Event || !$date instanceof EventDate || !$this->canEditEvent($event)
      || (int) $date->get('field_evento')->target_id !== (int) $event->id()) {
      $form_state->setErrorByName('', (string) $this->t('A data não pertence ao evento informado.'));
      return;
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $event = $this->eventFromState($form_state);
    $date = $this->dateFromState($form_state);
    $start = $this->utcValue($form_state->getValue('starts_at'));
    $end = $this->utcValue($form_state->getValue('ends_at'));
    if (!$event instanceof Event || !$date instanceof EventDate || !$this->canEditEvent($event)
      || (int) $date->get('field_evento')->target_id !== (int) $event->id()
      || $start === NULL || $end === NULL || $end <= $start) {
      $this->messenger()->addError($this->t('Não foi possível validar o evento e o intervalo da data.'));
      return;
    }

    $selectedLocal = $form_state->getValue('local_id');
    if (is_array($selectedLocal)) {
      $selectedLocal = $selectedLocal['target_id'] ?? 0;
    }
    $locationId = (is_numeric($selectedLocal) && (int) $selectedLocal > 0) ? (int) $selectedLocal : NULL;

    $date->set('title', (string) $form_state->getValue('title'));
    $date->set('field_quando', ['value' => $start, 'end_value' => $end]);
    $date->set('field_local', $locationId !== NULL ? ['target_id' => $locationId] : []);
    $date->save();

    $this->messenger()->addStatus($this->t('A data foi atualizada.'));
    $form_state->setRedirect('aculta_events.event_edit', ['aculta_event' => $event->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse|array {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }

    $eventId = (int) ($form_state->get('event_id') ?? 0);
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new RedirectCommand(
      Url::fromRoute('aculta_events.event_edit', ['aculta_event' => $eventId])->toString()
    ));
    return $response;
  }

  /**
   * Carrega a Data guardada no estado do formulário.
   */
  protected function dateFromState(FormStateInterface $form_state): ?EventDate {
    $rawId = $form_state->get('date_id') ?? $form_state->getValue('date_id');
    if (!is_scalar($rawId) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
      return NULL;
    }
    $date = $this->entities->getStorage('aculta_event_date')->load((int) $rawId);
    return $date instanceof EventDate ? $date : NULL;
  }

}
