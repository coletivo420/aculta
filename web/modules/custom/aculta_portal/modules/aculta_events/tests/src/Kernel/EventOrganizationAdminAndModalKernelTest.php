<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Controller\EventAdminOverviewController;
use Drupal\aculta_events\Controller\EventOrganizationAdminController;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Form\EventOrganizationModalForm;
use Drupal\aculta_events\Form\EventOrganizationRecoveryForm;
use Drupal\aculta_image\FileRemover;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_ugc\Entity\UgcReport;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\domain\DomainNegotiatorInterface;
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
    'aculta_ugc',
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
    $loader->addPsr4('Drupal\\aculta_ugc\\', $customRoot . '/modules/aculta_ugc/src', TRUE);
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
    $container->register('aculta_portal.unused_file_cleaner', \stdClass::class);
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
    $this->installConfig(['system', 'user', 'taxonomy', 'domain']);

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
    $this->assertCount(3, $render['table']['#rows']);

    // Ocultar localidade respeitado.
    $betaRow = NULL;
    foreach ($render['table']['#rows'] as $row) {
      if (str_contains((string) $row['title']['data']['#markup'], 'Beta')) {
        $betaRow = $row;
        break;
      }
    }
    $this->assertNotNull($betaRow);
    $this->assertStringContainsString('Ocultada', (string) $betaRow['localidade']);

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
    $this->assertArrayHasKey((int) $existing->id(), $formSearchResults['results']);

    $row = $formSearchResults['results'][(int) $existing->id()];
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

    $modalForm = new EventOrganizationModalForm(
      $this->container->get('entity_type.manager'),
      $this->container->get('aculta_events.permission_policy'),
      $this->container->get('aculta_user.organization_access_service'),
      $this->container->get('aculta_user.organization_membership_manager'),
    );

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

    $formBuild = $modalForm->buildForm([], $formState);
    $modalForm->validateForm($formBuild, $formState);
    $this->assertFalse($formState->hasAnyErrors());

    $modalForm->submitCreateOrganization($formBuild, $formState);
    $ajaxResponse = $modalForm->ajaxCreateOrganization($formBuild, $formState);
    $this->assertInstanceOf(AjaxResponse::class, $ajaxResponse);
    $commands = $ajaxResponse->getCommands();

    // Deve conter CloseModalDialogCommand, ReplaceCommand e InvokeCommand.
    $hasClose = FALSE;
    $hasReplace = FALSE;
    $hasFocus = FALSE;
    foreach ($commands as $cmd) {
      if ($cmd['command'] === 'closeDialog') {
        $hasClose = TRUE;
      }
      if ($cmd['command'] === 'insert' && ($cmd['selector'] ?? '') === '#org-wrapper-test') {
        $hasReplace = TRUE;
      }
      if ($cmd['command'] === 'invoke' && ($cmd['method'] ?? '') === 'focus') {
        $hasFocus = TRUE;
      }
    }

    $this->assertTrue($hasClose, 'Modal dialog foi fechado.');
    $this->assertTrue($hasReplace, 'Componente pai foi atualizado via ReplaceCommand.');
    $this->assertTrue($hasFocus, 'Foco devolvido ao gatilho.');

    // Confirma criação no banco.
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_event_organization');
    $orgs = $storage->loadByProperties(['title' => 'Coletivo Autônomo Inovador']);
    $this->assertCount(1, $orgs);
    /** @var \Drupal\aculta_events\Entity\EventOrganization $createdOrg */
    $createdOrg = reset($orgs);
    $this->assertEquals('coletivo-autonomo-inovador', $createdOrg->get('field_slug')->value);
    $this->assertTrue($createdOrg->shouldHideLocation());
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

}
