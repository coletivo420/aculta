<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_contributions\Adapter\ContributionAdapterRegistry;
use Drupal\aculta_contributions\Entity\Contribution;
use Drupal\aculta_contributions\Service\ContributionLifecycle;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\aculta_portal\Account\StreetNumberField;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Lets an author revise an Events-domain proposal returned for changes.
 */
final class EventContributionRevisionForm extends FormBase {

  /**
   * The contribution being revised in the current form build.
   */
  private ?Contribution $proposal = NULL;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
    #[Autowire(service: 'aculta_contributions.lifecycle')]
    private readonly ContributionLifecycle $lifecycle,
    #[Autowire(service: 'aculta_contributions.adapter_registry')]
    private readonly ContributionAdapterRegistry $adapters,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_contribution_revision_form';
  }

  /**
   * Builds a resubmission form for the owning author only.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $proposal_uuid = ''): array {
    $proposal = $this->loadProposal($proposal_uuid);
    if ($proposal->get('state')->value !== 'needs_changes'
      || !$proposal->access('update', $this->currentUser())) {
      throw new NotFoundHttpException();
    }
    $this->proposal = $proposal;
    $form_state->set('proposal_id', (int) $proposal->id());
    $form_state->set('proposal_source', (string) $proposal->get('source')->value);
    $form['revision_id'] = [
      '#type' => 'value',
      '#value' => (int) $proposal->getRevisionId(),
    ];
    $form['review_feedback'] = [
      '#type' => 'item',
      '#title' => $this->t('Retorno da revisão'),
      '#plain_text' => (string) $proposal->get('decision_note')->value,
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resumo da proposta'),
      '#default_value' => (string) $proposal->get('subject')->value,
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['rationale'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Justificativa atualizada'),
      '#default_value' => (string) $proposal->get('rationale')->value,
      '#maxlength' => 5000,
      '#required' => TRUE,
    ];
    $form['payload'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Conteúdo revisado'),
      '#tree' => TRUE,
    ];
    foreach ($proposal->getPayload() as $field => $value) {
      $this->buildPayloadField($form['payload'], (string) $field, $value);
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reenviar para revisão'),
    ];
    return $form;
  }

  /**
   * Validates the public allowlisted values using the registered adapter.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    try {
      $proposal = $this->loadProposalById((int) $form_state->get('proposal_id'));
      $source = (string) $form_state->get('proposal_source');
      $domainAdapter = $this->adapters->get($source);
      $targetUuid = $proposal->get('target_uuid')->value;
      $target = $domainAdapter->loadTarget(
        (string) $proposal->get('intent')->value,
        $targetUuid !== NULL && $targetUuid !== '' ? (string) $targetUuid : NULL,
      );
      $domainAdapter->validatePayload(
        (string) $proposal->get('intent')->value,
        $this->extractPayload($form, $form_state, $proposal),
        $target,
      );
    }
    catch (UnprocessableEntityHttpException $exception) {
      $form_state->setErrorByName('payload', $exception->getMessage());
    }
    catch (AccessDeniedHttpException | NotFoundHttpException $exception) {
      throw new NotFoundHttpException(previous: $exception);
    }
  }

  /**
   * Saves a new immutable proposal revision and returns to its status page.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $proposal = $this->loadProposalById((int) $form_state->get('proposal_id'));
    try {
      $this->lifecycle->revise(
        $proposal,
        (string) $form_state->getValue('subject'),
        (string) $form_state->getValue('rationale'),
        $this->extractPayload($form, $form_state, $proposal),
        (int) $form_state->getValue('revision_id'),
      );
      $this->messenger()->addStatus($this->t('Sua proposta foi reenviada para revisão.'));
      $form_state->setRedirect('aculta_events.organizer_contribution_status', [
        'proposal_uuid' => $proposal->uuid(),
      ]);
    }
    catch (ConflictHttpException | UnprocessableEntityHttpException $exception) {
      $form_state->setErrorByName('payload', $exception->getMessage());
    }
    catch (AccessDeniedHttpException | NotFoundHttpException) {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Adds a typed widget for each field in the allowlisted proposal payload.
   *
   * @param array<string, mixed> $form
   *   Payload fieldset.
   * @param string $field
   *   Allowlisted payload field name.
   * @param mixed $value
   *   Existing value to edit.
   */
  private function buildPayloadField(array &$form, string $field, mixed $value): void {
    $labels = [
      'title' => $this->t('Nome ou título'),
      'body' => $this->t('Descrição'),
      'imagem' => $this->t('Imagem ou logotipo'),
      'links' => $this->t('Links oficiais'),
      'localidade' => $this->t('Cidade'),
      'uf' => $this->t('UF'),
      'ocultar_localidade' => $this->t('Ocultar cidade no perfil público'),
      'field_tipo' => $this->t('Tipo do Local'),
      'field_endereco' => $this->t('Endereço do Local (Brasil)'),
      'field_precisao' => $this->t('Precisão pública do endereço'),
      'field_horario' => $this->t('Horário de funcionamento'),
      'field_descricao' => $this->t('Descrição do Local'),
      'field_telefone' => $this->t('Telefone de contato'),
      'field_site' => $this->t('Site do Local'),
      'field_subtitulo' => $this->t('Subtítulo do Evento'),
      'field_url' => $this->t('Site do Evento'),
      'field_organizacao' => $this->t('Organização responsável'),
      'field_coorganizadores' => $this->t('Organizações coorganizadoras'),
      'dates' => $this->t('Datas propostas'),
    ];
    $source = (string) ($this->proposal?->get('source')->value ?? '');
    $allowedFields = match ($source) {
      'events_organization' => [
        'title', 'body', 'imagem', 'links', 'localidade', 'uf', 'ocultar_localidade',
      ],
      'events_location' => [
        'title', 'field_tipo', 'field_endereco', 'field_precisao', 'field_horario',
        'field_descricao', 'field_telefone', 'field_site',
      ],
      'events_event' => [
        'title', 'field_subtitulo', 'body', 'field_url', 'field_organizacao',
        'field_coorganizadores', 'dates',
      ],
      default => [],
    };
    if (!in_array($field, $allowedFields, TRUE) || !isset($labels[$field])) {
      throw new UnprocessableEntityHttpException('A proposta contém um campo não suportado para revisão.');
    }
    if ($field === 'ocultar_localidade') {
      $form[$field] = [
        '#type' => 'checkbox',
        '#title' => $labels[$field],
        '#default_value' => (bool) $value,
      ];
      return;
    }
    if ($field === 'field_endereco') {
      $this->buildAddressField($form, $value, $labels[$field]);
      return;
    }
    if ($field === 'field_tipo') {
      $this->buildLocationTypeField($form, $value, $labels[$field]);
      return;
    }
    if ($field === 'field_precisao') {
      $form[$field] = [
        '#type' => 'select',
        '#title' => $labels[$field],
        '#options' => [
          'exato' => $this->t('Exato'),
          'aproximado' => $this->t('Aproximado'),
        ],
        '#default_value' => is_string($value) ? $value : '',
      ];
      return;
    }
    if ($field === 'field_organizacao') {
      $options = $this->publishedOrganizationOptions();
      $selected = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
      if (!isset($options[$selected])) {
        $options[$selected] = $this->t('Organização atualmente indisponível');
      }
      $form[$field] = [
        '#type' => 'select',
        '#title' => $labels[$field],
        '#options' => ['' => $this->t('- Nenhuma -')] + $options,
        '#default_value' => $selected,
      ];
      return;
    }
    if ($field === 'field_coorganizadores') {
      $options = $this->publishedOrganizationOptions();
      $selected = is_array($value) ? array_values(array_filter(
        $value,
        static fn(mixed $uuid): bool => is_string($uuid) && $uuid !== '',
      )) : [];
      foreach ($selected as $uuid) {
        if (!isset($options[$uuid])) {
          $options[$uuid] = $this->t('Organização atualmente indisponível');
        }
      }
      $form[$field] = [
        '#type' => 'select',
        '#title' => $labels[$field],
        '#options' => $options,
        '#default_value' => $selected,
        '#multiple' => TRUE,
      ];
      return;
    }
    if ($field === 'dates') {
      $this->buildDatesField($form, $value, $labels[$field]);
      return;
    }
    $longText = in_array($field, ['body', 'links', 'field_horario', 'field_descricao'], TRUE);
    $form[$field] = [
      '#type' => $longText ? 'textarea' : 'textfield',
      '#title' => $labels[$field],
      '#default_value' => is_scalar($value) ? (string) $value : '',
      '#maxlength' => $field === 'body' || $field === 'field_descricao' ? 20000 : 2048,
    ];
  }

  /**
   * Builds the centralized Brazilian address widget.
   */
  private function buildAddressField(array &$form, mixed $value, mixed $label): void {
    $address = is_array($value) ? ($value[0] ?? []) : [];
    if (!is_array($address)) {
      $address = [];
    }
    $parts = StreetNumberField::split((string) ($address['address_line1'] ?? ''));
    $number = $parts['number'];
    $sn = $parts['sn'];
    $address['country_code'] = 'BR';
    $address['address_line1'] = $parts['street'];
    $form['field_endereco'] = [
      '#type' => 'fieldset',
      '#title' => $label,
      '#tree' => TRUE,
      0 => [
        '#type' => 'address',
        '#default_value' => $address,
      ],
    ];
    AccountAddressForm::apply($form['field_endereco'][0], $number, $sn);
  }

  /**
   * Builds an allowlisted taxonomy selector for Local type.
   */
  private function buildLocationTypeField(array &$form, mixed $value, mixed $label): void {
    $options = [];
    $ids = $this->entities->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'aculta_location_type')
      ->condition('status', 1)
      ->sort('name')
      ->execute();
    foreach ($this->entities->getStorage('taxonomy_term')->loadMultiple($ids) as $term) {
      if ($term->access('view', $this->currentUser())) {
        $options[(int) $term->id()] = $term->label();
      }
    }
    $item = is_array($value) ? ($value[0] ?? []) : [];
    $selected = is_array($item) ? ($item['target_id'] ?? '') : $item;
    $form['field_tipo'] = [
      '#type' => 'select',
      '#title' => $label,
      '#options' => ['' => $this->t('- Selecione -')] + $options,
      '#default_value' => $selected,
    ];
  }

  /**
   * Builds one widget per existing proposed Event date.
   */
  private function buildDatesField(array &$form, mixed $value, mixed $label): void {
    if (!is_array($value) || $value === []) {
      throw new UnprocessableEntityHttpException('A proposta não contém Datas editáveis.');
    }
    $locationOptions = $this->publishedLocationOptions();
    $form['dates'] = [
      '#type' => 'fieldset',
      '#title' => $label,
      '#tree' => TRUE,
    ];
    foreach (array_values($value) as $delta => $date) {
      if (!is_array($date)) {
        throw new UnprocessableEntityHttpException('Uma Data da proposta está inválida.');
      }
      $form['dates'][$delta] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Data @number', ['@number' => $delta + 1]),
        '#tree' => TRUE,
        'title' => [
          '#type' => 'textfield',
          '#title' => $this->t('Nome da Data'),
          '#default_value' => (string) ($date['title'] ?? ''),
          '#maxlength' => 255,
        ],
        'starts_at' => [
          '#type' => 'textfield',
          '#title' => $this->t('Início (data e hora ISO 8601)'),
          '#default_value' => (string) ($date['starts_at'] ?? ''),
          '#description' => $this->t('Mantenha UTC ou informe o fuso explicitamente, por exemplo -03:00.'),
          '#required' => TRUE,
        ],
        'ends_at' => [
          '#type' => 'textfield',
          '#title' => $this->t('Fim (data e hora ISO 8601)'),
          '#default_value' => (string) ($date['ends_at'] ?? ''),
          '#required' => TRUE,
        ],
        'location_uuid' => [
          '#type' => 'select',
          '#title' => $this->t('Local'),
          '#options' => ['' => $this->t('- Sem Local -')] + $locationOptions,
          '#default_value' => (string) ($date['location_uuid'] ?? ''),
        ],
      ];
    }
  }

  /**
   * Returns public Organizations the current account can view.
   *
   * @return array<string, string>
   *   UUID-to-label options.
   */
  private function publishedOrganizationOptions(): array {
    $options = [];
    $ids = $this->entities->getStorage('aculta_event_organization')->getQuery()
      ->accessCheck(TRUE)
      ->condition('field_status', 'publicado')
      ->condition('status', 1)
      ->sort('title')
      ->execute();
    foreach ($this->entities->getStorage('aculta_event_organization')->loadMultiple($ids) as $organization) {
      if ($organization instanceof EventOrganization && $organization->access('view', $this->currentUser())) {
        $options[$organization->uuid()] = $organization->label();
      }
    }
    return $options;
  }

  /**
   * Returns public Locations the current account can view.
   *
   * @return array<string, string>
   *   UUID-to-label options.
   */
  private function publishedLocationOptions(): array {
    $options = [];
    $ids = $this->entities->getStorage('aculta_event_location')->getQuery()
      ->accessCheck(TRUE)
      ->condition('field_status', 'publicado')
      ->sort('title')
      ->execute();
    foreach ($this->entities->getStorage('aculta_event_location')->loadMultiple($ids) as $location) {
      if ($location instanceof EventLocation && $location->access('view', $this->currentUser())) {
        $options[$location->uuid()] = $location->label();
      }
    }
    return $options;
  }

  /**
   * Builds the normalized payload from the typed Form API controls.
   */
  private function extractPayload(array $form, FormStateInterface $form_state, Contribution $proposal): array {
    $submitted = (array) $form_state->getValue('payload');
    $payload = [];
    foreach ($proposal->getPayload() as $field => $oldValue) {
      if ($field === 'field_endereco') {
        $address = (array) $form_state->getValue(['payload', 'field_endereco', 0]);
        [$number, $sn] = StreetNumberField::values($form['payload']['field_endereco'][0], $form_state);
        $address['country_code'] = 'BR';
        $address['address_line1'] = StreetNumberField::join((string) ($address['address_line1'] ?? ''), $number, $sn);
        $payload[$field] = [$address];
      }
      elseif ($field === 'field_tipo') {
        $targetId = $submitted[$field] ?? '';
        $payload[$field] = $targetId === '' ? [] : (int) $targetId;
      }
      elseif ($field === 'field_organizacao') {
        $payload[$field] = (array_key_exists($field, $submitted) && $submitted[$field] !== '')
          ? [(string) $submitted[$field]]
          : [];
      }
      elseif ($field === 'field_coorganizadores') {
        $payload[$field] = array_values(array_filter(
          (array) ($submitted[$field] ?? []),
          'is_string',
        ));
      }
      elseif ($field === 'dates') {
        $payload[$field] = array_values((array) ($submitted[$field] ?? []));
      }
      else {
        $payload[$field] = $submitted[$field] ?? '';
      }
    }
    return $payload;
  }

  /**
   * Loads a proposal by UUID with owner and entity access checks.
   */
  private function loadProposal(string $uuid): Contribution {
    $ids = $this->entities->getStorage('aculta_contribution')->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $uuid)
      ->condition('uid', (int) $this->currentUser()->id())
      ->condition('source', ['events_organization', 'events_location', 'events_event'], 'IN')
      ->range(0, 1)
      ->execute();
    $proposal = $ids !== [] ? $this->entities->getStorage('aculta_contribution')->load(reset($ids)) : NULL;
    if (!$proposal instanceof Contribution || !$proposal->access('update', $this->currentUser())) {
      throw new NotFoundHttpException();
    }
    return $proposal;
  }

  /**
   * Reloads the proposal after Form API processing.
   */
  private function loadProposalById(int $id): Contribution {
    $proposal = $this->entities->getStorage('aculta_contribution')->load($id);
    if (!$proposal instanceof Contribution
      || (int) $proposal->getOwnerId() !== (int) $this->currentUser()->id()
      || !in_array($proposal->get('source')->value, ['events_organization', 'events_location', 'events_event'], TRUE)
      || !$proposal->access('update', $this->currentUser())) {
      throw new NotFoundHttpException();
    }
    return $proposal;
  }

}
