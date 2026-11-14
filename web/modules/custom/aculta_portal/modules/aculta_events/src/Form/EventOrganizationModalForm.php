<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Access\EventPermissionPolicy;
use Drupal\aculta_events\Adapter\OrganizationContributionAdapter;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Plugin\Field\FieldWidget\EventOrganizationModalWidget;
use Drupal\aculta_events\Route\EventRouteContext;
use Drupal\aculta_events\Slug\EventSlugGenerator;
use Drupal\aculta_cgu\Service\ContributionLifecycle;
use Drupal\aculta_user\Organization\OrganizationAccessService;
use Drupal\aculta_user\Organization\OrganizationMembershipManager;
use Drupal\aculta_user\Organization\OwnershipClaimService;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modal AJAX para busca, cadastro e seleção de organizações culturais.
 *
 * Integra seleção da organização principal e coorganizadoras nos fluxos
 * administrativo e do Studio Culturas, garantindo devolução de foco.
 */
final class EventOrganizationModalForm extends FormBase {

  /**
   * Wrapper do formulário trocado pelo envio HTMX.
   */
  private const WRAPPER_ID = 'aculta-organization-modal-form-wrapper';

  /**
   * URL do próprio modal (com o contexto do widget) para o `hx-post`.
   */
  protected Url $htmxUrl;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_events.permission_policy')]
    protected readonly EventPermissionPolicy $permissionPolicy,
    #[Autowire(service: 'aculta_user.organization_access_service')]
    protected readonly OrganizationAccessService $orgAccessService,
    #[Autowire(service: 'aculta_user.organization_membership_manager')]
    protected readonly OrganizationMembershipManager $membershipManager,
    #[Autowire(service: 'renderer')]
    protected readonly RendererInterface $renderer,
    #[Autowire(service: 'aculta_events.route_context')]
    protected readonly EventRouteContext $eventRouteContext,
    #[Autowire(service: 'aculta_user.ownership_claim_service')]
    protected readonly ?OwnershipClaimService $claimService = NULL,
    #[Autowire(service: 'aculta_cgu.contribution_lifecycle')]
    protected readonly ?ContributionLifecycle $contributions = NULL,
    #[Autowire(service: 'aculta_events.organization_contribution_adapter')]
    protected readonly ?OrganizationContributionAdapter $organizationAdapter = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_events.permission_policy'),
      $container->get('aculta_user.organization_access_service'),
      $container->get('aculta_user.organization_membership_manager'),
      $container->get('renderer'),
      $container->get('aculta_events.route_context'),
      $container->has('aculta_user.ownership_claim_service') ? $container->get('aculta_user.ownership_claim_service') : NULL,
      $container->has('aculta_cgu.contribution_lifecycle') ? $container->get('aculta_cgu.contribution_lifecycle') : NULL,
      $container->has('aculta_events.organization_contribution_adapter') ? $container->get('aculta_events.organization_contribution_adapter') : NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_organization_modal_form';
  }

  /**
   * Controle de acesso à rota do modal de organização.
   */
  public function access(AccountInterface $account): AccessResult {
    $canManage = $account->hasPermission('administer aculta events')
      || $this->permissionPolicy->canCreateEvent($account);

    return AccessResult::allowedIf($canManage)
      ->cachePerPermissions()
      ->addCacheContexts(['user.roles'])
      ->addCacheTags(['config:aculta_events.settings']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->getRequest();
    $fieldName = (string) ($form_state->get('field_name') ?? $request->query->get('field_name') ?? 'field_organizacao');
    $isMultiple = (bool) ($form_state->get('is_multiple') ?? (int) $request->query->get('is_multiple', 0));
    $defaultWrapper = 'field-' . str_replace('_', '-', $fieldName) . '-wrapper';
    $wrapperId = (string) ($form_state->get('wrapper_id') ?? $request->query->get('wrapper_id') ?? $defaultWrapper);

    $form_state->set('field_name', $fieldName);
    $form_state->set('is_multiple', $isMultiple);
    $form_state->set('wrapper_id', $wrapperId);

    $query = [
      'field_name' => $fieldName,
      'wrapper_id' => $wrapperId,
      'is_multiple' => $isMultiple ? 1 : 0,
    ];
    $targetId = $form_state->get('target_id') ?? $request->query->get('target_id');
    if ($targetId !== NULL && (int) $targetId > 0) {
      $query['target_id'] = (int) $targetId;
    }
    $this->htmxUrl = Url::fromRoute(
      $this->eventRouteContext->route('aculta_events.organization_modal', 'aculta_events.organizer_organization_modal'),
      [],
      ['query' => $query],
    );
    if ($targetId !== NULL) {
      $form_state->set('target_id', (int) $targetId);
    }

    $mode = (string) ($form_state->get('mode') ?? 'search');

    $form['#prefix'] = '<div id="aculta-organization-modal-form-wrapper">';
    $form['#suffix'] = '</div>';

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
   * Constrói o passo de busca e seleção de organizações existentes.
   */
  private function buildSearchStep(array &$form, FormStateInterface $form_state): void {
    $isMultiple = (bool) $form_state->get('is_multiple');
    $heading = $isMultiple
      ? $this->t('Selecionar coorganizadora')
      : $this->t('Selecionar organização principal');

    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => (string) $heading,
      '#attributes' => ['data-portal-ui' => 'event-organization-intro'],
    ];

    $form['search_box'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-organization-search'],
      'termo' => [
        '#type' => 'textfield',
        '#title' => $this->t('Buscar organização'),
        '#title_display' => 'invisible',
        '#placeholder' => (string) $this->t('Buscar por nome ou cidade...'),
        '#default_value' => (string) ($form_state->get('termo') ?? ''),
        '#size' => 30,
      ],
      'submit_search' => [
        '#type' => 'submit',
        '#value' => $this->t('Buscar'),
        '#submit' => ['::submitSearch'],
        '#attributes' => ['data-portal-ui' => 'event-organization-search-action'],
      ],
      'switch_create' => [
        '#type' => 'submit',
        '#value' => $this->t('+ Cadastrar nova organização'),
        '#submit' => ['::switchToCreate'],
        '#limit_validation_errors' => [],
        '#attributes' => ['data-portal-ui' => 'event-organization-add-action'],
      ],
    ];
    $this->applyHtmx($form['search_box']['submit_search']);
    $this->applyHtmx($form['search_box']['switch_create']);

    // Consulta de organizações existentes.
    $termo = trim((string) ($form_state->get('termo') ?? ''));
    $storage = $this->entityTypeManager->getStorage('aculta_event_organization');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('field_status', 'publicado')
      ->sort('title', 'ASC')
      ->range(0, 15);

    if ($termo !== '') {
      $group = $query->orConditionGroup()
        ->condition('title', $termo, 'CONTAINS')
        ->condition('localidade', $termo, 'CONTAINS');
      $query->condition($group);
    }

    $ids = $query->execute();

    if (!empty($ids)) {
      $form['results'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-organization-results-table'],
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Organização'),
            $this->t('Localidade'),
            $this->t('Situação'),
            $this->t('Ação'),
          ],
          '#empty' => $this->t('Nenhuma organização encontrada para este termo.'),
        ],
      ];

      /** @var \Drupal\aculta_events\Entity\EventOrganization[] $orgs */
      $orgs = $storage->loadMultiple($ids);
      foreach ($orgs as $org) {
        $oid = (int) $org->id();

        $localidadeStr = (string) $this->t('Não informada');
        if ($org->shouldHideLocation()) {
          $localidadeStr = (string) $this->t('Ocultada');
        }
        elseif (!empty($org->get('localidade')->value) || !empty($org->get('uf')->value)) {
          $parts = array_filter([(string) $org->get('localidade')->value, (string) $org->get('uf')->value]);
          $localidadeStr = implode(' - ', $parts);
        }

        $badgeText = $org->isClaimed() ? $this->t('Reivindicada') : $this->t('Não reivindicada');
        $badgeClass = $org->isClaimed() ? 'badge--success' : 'badge--warning';

        $form['results']['table'][$oid] = [
          'title' => [
            '#markup' => '<strong>' . htmlspecialchars((string) $org->label(), ENT_QUOTES, 'UTF-8') . '</strong>',
          ],
          'localidade' => [
            '#markup' => htmlspecialchars($localidadeStr, ENT_QUOTES, 'UTF-8'),
          ],
          'badge' => [
            '#markup' => '<span class="badge ' . $badgeClass . '">' . htmlspecialchars((string) $badgeText, ENT_QUOTES, 'UTF-8') . '</span>',
          ],
          'select' => $this->selectButton($oid),
        ];
      }
    }
    else {
      $form['empty_notice'] = [
        '#component' => 'aculta420_v2:status-messages',
        '#props' => [
          'type' => 'status',
          'messages' => [$this->t('Nenhuma organização encontrada. Você pode cadastrar uma nova organização imediatamente no botão acima.')],
        ],
      ];
    }

    $form['cancel_action'] = [
      '#type' => 'actions',
      'close' => [
        '#type' => 'button',
        '#value' => $this->t('Fechar'),
        '#attributes' => ['data-aculta-dialog-close' => ''],
      ],
    ];
  }

  /**
   * Botão "Selecionar" de uma organização, com o envelope HTMX.
   */
  private function selectButton(int $orgId, string $namePrefix = 'select_org_', ?string $label = NULL): array {
    $button = [
      '#type' => 'submit',
      '#value' => $label ?? $this->t('Selecionar'),
      '#name' => $namePrefix . $orgId,
      '#org_id' => $orgId,
      '#submit' => ['::submitSelectOrganization'],
      '#attributes' => ['data-portal-ui' => 'event-organization-small-button'],
    ];
    $this->applyHtmx($button);
    return $button;
  }

  /**
   * Constrói o passo de criação de nova organização.
   */
  private function buildCreateStep(array &$form, FormStateInterface $form_state): void {
    $form['create_intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => (string) $this->t('Preencha os dados da organização cultural. Endereço completo e CEP não são solicitados nem expostos publicamente.'),
      '#attributes' => ['data-portal-ui' => 'event-organization-intro'],
    ];

    // Se houver candidatos semelhantes detectados na validação.
    $candidates = $form_state->get('duplicate_candidates');
    if (!empty($candidates)) {
      $storage = $this->entityTypeManager->getStorage('aculta_event_organization');
      $dups = $storage->loadMultiple($candidates);

      $form['duplicate_warning'] = [
        '#component' => 'aculta420_v2:status-messages',
        '#props' => ['type' => 'warning'],
        '#slots' => ['content' => [
          'heading' => ['#markup' => '<h4>' . $this->t('Atenção: Organizações com nomes semelhantes já cadastradas') . '</h4><p>' . $this->t('Para evitar duplicidade, verifique se a organização desejada já existe antes de criar uma nova:') . '</p>'],
        ]],
      ];

      $form['duplicate_table'] = [
        '#type' => 'table',
        '#header' => [$this->t('Nome existente'), $this->t('Situação'), $this->t('Ação')],
      ];

      foreach ($dups as $dup) {
        if (!$dup instanceof EventOrganization) {
          continue;
        }
        $did = (int) $dup->id();
        $bText = $dup->isClaimed() ? $this->t('Reivindicada') : $this->t('Não reivindicada');
        $form['duplicate_table'][$did] = [
          'name' => ['#markup' => '<strong>' . htmlspecialchars((string) $dup->label(), ENT_QUOTES, 'UTF-8') . '</strong>'],
          'status' => ['#markup' => htmlspecialchars((string) $bText, ENT_QUOTES, 'UTF-8')],
          'select' => $this->selectButton($did, 'select_dup_', (string) $this->t('Selecionar esta')),
        ];
      }
    }

    $form['novo_nome'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome da organização'),
      '#required' => TRUE,
      '#size' => 40,
      '#default_value' => (string) ($form_state->getValue('novo_nome') ?? ''),
    ];

    $form['novo_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Descrição ou apresentação institucional'),
      '#rows' => 3,
      '#default_value' => (string) ($form_state->getValue('novo_body') ?? ''),
    ];

    $form['novo_links'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Links oficiais (sites, redes sociais)'),
      '#description' => $this->t('Um link por linha.'),
      '#rows' => 2,
      '#default_value' => (string) ($form_state->getValue('novo_links') ?? ''),
    ];

    $form['loc_container'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-organization-location-fields'],
      'novo_localidade' => [
        '#type' => 'textfield',
        '#title' => $this->t('Cidade'),
        '#size' => 25,
        '#default_value' => (string) ($form_state->getValue('novo_localidade') ?? ''),
      ],
      'novo_uf' => [
        '#type' => 'textfield',
        '#title' => $this->t('UF'),
        '#size' => 4,
        '#maxlength' => 2,
        '#default_value' => (string) ($form_state->getValue('novo_uf') ?? ''),
      ],
    ];

    $form['novo_ocultar_localidade'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ocultar cidade e UF do perfil público e das buscas'),
      '#default_value' => (bool) ($form_state->getValue('novo_ocultar_localidade') ?? FALSE),
    ];

    $isAdmin = $this->currentUser()->hasPermission('administer aculta events');
    if ($isAdmin) {
      $claimOptions = [
        'unclaimed' => $this->t('Cadastrar como organização não reivindicada (coletivo aberto ou parceiro sem titular cadastrado)'),
        'claimed' => $this->t('Cadastrar com titularidade imediata (administrador)'),
        'request_claim' => $this->t('Cadastrar e registrar solicitação de titularidade pendente para moderação'),
      ];
    }
    else {
      $claimOptions = [
        'unclaimed' => $this->t('Propor criação como organização não reivindicada'),
      ];
    }

    $form['is_claimed_choice'] = [
      '#type' => 'radios',
      '#title' => $this->t('Tipo de cadastro da organização'),
      '#options' => $claimOptions,
      '#default_value' => (string) ($form_state->getValue('is_claimed_choice') ?? 'unclaimed'),
    ];

    if (!$isAdmin) {
      $form['create_review_notice'] = [
        '#type' => 'item',
        '#markup' => '<p>' . $this->t('A nova organização só será criada e ficará pública após revisão. Depois da aprovação, você poderá solicitar titularidade pelo fluxo de reivindicação.') . '</p>',
      ];
      $form['contribution_rationale'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Por que esta organização deve ser cadastrada?'),
        '#description' => $this->t('Esta justificativa ficará privada para a equipe revisora.'),
        '#required' => TRUE,
        '#maxlength' => 5000,
        '#default_value' => (string) ($form_state->getValue('contribution_rationale') ?? ''),
      ];
      if (!$form_state->has('organization_contribution_token')) {
        $form_state->set('organization_contribution_token', bin2hex(random_bytes(32)));
      }
      $form['contribution_submission_token'] = [
        '#type' => 'value',
        '#value' => $form_state->get('organization_contribution_token'),
      ];
    }

    $form['justificativa_reivindicacao'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Justificativa da solicitação de titularidade'),
      '#description' => $this->t('Explique seu vínculo ou representação oficial da organização para análise da moderação.'),
      '#rows' => 3,
      '#default_value' => (string) ($form_state->getValue('justificativa_reivindicacao') ?? ''),
      '#states' => [
        'visible' => [
          ':input[name="is_claimed_choice"]' => ['value' => 'request_claim'],
        ],
        'required' => [
          ':input[name="is_claimed_choice"]' => ['value' => 'request_claim'],
        ],
      ],
    ];

    $form['contato_reivindicacao'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contato institucional / Comprovação (opcional)'),
      '#description' => $this->t('Link oficial, e-mail institucional ou telefone para confirmação.'),
      '#default_value' => (string) ($form_state->getValue('contato_reivindicacao') ?? ''),
      '#states' => [
        'visible' => [
          ':input[name="is_claimed_choice"]' => ['value' => 'request_claim'],
        ],
      ],
    ];

    $form['create_actions'] = [
      '#type' => 'actions',
      'submit_create' => [
        '#type' => 'submit',
        '#value' => !empty($candidates) ? $this->t('Confirmar cadastro mesmo assim') : $this->t('Cadastrar e selecionar'),
        '#name' => 'op_create_save',
        '#submit' => ['::submitCreateOrganization'],
        '#attributes' => ['data-portal-ui' => 'event-organization-add-action'],
      ],
      'back_search' => [
        '#type' => 'submit',
        '#value' => $this->t('Voltar para a busca'),
        '#submit' => ['::switchToSearch'],
        '#limit_validation_errors' => [],
        '#attributes' => ['data-portal-ui' => 'event-organization-back-action'],
      ],
    ];
    $this->applyHtmx($form['create_actions']['submit_create']);
    $this->applyHtmx($form['create_actions']['back_search']);
  }

  /**
   * Submissão do filtro de busca.
   */
  public function submitSearch(array &$form, FormStateInterface $form_state): void {
    $form_state->set('termo', trim((string) $form_state->getValue('termo')));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Alterna para a etapa de criação.
   */
  public function switchToCreate(array &$form, FormStateInterface $form_state): void {
    $form_state->set('mode', 'create');
    $form_state->set('duplicate_candidates', []);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Alterna de volta para a busca.
   */
  public function switchToSearch(array &$form, FormStateInterface $form_state): void {
    $form_state->set('mode', 'search');
    $form_state->set('duplicate_candidates', []);
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
        $form_state->setErrorByName('novo_nome', (string) $this->t('Informe o nome da organização.'));
        return;
      }

      $choice = (string) $form_state->getValue('is_claimed_choice', 'unclaimed');
      $isAdmin = $this->currentUser()->hasPermission('administer aculta events');
      if ($choice === 'claimed' && !$isAdmin) {
        $form_state->setErrorByName('is_claimed_choice', (string) $this->t('Apenas administradores podem conceder titularidade imediata no cadastro. Solicite a titularidade para análise da moderação.'));
      }
      if ($choice === 'request_claim') {
        if (!$isAdmin) {
          $form_state->setErrorByName('is_claimed_choice', (string) $this->t('A titularidade deve ser solicitada pelo fluxo próprio após a aprovação da criação.'));
        }
        $justificativa = trim((string) $form_state->getValue('justificativa_reivindicacao'));
        if ($justificativa === '') {
          $form_state->setErrorByName('justificativa_reivindicacao', (string) $this->t('Informe a justificativa da solicitação de titularidade para análise da moderação.'));
        }
      }
      if (!$isAdmin && $choice !== 'unclaimed') {
        $form_state->setErrorByName('is_claimed_choice', (string) $this->t('A proposta deve ser de uma organização não reivindicada.'));
      }
      if (!$isAdmin && trim((string) $form_state->getValue('contribution_rationale')) === '') {
        $form_state->setErrorByName('contribution_rationale', (string) $this->t('Informe por que esta organização deve ser cadastrada.'));
      }

      $duplicateConfirmed = (bool) $form_state->get('duplicate_confirmed');
      if (!$duplicateConfirmed) {
        $storage = $this->entityTypeManager->getStorage('aculta_event_organization');
        $dups = $storage->getQuery()
          ->accessCheck(TRUE)
          ->condition('title', $nome, 'CONTAINS')
          ->range(0, 5)
          ->execute();

        if (!empty($dups)) {
          $form_state->set('duplicate_candidates', array_values($dups));
          $form_state->set('duplicate_confirmed', TRUE);
          $form_state->setRebuild(TRUE);
          $form_state->setErrorByName('', (string) $this->t('Foram encontradas organizações com nomes semelhantes. Verifique a lista abaixo ou confirme o cadastro.'));
        }
      }
    }
  }

  /**
   * Submissão de seleção de organização existente com validação estrita.
   */
  public function submitSelectOrganization(array &$form, FormStateInterface $form_state): void {
    $triggering = $form_state->getTriggeringElement();
    $orgId = (int) ($triggering['#org_id'] ?? 0);
    if ($orgId > 0) {
      $org = $this->entityTypeManager->getStorage('aculta_event_organization')->load($orgId);
      if (!$org instanceof EventOrganization) {
        $form_state->setErrorByName('', (string) $this->t('Organização não encontrada.'));
        return;
      }

      $currentUser = $this->currentUser();

      // Regra de segurança: se a organização for reivindicada,
      // confirma autorização ou vínculo ativo no servidor.
      if ($org->isClaimed()) {
        $isAdmin = $currentUser->hasPermission('administer aculta events');
        $canAct = $this->orgAccessService->canActOnBehalf($currentUser, (string) $orgId);

        if (!$isAdmin && !$canAct) {
          $form_state->setErrorByName('', (string) $this->t('Esta organização é reivindicada e sua conta não possui autorização ou vínculo ativo para vinculá-la ao evento.'));
          return;
        }
      }

      $form_state->set('selected_org_id', $orgId);
      if ($this->isHtmxRequest()) {
        $form_state->setResponse($this->htmxUpdateParentResponse(
          $orgId,
          (string) $form_state->get('field_name'),
          (string) $form_state->get('wrapper_id'),
          (bool) $form_state->get('is_multiple'),
        ));
      }
    }
  }

  /**
   * Submissão de criação e seleção de nova organização.
   */
  public function submitCreateOrganization(array &$form, FormStateInterface $form_state): void {
    $nome = trim((string) $form_state->getValue('novo_nome'));
    $body = trim((string) $form_state->getValue('novo_body'));
    $links = trim((string) $form_state->getValue('novo_links'));
    $cidade = trim((string) $form_state->getValue('novo_localidade'));
    $uf = strtoupper(trim((string) $form_state->getValue('novo_uf')));
    $ocultarLoc = (bool) $form_state->getValue('novo_ocultar_localidade');
    $choice = (string) $form_state->getValue('is_claimed_choice', 'unclaimed');
    $currentUid = (int) $this->currentUser()->id();

    $isAdmin = $this->currentUser()->hasPermission('administer aculta events');
    if (!$isAdmin) {
      if ($this->contributions === NULL || $this->organizationAdapter === NULL) {
        throw new \LogicException('O serviço de propostas de organizações não está disponível.');
      }
      $payload = $this->organizationAdapter->validatePayload('organization_create', [
        'title' => $nome,
        'body' => $body,
        'links' => $links,
        'localidade' => $cidade,
        'uf' => $uf,
        'ocultar_localidade' => $ocultarLoc,
      ]);
      $this->contributions->submit(
        'events_organization',
        'organization_create',
        NULL,
        (string) $this->t('Criação da organização @name', ['@name' => $nome]),
        (string) $form_state->getValue('contribution_rationale'),
        $payload,
        (string) $form_state->getValue('contribution_submission_token'),
      );
      $this->messenger()->addStatus($this->t('Sua proposta foi enviada. A organização só será criada após a aprovação e não foi vinculada ao Evento.'));
      $form_state->set('proposal_submitted', TRUE);
      $form_state->set('mode', 'search');
      $form_state->setRebuild(TRUE);
      return;
    }

    $slug = EventSlugGenerator::fromTitle($nome);
    $isClaimedImmediate = $choice === 'claimed';
    $isPendingClaim = ($choice === 'request_claim');

    /** @var \Drupal\aculta_events\Entity\EventOrganization $org */
    $org = $this->entityTypeManager->getStorage('aculta_event_organization')->create([
      'title' => $nome,
      'field_slug' => $slug,
      'body' => $body,
      'links' => $links,
      'localidade' => $cidade,
      'uf' => $uf,
      'ocultar_localidade' => $ocultarLoc,
      'field_status' => 'publicado',
      'reivindicada' => $isClaimedImmediate,
      'reivindicacao_status' => $isClaimedImmediate ? 'confirmada' : ($isPendingClaim ? 'pendente' : 'nao_reivindicada'),
      'titular' => $isClaimedImmediate ? $currentUid : NULL,
      'field_organizador' => $currentUid,
    ]);
    $org->save();

    if ($isClaimedImmediate && $currentUid > 0) {
      $this->membershipManager->addMembership($currentUid, (string) $org->id(), 'owner', 'approved', TRUE, TRUE);
    }
    elseif ($isPendingClaim && $currentUid > 0) {
      $justificativa = trim((string) $form_state->getValue('justificativa_reivindicacao'));
      $contato = trim((string) $form_state->getValue('contato_reivindicacao'));
      if ($this->claimService !== NULL) {
        $this->claimService->submitClaim($org, $this->currentUser(), $justificativa, $contato !== '' ? $contato : NULL);
      }
      $this->messenger()->addStatus($this->t('Organização cadastrada. Sua solicitação de titularidade foi enviada para análise da moderação.'));
    }

    $form_state->set('selected_org_id', (int) $org->id());
    if ($this->isHtmxRequest()) {
      $form_state->setResponse($this->htmxUpdateParentResponse(
        (int) $org->id(),
        (string) $form_state->get('field_name'),
        (string) $form_state->get('wrapper_id'),
        (bool) $form_state->get('is_multiple'),
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Processado pelos submits específicos.
  }

  /**
   * Aplica HTMX a um botão do modal: recorta e troca o wrapper do formulário.
   */
  private function applyHtmx(array &$button): void {
    (new Htmx())
      ->post($this->htmxUrl)
      ->select('#' . self::WRAPPER_ID)
      ->target('#' . self::WRAPPER_ID)
      ->swap('outerHTML')
      ->onlyMainContent()
      ->applyTo($button);
  }

  /**
   * Resposta HTMX que fecha o modal e troca o widget no formulário pai.
   */
  private function htmxUpdateParentResponse(int $orgId, string $fieldName, string $wrapperId, bool $isMultiple): Response {
    $card = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapperId,
        'data-portal-ui' => 'event-organization-widget',
        'data-field-name' => $fieldName,
        'hx-swap-oob' => 'outerHTML',
      ],
    ];
    $card += $isMultiple
      ? EventOrganizationModalWidget::buildMultipleCards([$orgId], $fieldName, $wrapperId, $this->entityTypeManager, $this->eventRouteContext, $this->getStringTranslation())
      : EventOrganizationModalWidget::buildSingleCardOrEmpty($orgId, $fieldName, $wrapperId, $this->entityTypeManager, $this->eventRouteContext, $this->getStringTranslation());

    return new Response(
      (string) $this->renderer->renderInIsolation($card),
      Response::HTTP_OK,
      [
        'Content-Type' => 'text/html; charset=UTF-8',
        'HX-Trigger' => 'aculta:close-dialog',
      ],
    );
  }

}
