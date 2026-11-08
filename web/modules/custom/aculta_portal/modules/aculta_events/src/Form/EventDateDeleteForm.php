<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Event\EventDateListBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\aculta_events\Route\EventRouteContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirma a exclusão de uma Data dentro do contexto do Evento.
 */
final class EventDateDeleteForm extends FormBase {

  use HtmxDateListResponseTrait;

  /**
   * Wrapper do formulário trocado pelo envio HTMX.
   */
  private const WRAPPER_ID = 'aculta-event-date-delete-form';

  public function __construct(
    protected readonly EntityTypeManagerInterface $entities,
    protected readonly EventDateListBuilder $dateListBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_events.date_list_builder'),
    );
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

    $form['#prefix'] = '<div id="' . self::WRAPPER_ID . '">';
    $form['#suffix'] = '</div>';
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
    ];
    (new Htmx())
      ->post($this->htmxRouteUrl(
        'aculta_events.event_date_delete',
        'aculta_events.organizer_event_date_delete',
        ['aculta_event' => $event->id(), 'aculta_event_date' => $date->id()],
      ))
      ->select('#' . self::WRAPPER_ID)
      ->target('#' . self::WRAPPER_ID)
      ->swap('outerHTML')
      ->onlyMainContent()
      ->applyTo($form['actions']['submit']);
    $form['actions']['cancel'] = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:button',
      '#props' => [
        'text' => (string) $this->t('Cancelar'),
        'url' => Url::fromRoute(EventRouteContext::route('aculta_events.event_edit', 'aculta_events.organizer_event_edit'), ['aculta_event' => $event->id()])->toString(),
        'variant' => 'secondary',
      ],
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
    if ($this->isHtmxRequest()) {
      $form_state->setResponse($this->htmxDateListResponse($event));
      return;
    }
    $form_state->setRedirect(EventRouteContext::route('aculta_events.event_edit', 'aculta_events.organizer_event_edit'), ['aculta_event' => $event->id()]);
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
