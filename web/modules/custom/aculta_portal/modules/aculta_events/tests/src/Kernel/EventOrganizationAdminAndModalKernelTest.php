<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_cgu\Service\ContributionStateMachine;
use Drupal\aculta_cgu\Service\ContributionLifecycle;
use Drupal\aculta_cgu\Adapter\ContributionAdapterRegistry;
use Drupal\aculta_events\Service\OrganizationContributionEventQueue;
use Drupal\aculta_events\Adapter\OrganizationContributionAdapter;
use Drupal\aculta_events\Controller\EventAdminOverviewController;
use Drupal\aculta_events\Controller\EventOrganizationAdminController;
use Drupal\aculta_events\Controller\EventOrganizerDashboardController;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Form\EventOrganizationModalForm;
use Drupal\aculta_events\Form\EventOrganizationRecoveryForm;
use Drupal\aculta_image\FileRemover;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_cgu\Entity\UgcReport;
use Drupal\aculta_user\Controller\OwnershipClaimAdminController;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\DomainInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Valida a sessão administrativa de organizações e o modal AJAX (ARQ-05).
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventOrganizationAdminAndModalKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'key',
    'options',
    'link',
    'text',
    'filter',
    'taxonomy',
    'datetime',
    'datetime_range',
    'address',
    'geofield',
    'domain',
    'entity',
    'profile',
    'path_alias',
    'file',
    'image',
    'aculta_calendar',
    'aculta_maps',
    'aculta_user',
    'aculta_cgu',
    'aculta_index',
    'aculta_events',
  ];

  /**
   * Usuário administrador.
   */
  private User $adminUser;

  /**
   * Usuário editor.
   */
  private User $editorUser;

  /**
   * Usuário comum.
   */
  private User $commonUser;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    $customRoot = DRUPAL_ROOT . '/modules/custom/aculta_portal';
    $loader->addPsr4('Drupal\\aculta_portal\\', $customRoot . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_image\\', $customRoot . '/modules/aculta_image/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_user\\', $customRoot . '/modules/aculta_user/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_cgu\\', $customRoot . '/modules/aculta_cgu/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_index\\', $customRoot . '/modules/aculta_index/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_events\\', $customRoot . '/modules/aculta_events/src', TRUE);

    $container->register('aculta_portal.domain_purpose', DomainPurposeManager::class)
      ->addArgument($this->createMock(EntityTypeManagerInterface::class))
      ->addArgument($this->createMock(DomainNegotiatorInterface::class))
      ->addArgument($this->createMock(ConfigFactoryInterface::class))
      ->addArgument(new RequestStack());

    $container->register('aculta_image.render', \stdClass::class);
    $container->register('aculta_image.file_remover', FileRemover::class)
      ->addArgument(new Reference('file.usage'))
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('database'))
      ->addArgument(new Reference('file_system'))
      ->addArgument(new Reference('logger.factory'));
    $container->register('aculta_portal.email_confirmation_policy', EmailConfirmationPolicy::class)
      ->addArgument(new Reference('user.data'))
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('module_handler'));
    $container->register('aculta_file_uploader.unused_file_cleaner', \stdClass::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('path_alias');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('aculta_user', ['aculta_user_nickname']);
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('aculta_org_member');
    $this->installEntitySchema('aculta_ownership_claim');
    $this->installEntitySchema('aculta_ugc_report');
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
    ]);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_event_organization');
    $this->installEntitySchema('aculta_contribution');
    $this->installConfig(['system', 'user', 'taxonomy', 'domain', 'aculta_cgu']);

    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'settings' => ['max_length' => 80],
    ])->save();

    $roleEditor = Role::create([
      'id' => 'content_editor',
      'label' => 'Editor de Conteúdo',
    ]);
    $roleEditor->grantPermission('cadastrar locais de eventos');
    $roleEditor->save();

    $roleAdmin = Role::create([
      'id' => 'admin_events',
      'label' => 'Administrador de Eventos',
    ]);
    $roleAdmin->grantPermission('administer aculta events');
    $roleAdmin->grantPermission('administer aculta claims');
    $roleAdmin->grantPermission('moderar denuncias ugc');
    $roleAdmin->save();

    $this->adminUser = User::create([
      'name' => 'admin_user',
      'mail' => 'admin@example.com',
      'status' => 1,
      'roles' => ['admin_events'],
    ]);
    $this->adminUser->save();

    $this->editorUser = User::create([
      'name' => 'editor_user',
      'mail' => 'editor@example.com',
      'status' => 1,
      'roles' => ['content_editor'],
    ]);
    $this->editorUser->save();

    $this->commonUser = User::create([
      'name' => 'common_user',
      'mail' => 'common@example.com',
      'status' => 1,
    ]);
    $this->commonUser->save();

    $this->container->get('current_user')->setAccount($this->adminUser);

    $mainDomain = $this->createMock(DomainInterface::class);
    $mainDomain->method('id')->willReturn('aculta_org');
    $eventsDomain = $this->createMock(DomainInterface::class);
    $eventsDomain->method('id')->willReturn('eventos_aculta_org');
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturnCallback(fn() => (int) $this->container->get('current_user')->id() === (int) $this->editorUser->id() ? $eventsDomain : $mainDomain);
    $this->container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));

  }

  /**
   * Valida links no menu administrativo e visão geral com métricas.
   */
  public function testAdminOverviewAndMetrics(): void {
    // Cria 2 organizações para teste de métrica.
    EventOrganization::create([
      'title' => 'Org Alpha',
      'reivindicada' => FALSE,
    ])->save();

    EventOrganization::create([
      'title' => 'Org Beta',
      'reivindicada' => TRUE,
      'titular' => (int) $this->editorUser->id(),
    ])->save();

    $controller = new EventAdminOverviewController(
      $this->container->get('entity_type.manager'),
    );

    $render = $controller->overview();
    $this->assertArrayHasKey('metrics', $render);
    $this->assertArrayHasKey('quick_actions', $render);

    $metricsTable = $render['metrics'];
    $this->assertEquals('2', $metricsTable['#rows'][3][1]);
    $this->assertContains('aculta_event_organization_list', $render['#cache']['tags']);

    // Valida existência das ações rápidas.
    $quickActions = $render['quick_actions']['#items'];
    $routeNames = [];
    foreach ($quickActions as $item) {
      if (isset($item['#url'])) {
        $routeNames[] = $item['#url']->getRouteName();
      }
    }
    $this->assertContains('aculta_events.admin_organizations', $routeNames);
    $this->assertContains('aculta_events.admin_organization_add', $routeNames);
  }

  /**
   * Valida listagem operacional, paginação, filtros e rotas no MAIN.
   */
  public function testAdminOrganizationsListingAndFilters(): void {
    $org1 = EventOrganization::create([
      'title' => 'Coletivo Musical Alfa',
      'localidade' => 'São Paulo',
      'uf' => 'SP',
      'reivindicada' => TRUE,
      'reivindicacao_status' => 'confirmada',
      'revisao_acesso_status' => 'regular',
      'titular' => (int) $this->editorUser->id(),
      'status' => 1,
    ]);
    $org1->save();

    $org2 = EventOrganization::create([
      'title' => 'Coletivo Teatral Beta',
      'localidade' => 'Rio de Janeiro',
      'uf' => 'RJ',
      'ocultar_localidade' => TRUE,
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'nao_reivindicada',
      'revisao_acesso_status' => 'regular',
      'status' => 1,
    ]);
    $org2->save();

    $org3 = EventOrganization::create([
      'title' => 'Associação Gama',
      'localidade' => 'Brasília',
      'uf' => 'DF',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'nao_reivindicada',
      'revisao_acesso_status' => 'revisao_requerida',
      'status' => 1,
    ]);
    $org3->save();

    $org4 = EventOrganization::create([
      'title' => 'Coletivo Pendente Delta',
      'localidade' => 'Porto Alegre',
      'uf' => 'RS',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'pendente',
      'revisao_acesso_status' => 'regular',
      'status' => 1,
    ]);
    $org4->save();

    // Adiciona reivindicações de teste para org4 e org1.
    $claimStorage = $this->container->get('entity_type.manager')->getStorage('aculta_ownership_claim');
    $claimStorage->create([
      'target_type' => 'aculta_event_organization',
      'target_id' => (int) $org4->id(),
      'uid' => (int) $this->commonUser->id(),
      'justificativa' => 'Pedido para Delta',
      'status' => 'pending',
    ])->save();

    $claimStorage->create([
      'target_type' => 'aculta_event_organization',
      'target_id' => (int) $org1->id(),
      'uid' => (int) $this->commonUser->id(),
      'justificativa' => 'Pedido para Alfa',
      'status' => 'pending',
    ])->save();

    // Adiciona denúncia UGC em org3.
    UgcReport::create([
      'target_type' => 'aculta_event_organization',
      'target_id' => (int) $org3->id(),
      'uid' => (int) $this->commonUser->id(),
      'motivo' => 'Perfil fraudulento',
      'status' => 'pending',
    ])->save();

    $controller = new EventOrganizationAdminController(
      $this->container->get('entity_type.manager'),
    );

    // Listagem geral sem filtros.
    $request = Request::create('/painel-administrativo/eventos/organizacoes');
    $render = $controller->overview($request);
    $this->assertArrayHasKey('table', $render);
    $this->assertCount(4, $render['table']['#rows']);

    // Ocultar localidade respeitado.
    $betaRow = NULL;
    $deltaRow = NULL;
    foreach ($render['table']['#rows'] as $row) {
      if (str_contains((string) $row['title']['data']['#markup'], 'Beta')) {
        $betaRow = $row;
      }
      if (str_contains((string) $row['title']['data']['#markup'], 'Delta')) {
        $deltaRow = $row;
      }
    }
    $this->assertNotNull($betaRow);
    $this->assertStringContainsString('Ocultada', (string) $betaRow['localidade']);

    // Organização pendente exibe badge de reivindicação pendente.
    $this->assertNotNull($deltaRow);
    $deltaBadges = array_map(static fn(array $item) => (string) ($item['#value'] ?? ''), $deltaRow['status']['data']);
    $this->assertContains('Reivindicação pendente', $deltaBadges);

    // Organização com reivindicação pendente possui link de reivindicações
    // com filtros pré-aplicados.
    $this->assertArrayHasKey('claims', $deltaRow['actions']['data']['#links']);
    $claimsUrl = $deltaRow['actions']['data']['#links']['claims']['url'];
    $this->assertEquals('aculta_user.ownership_claims', $claimsUrl->getRouteName());
    $this->assertEquals([
      'target_type' => 'aculta_event_organization',
      'target_id' => $org4->id(),
    ], $claimsUrl->getOption('query'));

    // Valida filtragem no controlador de reivindicações do aculta_user.
    $claimsController = OwnershipClaimAdminController::create($this->container);
    $claimsRequest = Request::create(
      '/painel-administrativo/pessoas/reivindicacoes',
      'GET',
      [
        'target_type' => 'aculta_event_organization',
        'target_id' => (string) $org4->id(),
      ]
    );
    $claimsRender = $claimsController->overview($claimsRequest);
    $this->assertArrayHasKey('table', $claimsRender);
    $this->assertCount(1, $claimsRender['table']['#rows']);

    // Operações apontam para rotas administrativas no MAIN.
    $operations = $betaRow['actions']['data']['#links'];
    $this->assertArrayHasKey('edit', $operations);
    $this->assertEquals('aculta_events.admin_organization_edit', $operations['edit']['url']->getRouteName());
    $this->assertEquals('aculta_events.admin_organization_members', $operations['members']['url']->getRouteName());
    $this->assertEquals('aculta_events.admin_organization_recovery', $operations['recovery']['url']->getRouteName());

    // Filtro por nome: 'Alfa'.
    $requestNome = Request::create('/painel-administrativo/eventos/organizacoes', 'GET', ['nome' => 'Alfa']);
    $renderNome = $controller->overview($requestNome);
    $this->assertCount(1, $renderNome['table']['#rows']);

    // Filtro por reivindicada: 'reivindicada'.
    $requestReiv = Request::create('/painel-administrativo/eventos/organizacoes', 'GET', ['reivindicada' => 'reivindicada']);
    $renderReiv = $controller->overview($requestReiv);
    $this->assertCount(1, $renderReiv['table']['#rows']);

    // Filtro por reivindicada: 'pendente'.
    $requestPend = Request::create('/painel-administrativo/eventos/organizacoes', 'GET', ['reivindicada' => 'pendente']);
    $renderPend = $controller->overview($requestPend);
    $this->assertCount(1, $renderPend['table']['#rows']);
    $this->assertStringContainsString('Delta', (string) $renderPend['table']['#rows'][0]['title']['data']['#markup']);

    // Filtro por reivindicada: 'nao_reivindicada'.
    $requestNaoReiv = Request::create('/painel-administrativo/eventos/organizacoes', 'GET', ['reivindicada' => 'nao_reivindicada']);
    $renderNaoReiv = $controller->overview($requestNaoReiv);
    $this->assertCount(3, $renderNaoReiv['table']['#rows']);

    // Filtro por denúncias UGC: '1'.
    $requestDen = Request::create('/painel-administrativo/eventos/organizacoes', 'GET', ['com_denuncias' => '1']);
    $renderDen = $controller->overview($requestDen);
    $this->assertCount(1, $renderDen['table']['#rows']);
    $this->assertStringContainsString('Gama', (string) $renderDen['table']['#rows'][0]['title']['data']['#markup']);
  }

  /**
   * Valida recuperação administrativa emergencial de titularidade.
   */
  public function testAdminOrganizationRecovery(): void {
    $org = EventOrganization::create([
      'title' => 'Coletivo em Disputa',
      'reivindicada' => TRUE,
      'reivindicacao_status' => 'confirmada',
      'titular' => (int) $this->editorUser->id(),
    ]);
    $org->save();

    $this->assertEquals((int) $this->editorUser->id(), $org->getOwnerId());

    $form = new EventOrganizationRecoveryForm(
      $this->container->get('entity_type.manager'),
      $this->container->get('aculta_user.ownership_claim_service'),
    );

    $formState = new FormState();
    $formBuild = $form->buildForm([], $formState, $org);
    $this->assertArrayHasKey('new_owner', $formBuild);

    // Submete recuperação administrativa transferindo para commonUser.
    $formState->setValue('new_owner', (int) $this->commonUser->id());
    $formState->setValue('justification', 'Decisão judicial com ordem de posse administrativa.');

    $form->validateForm($formBuild, $formState);
    $this->assertFalse($formState->hasAnyErrors());

    $form->submitForm($formBuild, $formState);

    // Recarrega organização e verifica transferência de titularidade.
    $reloadedOrg = $this->container->get('entity_type.manager')
      ->getStorage('aculta_event_organization')
      ->load($org->id());

    $this->assertInstanceOf(EventOrganization::class, $reloadedOrg);
    $this->assertEquals((int) $this->commonUser->id(), $reloadedOrg->getOwnerId());
  }

  /**
   * Valida modal AJAX: busca e prevenção de duplicidades.
   */
  public function testModalSearchAndDuplicateWarning(): void {
    $existing = EventOrganization::create([
      'title' => 'Ponto Cultural Esperança',
      'localidade' => 'Campinas',
      'uf' => 'SP',
      'field_status' => 'publicado',
      'reivindicada' => TRUE,
      'reivindicacao_status' => 'confirmada',
      'status' => 1,
    ]);
    $existing->save();

    $this->container->get('current_user')->setAccount($this->editorUser);

    $modalForm = new EventOrganizationModalForm(
      $this->container->get('entity_type.manager'),
      $this->container->get('aculta_events.permission_policy'),
      $this->container->get('aculta_user.organization_access_service'),
      $this->container->get('aculta_user.organization_membership_manager'),
    );

    // Passo 1: Busca por 'Esperança'.
    $formState = new FormState();
    $formState->set('field_name', 'field_organizacao');
    $formState->set('wrapper_id', 'org-wrapper-test');
    $formState->set('is_multiple', FALSE);
    $formState->setValue('termo', 'Esperança');

    $formBuild = $modalForm->buildForm([], $formState);
    $modalForm->submitSearch($formBuild, $formState);

    // Constrói novamente o form pós-busca com termo no estado.
    $formSearchResults = $modalForm->buildForm([], $formState);
    $this->assertArrayHasKey('results', $formSearchResults);
    $this->assertArrayHasKey((int) $existing->id(), $formSearchResults['results']['table']);
    $this->assertSame('event-organization-results-table', $formSearchResults['results']['#attributes']['data-portal-ui']);

    $row = $formSearchResults['results']['table'][(int) $existing->id()];
    $this->assertStringContainsString('Ponto Cultural Esperança', (string) $row['title']['#markup']);
    $this->assertStringContainsString('Reivindicada', (string) $row['badge']['#markup']);

    // Passo 2: Tentativa de cadastro com nome similar detecta duplicidade.
    $createFormState = new FormState();
    $createFormState->set('mode', 'create');
    $createFormState->set('field_name', 'field_organizacao');
    $createFormState->set('wrapper_id', 'org-wrapper-test');
    $createFormState->set('is_multiple', FALSE);
    $createFormState->setTriggeringElement(['#name' => 'op_create_save']);

    $createFormState->setValue('novo_nome', 'Ponto Cultural Esperança');
    $createFormState->setValue('is_claimed_choice', 'unclaimed');

    $createBuild = $modalForm->buildForm([], $createFormState);
    $modalForm->validateForm($createBuild, $createFormState);

    // Sem confirmação de duplicidade, deve haver erro ou aviso bloqueando.
    $this->assertTrue($createFormState->hasAnyErrors());
  }

  /**
   * Valida criação pelo modal AJAX com atualização do componente pai e foco.
   */
  public function testModalCreateAndParentUpdate(): void {
    $this->container->get('current_user')->setAccount($this->editorUser);

    $modalForm = $this->createContributionModalForm();
    $formState = new FormState();
    $formState->set('mode', 'create');
    $formState->set('field_name', 'field_organizacao');
    $formState->set('wrapper_id', 'org-wrapper-test');
    $formState->set('is_multiple', FALSE);
    $formState->setTriggeringElement(['#name' => 'op_create_save']);

    $formState->setValue('novo_nome', 'Coletivo Autônomo Inovador');
    $formState->setValue('novo_body', 'Descrição sobre o coletivo autônomo.');
    $formState->setValue('novo_localidade', 'Salvador');
    $formState->setValue('novo_uf', 'BA');
    $formState->setValue('novo_ocultar_localidade', 1);
    $formState->setValue('is_claimed_choice', 'unclaimed');
    $formState->setValue('novo_links', 'https://autonomo.org');
    $formState->setValue('contribution_rationale', 'Este coletivo precisa de um perfil para ser encontrado.');
    $formState->setValue('contribution_submission_token', str_repeat('a', 64));

    $formBuild = $modalForm->buildForm([], $formState);
    $modalForm->validateForm($formBuild, $formState);
    $this->assertFalse($formState->hasAnyErrors());

    $modalForm->submitCreateOrganization($formBuild, $formState);
    $ajaxResponse = $modalForm->ajaxCreateOrganization($formBuild, $formState);
    $this->assertIsArray($ajaxResponse, 'A proposta mantém o modal aberto para mostrar o status.');
    $this->assertTrue($formState->get('proposal_submitted'));
    $this->assertNull($formState->get('selected_org_id'));

    // A submissão cria uma proposta privada, não um perfil público.
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_event_organization');
    $orgs = $storage->loadByProperties(['title' => 'Coletivo Autônomo Inovador']);
    $this->assertCount(0, $orgs);
    $proposals = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')
      ->loadByProperties(['intent' => 'organization_create']);
    $this->assertCount(1, $proposals);
    $proposal = reset($proposals);
    $this->assertSame('submitted', $proposal->get('state')->value);
    $this->assertSame('Este coletivo precisa de um perfil para ser encontrado.', $proposal->get('rationale')->value);
    $this->assertSame('https://autonomo.org', $proposal->getPayload()['links']);
  }

  /**
   * Valida restrição de segurança no servidor para seleção no EventForm.
   */
  public function testEventFormValidationSecurity(): void {
    // Organização reivindicada pertencente ao adminUser.
    $claimedOrg = EventOrganization::create([
      'title' => 'Organização Privada Admin',
      'reivindicada' => TRUE,
      'reivindicacao_status' => 'confirmada',
      'titular' => (int) $this->adminUser->id(),
    ]);
    $claimedOrg->save();

    // Outra organização qualquer.
    $coOrg = EventOrganization::create([
      'title' => 'Organização Coorganizadora',
      'reivindicada' => FALSE,
    ]);
    $coOrg->save();

    // EditorUser tenta salvar evento com claimedOrg sem ter permissão/behalf.
    $this->container->get('current_user')->setAccount($this->editorUser);

    $event = Event::create([
      'title' => 'Evento Teste Restrição',
      'field_organizador' => (int) $this->editorUser->id(),
    ]);

    $formObject = $this->container->get('entity_type.manager')->getFormObject('aculta_event', 'default');
    $formObject->setEntity($event);

    $formState = new FormState();
    $form = $this->container->get('form_builder')->buildForm($formObject, $formState);

    $formState->setValue('field_organizacao', [
      ['target_id' => (string) $claimedOrg->id()],
    ]);
    $formState->setValue('field_coorganizadores', [
      ['target_id' => (string) $coOrg->id()],
    ]);

    $formObject->validateForm($form, $formState);

    // Deve falhar pois claimedOrg não é gerida por editorUser.
    $this->assertTrue($formState->hasAnyErrors());
    $errors = $formState->getErrors();
    $this->assertArrayHasKey('field_organizacao', $errors);

    // Teste 2: organização não pode ser simultaneamente coorganizadora.
    $formState2 = new FormState();
    $form2 = $this->container->get('form_builder')->buildForm($formObject, $formState2);
    $formState2->setValue('field_organizacao', [
      ['target_id' => (string) $coOrg->id()],
    ]);
    $formState2->setValue('field_coorganizadores', [
      ['target_id' => (string) $coOrg->id()],
    ]);

    $formObject->validateForm($form2, $formState2);
    $this->assertTrue($formState2->hasAnyErrors());
    $errors2 = $formState2->getErrors();
    $this->assertArrayHasKey('field_coorganizadores', $errors2);
  }

  /**
   * Valida solicitação de titularidade por não-admin e projeção no índice.
   */
  public function testNonAdminModalClaimRequestLifecycleAndIndex(): void {
    $this->container->get('current_user')->setAccount($this->editorUser);

    $modalForm = $this->createContributionModalForm();

    // 1. Não-admin tentando assumir titularidade imediata falha na validação.
    $stateClaimImmediate = new FormState();
    $stateClaimImmediate->set('mode', 'create');
    $stateClaimImmediate->setTriggeringElement(['#name' => 'op_create_save']);
    $stateClaimImmediate->setValue('novo_nome', 'Coletivo Nova Esperança');
    $stateClaimImmediate->setValue('is_claimed_choice', 'claimed');

    $buildImmediate = $modalForm->buildForm([], $stateClaimImmediate);
    $modalForm->validateForm($buildImmediate, $stateClaimImmediate);
    $this->assertTrue($stateClaimImmediate->hasAnyErrors());
    $this->assertArrayHasKey('is_claimed_choice', $stateClaimImmediate->getErrors());

    // 2. Solicitação de titularidade deve seguir o fluxo próprio após a
    // aprovação da criação.
    $stateClaimNoJust = new FormState();
    $stateClaimNoJust->set('mode', 'create');
    $stateClaimNoJust->setTriggeringElement(['#name' => 'op_create_save']);
    $stateClaimNoJust->setValue('novo_nome', 'Coletivo Nova Esperança');
    $stateClaimNoJust->setValue('is_claimed_choice', 'request_claim');
    $stateClaimNoJust->setValue('justificativa_reivindicacao', '');

    $buildNoJust = $modalForm->buildForm([], $stateClaimNoJust);
    $modalForm->validateForm($buildNoJust, $stateClaimNoJust);
    $this->assertTrue($stateClaimNoJust->hasAnyErrors());
    $this->assertArrayHasKey('is_claimed_choice', $stateClaimNoJust->getErrors());

    // 3. A proposta não cria organização nem registra claim de titularidade.
    $stateValid = new FormState();
    $stateValid->set('mode', 'create');
    $stateValid->set('field_name', 'field_organizacao');
    $stateValid->set('wrapper_id', 'org-wrapper-test');
    $stateValid->set('is_multiple', FALSE);
    $stateValid->setTriggeringElement(['#name' => 'op_create_save']);

    $stateValid->setValue('novo_nome', 'Coletivo Vanguarda Singular');
    $stateValid->setValue('novo_localidade', 'Campinas');
    $stateValid->setValue('novo_uf', 'SP');
    $stateValid->setValue('is_claimed_choice', 'unclaimed');
    $stateValid->setValue('contribution_rationale', 'O coletivo precisa de um perfil público.');
    $stateValid->setValue('contribution_submission_token', str_repeat('b', 64));

    $buildValid = $modalForm->buildForm([], $stateValid);
    $modalForm->validateForm($buildValid, $stateValid);
    $this->assertSame([], $stateValid->getErrors());

    $modalForm->submitCreateOrganization($buildValid, $stateValid);

    // A proposta fica privada e não cria organização nem claim.
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_event_organization');
    $orgs = $storage->loadByProperties(['title' => 'Coletivo Vanguarda Singular']);
    $this->assertCount(0, $orgs);
    $proposalStorage = $this->container->get('entity_type.manager')->getStorage('aculta_contribution');
    $proposals = $proposalStorage->loadByProperties(['intent' => 'organization_create']);
    $this->assertCount(1, $proposals);
    $proposal = reset($proposals);
    $this->assertSame('submitted', $proposal->get('state')->value);
    $this->assertSame('O coletivo precisa de um perfil público.', $proposal->get('rationale')->value);

    // Nenhum pedido de titularidade é gravado junto com a proposta de criação.
    $claimStorage = $this->container->get('entity_type.manager')->getStorage('aculta_ownership_claim');
    $this->assertCount(0, $claimStorage->loadMultiple());
  }

  /**
   * Creates the modal with the registered contribution services for tests. */
  private function createContributionModalForm(): EventOrganizationModalForm {
    $adapter = new OrganizationContributionAdapter(
      $this->container->get('entity_type.manager'),
      $this->container->get('aculta_portal.domain_purpose'),
      $this->container->get('aculta_events.permission_policy'),
      $this->container->get('lock'),
      $this->container->get('database'),
      new OrganizationContributionEventQueue($this->container->get('queue'), $this->container->get('keyvalue')),
    );
    $registry = new ContributionAdapterRegistry();
    $registry->addAdapter($adapter);
    $lifecycle = new ContributionLifecycle(
      $this->container->get('entity_type.manager'),
      $this->container->get('current_user'),
      $registry,
      new ContributionStateMachine(),
      $this->container->get('flood'),
      $this->container->get('lock'),
      $this->container->get('database'),
      $this->container->get('config.factory'),
      $this->container->get('logger.factory')->get('aculta_contributions'),
    );
    return new EventOrganizationModalForm(
      $this->container->get('entity_type.manager'),
      $this->container->get('aculta_events.permission_policy'),
      $this->container->get('aculta_user.organization_access_service'),
      $this->container->get('aculta_user.organization_membership_manager'),
      $this->container->get('aculta_user.ownership_claim_service'),
      $lifecycle,
      $adapter,
    );
  }

  /**
   * Valida visibilidade e escopo de eventos no dashboard por organização.
   */
  public function testOrganizerDashboardVisibilityForOrganizationMembers(): void {
    /** @var \Drupal\aculta_user\Organization\OrganizationMembershipManager $membershipManager */
    $membershipManager = $this->container->get('aculta_user.organization_membership_manager');

    // Cria organização reivindicada com titular outro editor.
    $otherEditor = User::create([
      'name' => 'other_editor',
      'mail' => 'other@example.com',
      'status' => 1,
      'roles' => ['content_editor'],
    ]);
    $otherEditor->save();

    $unrelatedEditor = User::create([
      'name' => 'unrelated_editor',
      'mail' => 'unrelated@example.com',
      'status' => 1,
      'roles' => ['content_editor'],
    ]);
    $unrelatedEditor->save();

    /** @var \Drupal\aculta_events\Entity\EventOrganization $org */
    $org = EventOrganization::create([
      'name' => 'Coletivo das Artes',
      'field_slug' => 'coletivo-das-artes',
      'reivindicada' => TRUE,
      'reivindicacao_status' => 'confirmada',
      'titular' => (int) $otherEditor->id(),
    ]);
    $org->save();

    // Outro editor é titular e tem membership owner.
    $membershipManager->addMembership($otherEditor, (string) $org->id(), 'owner', 'approved', TRUE, TRUE);

    // Adiciona editorUser como membro aprovado com papel 'editor'.
    $membershipManager->addMembership($this->editorUser, (string) $org->id(), 'editor', 'approved', FALSE, FALSE);

    // Cria Evento 1 associado à organização (field_organizador é otherEditor).
    $event1 = Event::create([
      'title' => 'Festival Coletivo das Artes',
      'field_status' => 'publicado',
      'field_organizador' => (int) $otherEditor->id(),
      'field_organizacao' => (int) $org->id(),
    ]);
    $event1->save();

    // 1. Visão do editorUser (membro aprovado da organização):
    // Deve gerenciar Festival Coletivo das Artes no dashboard.
    $this->container->get('current_user')->setAccount($this->editorUser);
    $controller = EventOrganizerDashboardController::create($this->container);
    $buildEditor = $controller->page();

    $this->assertNotEmpty($buildEditor['events']['#rows'], 'Editor vinculado enxerga os eventos da organização.');
    $foundEvent1 = FALSE;
    foreach ($buildEditor['events']['#rows'] as $row) {
      if (isset($row[0]['data']) && $row[0]['data'] === 'Festival Coletivo das Artes') {
        $foundEvent1 = TRUE;
        break;
      }
    }
    $this->assertTrue($foundEvent1, 'Evento da organização está listado no dashboard do membro aprovado.');

    // 2. Visão do unrelatedEditor (sem vínculo com a organização):
    // Não deve enxergar o evento da organização alheia.
    $this->container->get('current_user')->setAccount($unrelatedEditor);
    $buildUnrelated = $controller->page();
    $foundForUnrelated = FALSE;
    foreach ($buildUnrelated['events']['#rows'] as $row) {
      if (isset($row[0]['data']) && $row[0]['data'] === 'Festival Coletivo das Artes') {
        $foundForUnrelated = TRUE;
        break;
      }
    }
    $this->assertFalse($foundForUnrelated, 'Editor sem vínculo não enxerga eventos de organização alheia.');

    // 3. Testa coorganizadores:
    // Cria organização secundária em que o editorUser é titular.
    $coOrg = EventOrganization::create([
      'name' => 'Associação Parceira',
      'field_slug' => 'associacao-parceira',
      'reivindicada' => TRUE,
      'reivindicacao_status' => 'confirmada',
      'titular' => (int) $this->editorUser->id(),
    ]);
    $coOrg->save();

    // Evento 2 criado pelo otherEditor com Associação Parceira como parceira.
    $event2 = Event::create([
      'title' => 'Encontro Parcerias Culturais',
      'field_status' => 'publicado',
      'field_organizador' => (int) $otherEditor->id(),
      'field_coorganizadores' => [(int) $coOrg->id()],
    ]);
    $event2->save();

    // editorUser deve enxergar o Evento 2 no dashboard via coorganização.
    $this->container->get('current_user')->setAccount($this->editorUser);
    $buildCoOrg = $controller->page();
    $foundEvent2 = FALSE;
    foreach ($buildCoOrg['events']['#rows'] as $row) {
      if (isset($row[0]['data']) && $row[0]['data'] === 'Encontro Parcerias Culturais') {
        $foundEvent2 = TRUE;
        break;
      }
    }
    $this->assertTrue($foundEvent2, 'Evento com coorganização associada ao usuário está listado no dashboard.');
  }

}
