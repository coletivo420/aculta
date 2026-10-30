<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirma a exclusão de uma Data dentro do contexto do Evento.
 */
final class EventDateDeleteForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_date_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Event $aculta_event = NULL, ?EventDate $aculta_event_date = NULL): array {
    $event = $aculta_event ?? $this->entityFromState($form_state, 'event_id', 'aculta_event');
    $date = $aculta_event_date ?? $this->entityFromState($form_state, 'date_id', 'aculta_event_date');
    if (!$event instanceof Event || !$date instanceof EventDate
      || !$event->access('update', $this->currentUser(), TRUE)->isAllowed()
      || (int) $date->get('field_evento')->target_id !== (int) $event->id()) {
      throw new NotFoundHttpException();
    }

    $form_state->set('event_id', (int) $event->id());
    $form_state->set('date_id', (int) $date->id());

    $form['event_id'] = ['#type' => 'hidden', '#value' => (string) $event->id()];
    $form['date_id'] = ['#type' => 'hidden', '#value' => (string) $date->id()];

    $form['message'] = [
      '#markup' => '<p>' . $this->t('Você tem certeza que deseja remover a data %title do evento %event?', [
        '%title' => $date->label(),
        '%event' => $event->label(),
      ]) . '</p>',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remover data'),
      '#attributes' => ['data-portal-ui' => 'event-date-delete'],
      '#ajax' => ['callback' => '::ajaxSubmit'],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancelar'),
      '#url' => Url::fromRoute('aculta_events.event_edit', ['aculta_event' => $event->id()]),
      '#attributes' => ['data-portal-ui' => 'event-date-cancel'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $event = $this->entityFromState($form_state, 'event_id', 'aculta_event');
    $date = $this->entityFromState($form_state, 'date_id', 'aculta_event_date');
    if (!$event instanceof Event || !$date instanceof EventDate
      || !$event->access('update', $this->currentUser(), TRUE)->isAllowed()
      || (int) $date->get('field_evento')->target_id !== (int) $event->id()) {
      $this->messenger()->addError($this->t('Não foi possível remover a data: contexto do evento inválido.'));
      return;
    }

    $title = $date->label();
    $date->delete();
    $this->messenger()->addStatus($this->t('A data %title foi removida.', ['%title' => $title]));
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
   * Carrega uma entidade a partir do estado do formulário.
   */
  private function entityFromState(FormStateInterface $form_state, string $stateKey, string $entityType): ?object {
    $rawId = $form_state->get($stateKey) ?? $form_state->getValue($stateKey);
    if (!is_scalar($rawId) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
      return NULL;
    }
    return $this->entities->getStorage($entityType)->load((int) $rawId);
  }

}
