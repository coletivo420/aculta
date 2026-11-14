<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_cgu\Adapter\ContributionAdapterRegistry;
use Drupal\aculta_cgu\Service\ContributionLifecycle;
use Drupal\aculta_events\Adapter\EventContributionAdapter;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;
use Drupal\aculta_events\Service\OrganizationContributionEventQueue;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Submits a private proposal to create an Event and its initial Dates.
 */
final class EventContributionSubmissionForm extends FormBase {

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
    return 'aculta_events_contribution_submission_form';
  }

  /**
   * Builds a typed form; no Event entity is saved before review.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $adapter = $this->eventAdapter();
    if (!$adapter->submitAccess($this->currentUser(), 'event_create', NULL)) {
      throw new AccessDeniedHttpException();
    }
    if ($form_state->get('date_delta') === NULL) {
      $form_state->set('date_delta', 1);
    }
    if (!$form_state->has('submission_token')) {
      $form_state->set('submission_token', bin2hex(random_bytes(32)));
    }

    $form['intro'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('Sua proposta e as Datas ficarão privadas até a revisão editorial. Um Evento só será criado após aprovação.'),
    ];
    $form['payload'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dados do Evento'),
      '#tree' => TRUE,
    ];
    $form['payload']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do Evento'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $form_state->getValue(['payload', 'title'], ''),
    ];
    $form['payload']['field_subtitulo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subtítulo'),
      '#maxlength' => 255,
      '#default_value' => $form_state->getValue(['payload', 'field_subtitulo'], ''),
    ];
    $form['payload']['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Descrição'),
      '#maxlength' => 20000,
      '#default_value' => $form_state->getValue(['payload', 'body'], ''),
    ];
    $form['payload']['field_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Site oficial'),
      '#maxlength' => 255,
      '#default_value' => $form_state->getValue(['payload', 'field_url'], ''),
    ];
    $organizationOptions = $this->publishedOrganizationOptions();
    $form['payload']['field_organizacao'] = [
      '#type' => 'select',
      '#title' => $this->t('Organização responsável'),
      '#options' => ['' => $this->t('- Nenhuma -')] + $organizationOptions,
      '#default_value' => $form_state->getValue(['payload', 'field_organizacao'], ''),
    ];
    $form['payload']['field_coorganizadores'] = [
      '#type' => 'select',
      '#title' => $this->t('Organizações coorganizadoras'),
      '#options' => $organizationOptions,
      '#multiple' => TRUE,
      '#default_value' => $form_state->getValue(['payload', 'field_coorganizadores'], []),
    ];
    $form['payload']['dates'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Datas'),
      '#tree' => TRUE,
    ];
    for ($delta = 0; $delta < (int) $form_state->get('date_delta'); $delta++) {
      $row = (array) $form_state->getValue(['payload', 'dates', $delta], []);
      $form['payload']['dates'][$delta] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Data @number', ['@number' => $delta + 1]),
        '#tree' => TRUE,
        'title' => [
          '#type' => 'textfield',
          '#title' => $this->t('Título da Data (opcional)'),
          '#maxlength' => 255,
          '#default_value' => (string) ($row['title'] ?? ''),
        ],
        'starts_at' => [
          '#type' => 'textfield',
          '#title' => $this->t('Início (ISO 8601)'),
          '#description' => $this->t('Informe a data e hora com fuso, por exemplo 2027-02-10T18:00:00-03:00.'),
          '#required' => TRUE,
          '#default_value' => (string) ($row['starts_at'] ?? ''),
        ],
        'ends_at' => [
          '#type' => 'textfield',
          '#title' => $this->t('Fim (ISO 8601)'),
          '#required' => TRUE,
          '#default_value' => (string) ($row['ends_at'] ?? ''),
        ],
        'location_uuid' => [
          '#type' => 'select',
          '#title' => $this->t('Local'),
          '#options' => ['' => $this->t('- Sem Local -')] + $this->publishedLocationOptions(),
          '#default_value' => (string) ($row['location_uuid'] ?? ''),
        ],
      ];
    }
    $form['add_date'] = [
      '#type' => 'submit',
      '#value' => $this->t('Adicionar outra Data'),
      '#submit' => ['::addDate'],
      '#limit_validation_errors' => [],
      '#name' => 'add_event_contribution_date',
    ];
    $form['rationale'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Por que este Evento deve ser incluído?'),
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
   * Adds another date row without validating incomplete rows.
   */
  public function addDate(array &$form, FormStateInterface $form_state): void {
    $form_state->set('date_delta', min(30, (int) $form_state->get('date_delta') + 1));
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->eventAdapter()->validatePayload(
        'event_create',
        $this->submittedPayload($form_state),
      );
    }
    catch (UnprocessableEntityHttpException $exception) {
      $form_state->setErrorByName('payload', $exception->getMessage());
    }
  }

  /**
   * Persists a private Contribution, never the official Event entity.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $payload = $this->eventAdapter()->validatePayload(
        'event_create',
        $this->submittedPayload($form_state),
      );
      $proposal = $this->lifecycle->submit(
        'events_event',
        'event_create',
        NULL,
        (string) $payload['title'],
        (string) $form_state->getValue('rationale'),
        $payload,
        (string) $form_state->getValue('submission_token'),
      );
      $this->eventQueue->enqueue(new OrganizationContributionOutcomeRecordedEvent(
        'events_event',
        $proposal->uuid(),
        (int) $proposal->getRevisionId(),
        (int) $proposal->getOwnerId(),
        'submitted',
      ));
      $this->messenger()->addStatus($this->t('Sua proposta foi enviada para revisão. Acompanhe o andamento em Minhas propostas.'));
      $form_state->setRedirect('aculta_events.organizer_contribution_status', [
        'proposal_uuid' => $proposal->uuid(),
      ]);
    }
    catch (AccessDeniedHttpException) {
      throw new AccessDeniedHttpException();
    }
    catch (UnprocessableEntityHttpException $exception) {
      $form_state->setErrorByName('payload', $exception->getMessage());
    }
  }

  /**
   * Gets the current Events adapter and fails closed if it is unavailable.
   */
  private function eventAdapter(): EventContributionAdapter {
    $adapter = $this->adapters->get('events_event');
    if (!$adapter instanceof EventContributionAdapter) {
      throw new AccessDeniedHttpException();
    }
    return $adapter;
  }

  /**
   * Maps typed form values to the adapter's public payload contract.
   *
   * @return array<string, mixed>
   *   Allowlisted Event payload.
   */
  private function submittedPayload(FormStateInterface $form_state): array {
    $values = (array) $form_state->getValue('payload');
    $payload = [
      'title' => (string) ($values['title'] ?? ''),
      'field_subtitulo' => (string) ($values['field_subtitulo'] ?? ''),
      'body' => (string) ($values['body'] ?? ''),
      'field_url' => (string) ($values['field_url'] ?? ''),
      'field_organizacao' => (string) ($values['field_organizacao'] ?? ''),
      'field_coorganizadores' => array_values(array_filter(
        (array) ($values['field_coorganizadores'] ?? []),
        'is_string',
      )),
      'dates' => array_values((array) ($values['dates'] ?? [])),
    ];
    return $payload;
  }

  /**
   * Lists published Organizations as UUID-selectable references.
   *
   * @return array<string, string>
   *   UUID to label map.
   */
  private function publishedOrganizationOptions(): array {
    $storage = $this->entities->getStorage('aculta_event_organization');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('field_status', 'publicado')
      ->sort('title')
      ->execute();
    $options = [];
    foreach ($storage->loadMultiple($ids) as $organization) {
      if ($organization instanceof EventOrganization && $organization->access('view', $this->currentUser())) {
        $options[$organization->uuid()] = $organization->label();
      }
    }
    return $options;
  }

  /**
   * Lists published Locations the author may view.
   *
   * @return array<string, string>
   *   UUID to label map.
   */
  private function publishedLocationOptions(): array {
    $storage = $this->entities->getStorage('aculta_event_location');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('field_status', 'publicado')
      ->sort('title')
      ->execute();
    $options = [];
    foreach ($storage->loadMultiple($ids) as $location) {
      if ($location instanceof EventLocation && $location->access('view', $this->currentUser())) {
        $options[$location->uuid()] = $location->label();
      }
    }
    return $options;
  }

}
