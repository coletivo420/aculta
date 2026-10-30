<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Plugin\Field\FieldWidget\EventLocationModalWidget;
use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Modal AJAX com duas etapas para buscar ou cadastrar locais reutilizáveis.
 */
final class EventLocationModalForm extends FormBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_location_modal_form';
  }

  /**
   * Controle de acesso à rota do modal.
   */
  public function access(AccountInterface $account): AccessResult {
    $canManage = $account->hasPermission('administer aculta events')
      || in_array('content_editor', $account->getRoles(), TRUE)
      || in_array('administrator', $account->getRoles(), TRUE);

    return AccessResult::allowedIf($canManage)->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->getRequest();
    $fieldName = (string) ($form_state->get('field_name') ?? $request->query->get('field_name') ?? 'field_event_location');
    $form_state->set('field_name', $fieldName);

    $targetId = $form_state->get('target_id') ?? $request->query->get('target_id');
    if ($targetId !== NULL) {
      $form_state->set('target_id', (int) $targetId);
    }

    $mode = (string) ($form_state->get('mode') ?? 'search');

    $form['#prefix'] = '<div id="aculta-location-modal-form-wrapper" class="aculta-location-modal">';
    $form['#suffix'] = '</div>';

    // Área para mensagens de status e validação dentro do modal.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -100,
    ];

    if ($mode === 'create') {
      $this->buildCreateStep($form, $form_state);
    }
    else {
      $this->buildSearchStep($form, $form_state);
    }

    return $form;
  }

  /**
   * Constrói o passo de busca e seleção de locais existentes.
   */
  private function buildSearchStep(array &$form, FormStateInterface $form_state): void {
    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => (string) $this->t('Pesquise e selecione um local existente ou inicie um novo cadastro sem fechar a página.'),
    ];

    $form['search_bar'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-location-search-bar'],
    ];

    $form['search_bar']['search_term'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do local'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Filtrar por nome do local...'),
      '#size' => 40,
      '#default_value' => $form_state->get('search_term') ?? '',
    ];

    $form['search_bar']['search_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Buscar'),
      '#name' => 'op_search',
      '#submit' => ['::submitSearch'],
      '#ajax' => [
        'callback' => '::ajaxRebuildModal',
        'wrapper' => 'aculta-location-modal-form-wrapper',
      ],
    ];

    $searchTerm = trim((string) ($form_state->get('search_term') ?? ''));

    $query = $this->entityTypeManager->getStorage('aculta_event_location')
      ->getQuery()
      ->accessCheck(TRUE)
      ->sort('id', 'DESC')
      ->range(0, 15);

    if ($searchTerm !== '') {
      $query->condition('title', '%' . $searchTerm . '%', 'LIKE');
    }

    $ids = $query->execute();
    $locations = $ids !== [] ? $this->entityTypeManager->getStorage('aculta_event_location')->loadMultiple($ids) : [];

    $rows = [];
    $currentUser = $this->currentUser();
    foreach ($locations as $loc) {
      if (!$loc instanceof EventLocation || !$loc->access('view', $currentUser)) {
        continue;
      }

      $addressText = '';
      if ($loc->hasField('field_endereco') && !$loc->get('field_endereco')->isEmpty()) {
        $addr = $loc->get('field_endereco')->first();
        if ($addr) {
          $parts = array_filter([$addr->locality, $addr->administrative_area]);
          $addressText = implode(' - ', $parts);
        }
      }

      $locId = (int) $loc->id();
      $isPublic = ($loc->get('field_status')->value ?? '') === 'publicado';

      $rows[$locId] = [
        'title' => $loc->label(),
        'city' => $addressText !== '' ? $addressText : $this->t('Brasil'),
        'status' => $isPublic ? $this->t('Publicado') : $this->t('Rascunho'),
        'actions' => [
          'data' => [
            '#type' => 'submit',
            '#value' => $this->t('Selecionar'),
            '#name' => 'select_location_' . $locId,
            '#location_id' => $locId,
            '#submit' => ['::submitSelectLocation'],
            '#ajax' => [
              'callback' => '::ajaxSelectLocation',
            ],
            '#attributes' => ['data-portal-ui' => 'event-location-button-primary-small'],
          ],
        ],
      ];
    }

    $form['locations_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Local'),
        $this->t('Município / UF'),
        $this->t('Status'),
        $this->t('Ação'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum local acessível encontrado com este termo.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'switch_create' => [
        '#type' => 'submit',
        '#value' => $this->t('Cadastrar novo local'),
        '#name' => 'op_switch_create',
        '#submit' => ['::switchToCreate'],
        '#ajax' => [
          'callback' => '::ajaxRebuildModal',
          'wrapper' => 'aculta-location-modal-form-wrapper',
        ],
        '#limit_validation_errors' => [],
        '#attributes' => ['data-portal-ui' => 'event-location-button-action'],
      ],
      'cancel' => [
        '#type' => 'button',
        '#value' => $this->t('Cancelar'),
        '#ajax' => [
          'callback' => '::ajaxCloseModal',
        ],
      ],
    ];
  }

  /**
   * Constrói o passo de cadastro do endereço brasileiro.
   */
  private function buildCreateStep(array &$form, FormStateInterface $form_state): void {
    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => (string) $this->t('Preencha os dados do novo local. O país é fixado em Brasil e a busca é iniciada pelo CEP.'),
    ];

    $form['novo_nome'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do local'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $form_state->getValue('novo_nome') ?? '',
    ];

    // Fieldset de endereço brasileiro reutilizando o adaptador central.
    $form['field_endereco'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Endereço do local (Brasil)'),
      '#tree' => TRUE,
    ];

    $form['field_endereco'][0] = [
      '#type' => 'address',
      '#default_value' => [
        'country_code' => 'BR',
      ],
    ];

    AccountAddressForm::apply($form['field_endereco'][0]);

    $form['field_precisao'] = [
      '#type' => 'select',
      '#title' => $this->t('Precisão pública do endereço'),
      '#options' => [
        'exato' => $this->t('Exato (número e rua visíveis)'),
        'aproximado' => $this->t('Aproximado (apenas bairro e município públicos)'),
      ],
      '#default_value' => 'exato',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'save_and_select' => [
        '#type' => 'submit',
        '#value' => $this->t('Salvar e selecionar este local'),
        '#name' => 'op_create_save',
        '#submit' => ['::submitCreateLocation'],
        '#ajax' => [
          'callback' => '::ajaxCreateLocation',
        ],
        '#attributes' => ['data-portal-ui' => 'event-location-button-primary'],
      ],
      'back_to_search' => [
        '#type' => 'submit',
        '#value' => $this->t('Voltar para busca'),
        '#name' => 'op_back_search',
        '#submit' => ['::switchToSearch'],
        '#ajax' => [
          'callback' => '::ajaxRebuildModal',
          'wrapper' => 'aculta-location-modal-form-wrapper',
        ],
        '#limit_validation_errors' => [],
        '#attributes' => ['data-portal-ui' => 'event-location-button-secondary'],
      ],
      'cancel' => [
        '#type' => 'button',
        '#value' => $this->t('Cancelar'),
        '#ajax' => [
          'callback' => '::ajaxCloseModal',
        ],
      ],
    ];
  }

  /**
   * Submissão de busca por termo.
   */
  public function submitSearch(array &$form, FormStateInterface $form_state): void {
    $term = trim((string) $form_state->getValue('search_term'));
    $form_state->set('search_term', $term);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Transiciona para a etapa de criação.
   */
  public function switchToCreate(array &$form, FormStateInterface $form_state): void {
    $form_state->set('mode', 'create');
    $form_state->setRebuild(TRUE);
  }

  /**
   * Transiciona de volta para a busca.
   */
  public function switchToSearch(array &$form, FormStateInterface $form_state): void {
    $form_state->set('mode', 'search');
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $triggering = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if ($triggering === 'op_create_save') {
      $nome = trim((string) $form_state->getValue('novo_nome'));
      if ($nome === '') {
        $form_state->setErrorByName('novo_nome', (string) $this->t('Informe o nome do local.'));
      }

      $addressValue = $form_state->getValue(['field_endereco', 0]);
      if (is_array($addressValue)) {
        if (isset($addressValue['country_code']) && $addressValue['country_code'] !== '' && $addressValue['country_code'] !== 'BR') {
          $form_state->setErrorByName('field_endereco][0][country_code', (string) $this->t('Apenas endereços no Brasil (BR) são suportados.'));
        }
      }
    }
  }

  /**
   * Submissão de seleção de um local existente.
   */
  public function submitSelectLocation(array &$form, FormStateInterface $form_state): void {
    $triggering = $form_state->getTriggeringElement();
    $locationId = (int) ($triggering['#location_id'] ?? 0);
    $form_state->set('selected_location_id', $locationId);
  }

  /**
   * Submissão de criação de um novo local.
   */
  public function submitCreateLocation(array &$form, FormStateInterface $form_state): void {
    $nome = trim((string) $form_state->getValue('novo_nome'));
    $addressValue = $form_state->getValue(['field_endereco', 0]) ?? [];

    $countryCode = 'BR';
    $logradouro = (string) ($addressValue['address_line1'] ?? '');
    $bairro = (string) ($addressValue['dependent_locality'] ?? '');
    $cidade = (string) ($addressValue['locality'] ?? '');
    $uf = strtoupper((string) ($addressValue['administrative_area'] ?? ''));
    $cep = (string) ($addressValue['postal_code'] ?? '');
    $precisao = (string) ($form_state->getValue('field_precisao') ?? 'exato');

    $location = EventLocation::create([
      'title' => $nome,
      'field_status' => 'publicado',
      'field_precisao' => $precisao,
      'field_organizador' => $this->currentUser()->id(),
      'field_endereco' => [
        'country_code' => $countryCode,
        'address_line1' => $logradouro,
        'dependent_locality' => $bairro,
        'locality' => $cidade,
        'administrative_area' => $uf,
        'postal_code' => $cep,
      ],
    ]);
    $location->save();

    $form_state->set('selected_location_id', (int) $location->id());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Processado pelos handlers específicos.
  }

  /**
   * AJAX callback para reconstruir o modal após troca de tela ou busca.
   */
  public function ajaxRebuildModal(array &$form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * AJAX callback para fechar o modal.
   */
  public function ajaxCloseModal(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * AJAX callback para selecionar local e atualizar o formulário pai.
   */
  public function ajaxSelectLocation(array &$form, FormStateInterface $form_state): AjaxResponse {
    $locationId = (int) $form_state->get('selected_location_id');
    return $this->buildUpdateParentResponse($locationId, (string) $form_state->get('field_name'));
  }

  /**
   * AJAX callback para salvar novo local e atualizar o pai.
   */
  public function ajaxCreateLocation(array &$form, FormStateInterface $form_state): AjaxResponse|array {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }
    $locationId = (int) $form_state->get('selected_location_id');
    return $this->buildUpdateParentResponse($locationId, (string) $form_state->get('field_name'));
  }

  /**
   * Constrói resposta AJAX que fecha o modal e atualiza o formulário pai.
   */
  private function buildUpdateParentResponse(int $locationId, string $fieldName): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());

    $cardArray = EventLocationModalWidget::buildCardOrEmpty($locationId, $fieldName, 'field-event-location-wrapper');
    $cardArray['#prefix'] = '<div id="field-event-location-wrapper" class="aculta-event-location-widget">';
    $cardArray['#suffix'] = '</div>';

    $response->addCommand(new ReplaceCommand('#field-event-location-wrapper', $cardArray));
    $response->addCommand(new InvokeCommand('#field-event-location-target-id', 'val', [(string) $locationId]));
    $response->addCommand(new InvokeCommand('#field-event-location-target-id', 'trigger', ['change']));

    return $response;
  }

}
