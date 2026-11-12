<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_cgu\Adapter\ContributionAdapterRegistry;
use Drupal\aculta_cgu\Service\ContributionLifecycle;
use Drupal\aculta_events\Adapter\EventContributionAdapter;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;
use Drupal\aculta_events\Service\OrganizationContributionEventQueue;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Submits a private proposal to update an existing Event.
 */
final class EventUpdateContributionSubmissionForm extends FormBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    protected readonly EntityTypeManagerInterface $entities,
    #[Autowire(service: 'aculta_cgu.contribution_lifecycle')]
    protected readonly ContributionLifecycle $lifecycle,
    #[Autowire(service: 'aculta_cgu.contribution_adapter_registry')]
    protected readonly ContributionAdapterRegistry $adapters,
    #[Autowire(service: 'aculta_events.organization_contribution_event_queue')]
    protected readonly OrganizationContributionEventQueue $eventQueue,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_update_contribution_submission_form';
  }

  /**
   * Builds an update proposal against the current Event revision.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Event $aculta_event = NULL): array {
    if (!$aculta_event instanceof Event) {
      throw new NotFoundHttpException();
    }
    $adapter = $this->eventAdapter();
    if (!$adapter->submitAccess($this->currentUser(), 'event_update', $aculta_event)) {
      throw new NotFoundHttpException();
    }
    $uuid = (string) $aculta_event->uuid();
    $expectedVersion = $adapter->currentVersion('event_update', $aculta_event);
    $form_state->set('target_uuid', $uuid);
    $form_state->set('expected_version', $expectedVersion);
    if (!$form_state->has('submission_token')) {
      $form_state->set('submission_token', bin2hex(random_bytes(32)));
    }

    $form['intro'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('A proposta ficará privada até a revisão. O Evento oficial não muda agora; as Datas propostas serão adicionadas somente após aprovação.'),
    ];
    $form['event_title'] = [
      '#type' => 'item',
      '#title' => $this->t('Evento'),
      '#plain_text' => $aculta_event->label(),
    ];
    $form['payload'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Alterações propostas'),
      '#tree' => TRUE,
    ];
    $form['payload']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do Evento'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $form_state->getValue(['payload', 'title'], (string) $aculta_event->label()),
    ];
    foreach ([
      'field_subtitulo' => $this->t('Subtítulo'),
      'body' => $this->t('Descrição'),
      'field_url' => $this->t('Site oficial'),
    ] as $field => $label) {
      $form['payload'][$field] = [
        '#type' => $field === 'body' ? 'textarea' : ($field === 'field_url' ? 'url' : 'textfield'),
        '#title' => $label,
        '#maxlength' => $field === 'body' ? 20000 : 255,
        '#default_value' => $form_state->getValue(['payload', $field], (string) ($aculta_event->get($field)->value ?? '')),
      ];
    }
    $organizations = $this->publishedOrganizationOptions();
    $primary = $aculta_event->get('field_organizacao')->entity;
    $form['payload']['field_organizacao'] = [
      '#type' => 'select',
      '#title' => $this->t('Organização responsável'),
      '#options' => ['' => $this->t('- Nenhuma -')] + $organizations,
      '#default_value' => $form_state->getValue(['payload', 'field_organizacao'], $primary instanceof EventOrganization ? $primary->uuid() : ''),
    ];
    $coOrganizers = [];
    foreach ($aculta_event->get('field_coorganizadores')->referencedEntities() as $organization) {
      if ($organization instanceof EventOrganization) {
        $coOrganizers[] = $organization->uuid();
      }
    }
    $form['payload']['field_coorganizadores'] = [
      '#type' => 'select',
      '#title' => $this->t('Organizações coorganizadoras'),
      '#options' => $organizations,
      '#multiple' => TRUE,
      '#default_value' => $form_state->getValue(['payload', 'field_coorganizadores'], $coOrganizers),
    ];
    $form['payload']['date'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Nova Data (opcional)'),
      '#tree' => TRUE,
      'title' => [
        '#type' => 'textfield',
        '#title' => $this->t('Título da Data'),
        '#maxlength' => 255,
        '#default_value' => $form_state->getValue(['payload', 'date', 'title'], ''),
      ],
      'starts_at' => [
        '#type' => 'textfield',
        '#title' => $this->t('Início (ISO 8601 com fuso)'),
        '#default_value' => $form_state->getValue(['payload', 'date', 'starts_at'], ''),
        '#description' => $this->t('Exemplo: 2027-02-10T18:00:00-03:00.'),
      ],
      'ends_at' => [
        '#type' => 'textfield',
        '#title' => $this->t('Fim (ISO 8601 com fuso)'),
        '#default_value' => $form_state->getValue(['payload', 'date', 'ends_at'], ''),
      ],
      'location_uuid' => [
        '#type' => 'select',
        '#title' => $this->t('Local'),
        '#options' => ['' => $this->t('- Sem Local -')] + $this->publishedLocationOptions(),
        '#default_value' => $form_state->getValue(['payload', 'date', 'location_uuid'], ''),
      ],
    ];
    $form['rationale'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Justificativa da alteração'),
      '#required' => TRUE,
      '#maxlength' => 5000,
      '#default_value' => $form_state->getValue('rationale', ''),
    ];
    $form['submission_token'] = [
      '#type' => 'value',
      '#value' => $form_state->get('submission_token'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar proposta para revisão'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    try {
      $target = $this->loadAuthorizedTarget((string) $form_state->get('target_uuid'));
      $this->eventAdapter()->validatePayload('event_update', $this->submittedPayload($form_state), $target);
    }
    catch (UnprocessableEntityHttpException $exception) {
      $form_state->setErrorByName('payload', $exception->getMessage());
    }
    catch (AccessDeniedHttpException | NotFoundHttpException $exception) {
      throw new NotFoundHttpException(previous: $exception);
    }
    catch (ConflictHttpException $exception) {
      $form_state->setErrorByName('payload', $exception->getMessage());
    }
  }

  /**
   * Submits an update proposal and a minimal Messenger notice.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $target = $this->loadAuthorizedTarget((string) $form_state->get('target_uuid'));
      $adapter = $this->eventAdapter();
      $payload = $adapter->validatePayload('event_update', $this->submittedPayload($form_state), $target);
      $proposal = $this->lifecycle->submit(
        'events_event',
        'event_update',
        (string) $target->uuid(),
        (string) $this->t('Alteração proposta: @title', ['@title' => $target->label()]),
        (string) $form_state->getValue('rationale'),
        $payload,
        (string) $form_state->getValue('submission_token'),
        [],
        (string) $form_state->get('expected_version'),
      );
      $this->eventQueue->enqueue(new OrganizationContributionOutcomeRecordedEvent(
        'events_event',
        $proposal->uuid(),
        (int) $proposal->getRevisionId(),
        (int) $proposal->getOwnerId(),
        'submitted',
      ));
      $this->messenger()->addStatus($this->t('Sua proposta de alteração foi enviada para revisão.'));
      $form_state->setRedirect('aculta_events.organizer_contribution_status', ['proposal_uuid' => $proposal->uuid()]);
    }
    catch (UnprocessableEntityHttpException | ConflictHttpException $exception) {
      $form_state->setErrorByName('payload', $exception->getMessage());
    }
    catch (AccessDeniedHttpException | NotFoundHttpException $exception) {
      throw new NotFoundHttpException(previous: $exception);
    }
  }

  /**
   * Resolves a fresh target and rechecks the adapter's submission policy.
   */
  private function loadAuthorizedTarget(string $uuid): Event {
    $target = $this->eventAdapter()->loadTarget('event_update', $uuid);
    if (!$target instanceof Event || !$this->eventAdapter()->submitAccess($this->currentUser(), 'event_update', $target)) {
      throw new NotFoundHttpException();
    }
    return $target;
  }

  /**
   * Gets the registered Events adapter and fails closed if unavailable.
   */
  private function eventAdapter(): EventContributionAdapter {
    $adapter = $this->adapters->get('events_event');
    if (!$adapter instanceof EventContributionAdapter) {
      throw new NotFoundHttpException();
    }
    return $adapter;
  }

  /**
   * Maps form values to the allowlisted contribution payload.
   */
  private function submittedPayload(FormStateInterface $form_state): array {
    $values = (array) $form_state->getValue('payload');
    $date = (array) ($values['date'] ?? []);
    $dates = trim((string) ($date['starts_at'] ?? '')) === '' && trim((string) ($date['ends_at'] ?? '')) === ''
      ? []
      : [
        [
          'title' => (string) ($date['title'] ?? ''),
          'starts_at' => (string) ($date['starts_at'] ?? ''),
          'ends_at' => (string) ($date['ends_at'] ?? ''),
          'location_uuid' => (string) ($date['location_uuid'] ?? ''),
        ],
      ];
    return [
      'title' => (string) ($values['title'] ?? ''),
      'field_subtitulo' => (string) ($values['field_subtitulo'] ?? ''),
      'body' => (string) ($values['body'] ?? ''),
      'field_url' => (string) ($values['field_url'] ?? ''),
      'field_organizacao' => (string) ($values['field_organizacao'] ?? ''),
      'field_coorganizadores' => array_values(array_filter((array) ($values['field_coorganizadores'] ?? []), 'is_string')),
      'dates' => $dates,
    ];
  }

  /**
   * Lists published, viewable Organizations by UUID.
   */
  private function publishedOrganizationOptions(): array {
    $storage = $this->entities->getStorage('aculta_event_organization');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('field_status', 'publicado')->sort('title')->execute();
    $options = [];
    foreach ($storage->loadMultiple($ids) as $organization) {
      if ($organization instanceof EventOrganization && $organization->access('view', $this->currentUser())) {
        $options[$organization->uuid()] = $organization->label();
      }
    }
    return $options;
  }

  /**
   * Lists published, viewable Locations by UUID.
   */
  private function publishedLocationOptions(): array {
    $storage = $this->entities->getStorage('aculta_event_location');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('field_status', 'publicado')->sort('title')->execute();
    $options = [];
    foreach ($storage->loadMultiple($ids) as $location) {
      if ($location instanceof EventLocation && $location->access('view', $this->currentUser())) {
        $options[$location->uuid()] = $location->label();
      }
    }
    return $options;
  }

}
