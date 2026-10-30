<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Plugin\Field\FieldWidget\EventLocationModalWidget;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cria uma Data dentro do contexto de um Evento.
 *
 * A Data não é uma tarefa editorial independente: o Evento de origem vem da
 * rota, é obrigatório e a autorização é revalidada no servidor no submit.
 */
class EventDateCreateForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entities,
    protected readonly AccountProxyInterface $currentUserAccount,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_date_create';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Event $aculta_event = NULL): array {
    $event = $aculta_event ?? $this->eventFromState($form_state);
    if (!$event instanceof Event || !$this->canEditEvent($event)) {
      throw new NotFoundHttpException();
    }

    $form_state->set('event_id', (int) $event->id());

    $form['event_id'] = [
      '#type' => 'hidden',
      '#value' => (string) $event->id(),
    ];

    $form['event_summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Evento'),
      '#markup' => (string) $event->label(),
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título da data'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => (string) $event->label(),
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

    $wrapperId = 'event-date-local-wrapper';
    $form['local_section']['local_id'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapperId, 'class' => ['aculta-event-location-widget']],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ] + EventLocationModalWidget::buildCardOrEmpty($localId, 'local_id', $wrapperId);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Criar data'),
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
    if (!$event instanceof Event || !$this->canEditEvent($event)) {
      $form_state->setErrorByName('', (string) $this->t('Selecione um evento que você possa editar.'));
      return;
    }

    $start = $this->utcValue($form_state->getValue('starts_at'));
    $end = $this->utcValue($form_state->getValue('ends_at'));
    if ($start === NULL || $end === NULL || $end <= $start) {
      $form_state->setErrorByName('ends_at', (string) $this->t('O fim deve ser posterior ao início.'));
    }

    $selectedLocal = $form_state->getValue('local_id');
    $localId = is_array($selectedLocal) ? ($selectedLocal['target_id'] ?? 0) : $selectedLocal;
    if (!empty($localId)) {
      if (!is_numeric($localId) || (int) $localId < 1) {
        $form_state->setErrorByName('local_id', (string) $this->t('O local selecionado é inválido.'));
      }
      else {
        $loc = $this->entities->getStorage('aculta_event_location')->load((int) $localId);
        if (!$loc instanceof EventLocation || !$loc->access('view', $this->currentUserAccount)) {
          $form_state->setErrorByName('local_id', (string) $this->t('O local selecionado não existe ou não é acessível.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $event = $this->eventFromState($form_state);
    $start = $this->utcValue($form_state->getValue('starts_at'));
    $end = $this->utcValue($form_state->getValue('ends_at'));
    if (!$event instanceof Event || !$this->canEditEvent($event) || $start === NULL || $end === NULL || $end <= $start) {
      $this->messenger()->addError($this->t('Não foi possível validar o evento e o intervalo da data.'));
      return;
    }

    $locationId = NULL;
    $selectedLocal = $form_state->getValue('local_id');
    if (is_array($selectedLocal)) {
      $selectedLocal = $selectedLocal['target_id'] ?? 0;
    }
    if (!empty($selectedLocal) && is_numeric($selectedLocal) && (int) $selectedLocal > 0) {
      $locationId = (int) $selectedLocal;
    }

    $dateData = [
      'title' => (string) $form_state->getValue('title'),
      'field_evento' => (int) $event->id(),
      'field_quando' => ['value' => $start, 'end_value' => $end],
    ];
    if ($locationId !== NULL && $locationId > 0) {
      $dateData['field_local'] = $locationId;
    }

    $date = $this->entities->getStorage('aculta_event_date')->create($dateData);
    $date->save();
    $this->messenger()->addStatus($this->t('A data foi criada.'));
    $form_state->setRedirect('aculta_events.event_edit', ['aculta_event' => $event->id()]);
  }

  /**
   * Fecha o diálogo e retorna ao Evento de origem.
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
   * Carrega o Evento guardado no estado do formulário.
   */
  protected function eventFromState(FormStateInterface $form_state): ?Event {
    $rawId = $form_state->get('event_id') ?? $form_state->getValue('event_id');
    if (!is_scalar($rawId) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
      return NULL;
    }
    $event = $this->entities->getStorage('aculta_event')->load((int) $rawId);
    return $event instanceof Event ? $event : NULL;
  }

  /**
   * Confirma que a conta atual pode atualizar o Evento.
   */
  protected function canEditEvent(Event $event): bool {
    return $event->access('update', $this->currentUserAccount, TRUE)->isAllowed();
  }

  /**
   * Normaliza um valor de data para o formato UTC da entidade.
   */
  protected function utcValue(mixed $value): ?string {
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
