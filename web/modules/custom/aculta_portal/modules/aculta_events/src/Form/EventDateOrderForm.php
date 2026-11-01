<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Event\EventDateListBuilder;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\aculta_events\Route\EventRouteContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Edita a ordem editorial das Datas dentro do Evento.
 *
 * A ordem é um peso numérico por Data. Ao salvar, o popup fecha e a lista de
 * Datas do formulário pai é atualizada por AJAX, preservando os demais campos.
 */
final class EventDateOrderForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EventDateListBuilder $dateListBuilder,
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
    return 'aculta_events_date_order';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Event $aculta_event = NULL): array {
    if (!$aculta_event instanceof Event) {
      throw new NotFoundHttpException();
    }
    $form_state->set('event_id', (int) $aculta_event->id());

    $form['event_id'] = ['#type' => 'hidden', '#value' => (string) $aculta_event->id()];
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Valores menores aparecem primeiro. Em caso de empate, vale o horário de início.') . '</p>',
    ];

    $form['weights'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['data-portal-ui' => 'event-date-order'],
    ];
    foreach ($this->dateListBuilder->loadDates($aculta_event) as $date) {
      $quando = $date->get('field_quando')->first();
      $form['weights'][$date->id()] = [
        '#type' => 'number',
        '#title' => $date->label(),
        '#description' => $quando?->value
          ? $this->t('Início: @inicio', ['@inicio' => $quando->value])
          : '',
        '#default_value' => (int) ($date->get('field_peso')->value ?? 0),
        '#min' => 0,
        '#step' => 1,
        '#required' => TRUE,
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar ordem'),
      '#ajax' => ['callback' => '::ajaxSubmit'],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancelar'),
      '#url' => Url::fromRoute(EventRouteContext::route('aculta_events.event_edit', 'aculta_events.organizer_event_edit'), ['aculta_event' => $aculta_event->id()]),
      '#attributes' => ['data-portal-ui' => 'button'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach ((array) $form_state->getValue('weights', []) as $dateId => $weight) {
      if (!is_numeric($weight) || (int) $weight < 0) {
        $form_state->setErrorByName('weights][' . $dateId, (string) $this->t('Informe uma ordem inteira maior ou igual a zero.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $event = $this->eventFromState($form_state);
    if (!$event instanceof Event || !$event->access('update', $this->currentUser(), TRUE)->isAllowed()) {
      $this->messenger()->addError($this->t('Não foi possível salvar a ordem: evento inválido.'));
      return;
    }

    $storage = $this->entities->getStorage('aculta_event_date');
    foreach ((array) $form_state->getValue('weights', []) as $dateId => $weight) {
      $date = $storage->load((int) $dateId);
      if (!$date instanceof EventDate
        || (int) $date->get('field_evento')->target_id !== (int) $event->id()) {
        continue;
      }
      $date->set('field_peso', max(0, (int) $weight));
      $date->save();
    }

    $this->messenger()->addStatus($this->t('A ordem das datas foi atualizada.'));
    $form_state->setRedirect(EventRouteContext::route('aculta_events.event_edit', 'aculta_events.organizer_event_edit'), ['aculta_event' => $event->id()]);
  }

  /**
   * Fecha o diálogo e atualiza a lista de Datas no formulário pai.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse|array {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }
    $event = $this->eventFromState($form_state);
    if (!$event instanceof Event) {
      return $form;
    }

    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new MessageCommand($this->t('A ordem das datas foi atualizada.')));
    $response->addCommand(new ReplaceCommand('#event-dates-list', $this->dateListBuilder->build($event)));
    return $response;
  }

  /**
   * Carrega o Evento do estado do formulário.
   */
  private function eventFromState(FormStateInterface $form_state): ?Event {
    $rawId = $form_state->get('event_id') ?? $form_state->getValue('event_id');
    if (!is_scalar($rawId) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
      return NULL;
    }
    $event = $this->entities->getStorage('aculta_event')->load((int) $rawId);
    return $event instanceof Event ? $event : NULL;
  }

}
