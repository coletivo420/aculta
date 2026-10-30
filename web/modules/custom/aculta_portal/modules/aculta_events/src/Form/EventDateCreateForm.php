<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
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

    $form['local_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Local do evento'),
      '#open' => TRUE,
    ];

    $form['local_section']['local_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Selecionar local existente'),
      '#target_type' => 'aculta_event_location',
      '#description' => $this->t('Pesquise pelo nome do local para reutilizar um cadastro existente.'),
    ];

    $form['local_section']['create_new_location'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cadastrar um novo local para esta data'),
      '#description' => $this->t('Marque se o local não existir na lista acima.'),
    ];

    $form['local_section']['new_location_fields'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="create_new_location"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['local_section']['new_location_fields']['new_location_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do novo local'),
      '#maxlength' => 255,
    ];

    $form['local_section']['new_location_fields']['new_location_tipo'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Tipo de local'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['aculta_location_type' => 'aculta_location_type'],
      ],
    ];

    $form['local_section']['new_location_fields']['new_location_precision'] = [
      '#type' => 'select',
      '#title' => $this->t('Precisão do endereço'),
      '#options' => [
        'exato' => $this->t('Exato (número e rua visíveis)'),
        'aproximado' => $this->t('Aproximado (apenas bairro e município públicos)'),
      ],
      '#default_value' => 'exato',
    ];

    $form['local_section']['new_location_fields']['new_location_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Logradouro e número'),
      '#maxlength' => 255,
    ];

    $form['local_section']['new_location_fields']['new_location_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Município'),
      '#maxlength' => 255,
    ];

    $form['local_section']['new_location_fields']['new_location_state'] = [
      '#type' => 'textfield',
      '#title' => $this->t('UF (Estado)'),
      '#maxlength' => 2,
    ];

    $form['local_section']['new_location_fields']['new_location_postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CEP'),
      '#maxlength' => 9,
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

    $createNew = (bool) $form_state->getValue('create_new_location');
    if ($createNew) {
      $newName = trim((string) $form_state->getValue('new_location_name'));
      if ($newName === '') {
        $form_state->setErrorByName('new_location_name', $this->t('Informe o nome do novo local.'));
      }
    }
    else {
      $selectedLocal = $form_state->getValue('local_id');
      if (!empty($selectedLocal)) {
        if (!is_numeric($selectedLocal) || (int) $selectedLocal < 1) {
          $form_state->setErrorByName('local_id', $this->t('O local selecionado é inválido.'));
        }
        else {
          $loc = $this->entities->getStorage('aculta_event_location')->load((int) $selectedLocal);
          if (!$loc instanceof EventLocation) {
            $form_state->setErrorByName('local_id', $this->t('O local selecionado não existe.'));
          }
        }
      }
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

    $locationId = NULL;
    $createNew = (bool) $form_state->getValue('create_new_location');
    if ($createNew) {
      $newName = trim((string) $form_state->getValue('new_location_name'));
      $locationStorage = $this->entities->getStorage('aculta_event_location');
      $locationData = [
        'title' => $newName,
        'field_precisao' => (string) ($form_state->getValue('new_location_precision') ?: 'exato'),
        'field_status' => 'publicado',
        'field_organizador' => $this->currentUserAccount->id(),
      ];
      $tipo = $form_state->getValue('new_location_tipo');
      if (!empty($tipo)) {
        $locationData['field_tipo'] = $tipo;
      }
      $addressLine = trim((string) $form_state->getValue('new_location_address'));
      $city = trim((string) $form_state->getValue('new_location_city'));
      $state = trim((string) $form_state->getValue('new_location_state'));
      $cep = trim((string) $form_state->getValue('new_location_postal_code'));
      if ($addressLine !== '' || $city !== '' || $state !== '' || $cep !== '') {
        $locationData['field_endereco'] = [
          'country_code' => 'BR',
          'address_line1' => $addressLine,
          'locality' => $city,
          'administrative_area' => $state,
          'postal_code' => $cep,
        ];
      }
      $newLocation = $locationStorage->create($locationData);
      $newLocation->save();
      $locationId = (int) $newLocation->id();
      $this->messenger()->addStatus($this->t('O novo local %name foi cadastrado.', ['%name' => $newName]));
    }
    else {
      $selectedLocal = $form_state->getValue('local_id');
      if (!empty($selectedLocal) && is_numeric($selectedLocal)) {
        $locationId = (int) $selectedLocal;
      }
    }

    $dateData = [
      'title' => (string) $form_state->getValue('title'),
      'field_evento' => $event->id(),
      'field_quando' => ['value' => $start, 'end_value' => $end],
    ];
    if ($locationId !== NULL && $locationId > 0) {
      $dateData['field_local'] = $locationId;
    }

    $date = $this->entities->getStorage('aculta_event_date')->create($dateData);
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
