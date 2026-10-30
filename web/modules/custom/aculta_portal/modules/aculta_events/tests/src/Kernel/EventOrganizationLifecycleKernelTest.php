<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_image\FileRemover;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_ugc\Entity\UgcReport;
use Drupal\aculta_user\Entity\OwnershipClaim;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Valida ciclo de vida de Organizações Culturais e Coorganização (ARQ-05).
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventOrganizationLifecycleKernelTest extends KernelTestBase {

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
   * Usuário editor de conteúdo.
   */
  private User $editorUser;

  /**
   * Outro usuário editor de conteúdo.
   */
  private User $anotherEditorUser;

  /**
   * Usuário comum sem permissões privilegiadas.
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
    $roleEditor->save();

    $roleAdmin = Role::create([
      'id' => 'admin_events',
      'label' => 'Administrador de Eventos',
    ]);
    $roleAdmin->grantPermission('administer aculta events');
    $roleAdmin->grantPermission('administer aculta claims');
    $roleAdmin->grantPermission('moderar denuncias ugc');
    $roleAdmin->save();

    $rootUser = User::create(['name' => 'root', 'mail' => 'root@example.com', 'status' => 1]);
    $rootUser->save();

    $this->adminUser = User::create(['name' => 'admin_events', 'mail' => 'admin@example.com', 'status' => 1]);
    $this->adminUser->addRole('admin_events');
    $this->adminUser->save();

    $this->editorUser = User::create(['name' => 'editor_user', 'mail' => 'editor@example.com', 'status' => 1]);
    $this->editorUser->addRole('content_editor');
    $this->editorUser->save();

    $this->anotherEditorUser = User::create(['name' => 'another_editor', 'mail' => 'another@example.com', 'status' => 1]);
    $this->anotherEditorUser->addRole('content_editor');
    $this->anotherEditorUser->save();

    $this->commonUser = User::create(['name' => 'common_user', 'mail' => 'common@example.com', 'status' => 1]);
    $this->commonUser->save();
  }

  /**
   * Valida criação/edição de organização não reivindicada e visão pública.
   */
  public function testUnclaimedOrganizationCreationAndPublicView(): void {
    $org = EventOrganization::create([
      'title' => 'Coletivo Cultural do Bairro',
      'field_slug' => 'coletivo-cultural-bairro',
      'body' => 'Espaço aberto gerido coletivamente.',
      'localidade' => 'Goiânia',
      'uf' => 'GO',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'nao_reivindicada',
      'field_status' => 'publicado',
      'status' => 1,
    ]);
    $org->save();

    $this->assertTrue($org->isUnclaimed());
    $this->assertFalse($org->isClaimed());
    $this->assertSame('nao_reivindicada', $org->getClaimStatus());

    // Leitura pública permitida para todos.
    $this->assertTrue($org->access('view', $this->commonUser));

    // Qualquer usuário com permissão de criar eventos pode editar perfil de
    // organização não reivindicada.
    $this->assertTrue($org->access('update', $this->editorUser));
    $this->assertTrue($org->access('update', $this->anotherEditorUser));

    // Usuário comum sem permissão de criar eventos não pode editar.
    $this->assertFalse($org->access('update', $this->commonUser));
  }

  /**
   * Valida reivindicação, aprovação e suspensão preventiva dos acessos.
   */
  public function testClaimSubmissionAndApprovalWithSuspension(): void {
    /** @var \Drupal\aculta_user\Organization\OrganizationMembershipManager $memberManager */
    $memberManager = $this->container->get('aculta_user.organization_membership_manager');
    /** @var \Drupal\aculta_user\Organization\OwnershipClaimService $claimService */
    $claimService = $this->container->get('aculta_user.ownership_claim_service');

    $org = EventOrganization::create([
      'title' => 'Associação de Artistas Tradicionais',
      'field_slug' => 'associacao-artistas',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'nao_reivindicada',
      'field_status' => 'publicado',
      'status' => 1,
    ]);
    $org->save();

    // Adiciona membro anterior na organização antes da reivindicação.
    $memberPrior = $memberManager->addMembership($this->anotherEditorUser, (string) $org->id(), 'editor', 'approved');
    $this->assertTrue($memberManager->hasApprovedMembership($this->anotherEditorUser, (string) $org->id()));

    // Usuário comum solicita a reivindicação da organização.
    $claim = $claimService->submitClaim(
      $org,
      $this->commonUser,
      'Sou o novo presidente eleito pela ata da assembleia de outubro.',
      'contato@artistas.org'
    );
    $this->assertInstanceOf(OwnershipClaim::class, $claim);
    $this->assertSame('pending', $claim->get('status')->value);

    // Administrador aprova a reivindicação.
    $claimService->decideClaim($claim, $this->adminUser, TRUE, 'Ata e estatuto conferidos.');

    $reloadedOrg = EventOrganization::load($org->id());
    $this->assertNotNull($reloadedOrg);
    $this->assertTrue($reloadedOrg->isClaimed());
    $this->assertSame('em_revisao', $reloadedOrg->getClaimStatus());
    $this->assertSame((int) $this->commonUser->id(), $reloadedOrg->getOwnerId());

    // Membro anterior foi suspenso preventivamente.
    $this->assertFalse($memberManager->hasApprovedMembership($this->anotherEditorUser, (string) $org->id()));
    $reloadedMember = $this->container->get('entity_type.manager')->getStorage('aculta_org_member')->load($memberPrior->id());
    $this->assertSame('suspended', $reloadedMember->get('status')->value);

    // Titular revisa e confirma membro anterior como editor com poderes.
    $memberManager->confirmSuspendedMember($reloadedMember, 'editor', FALSE, FALSE, $this->commonUser);
    $this->assertTrue($memberManager->hasApprovedMembership($this->anotherEditorUser, (string) $org->id()));

    // Titular conclui a revisão.
    $reloadedOrg->set('reivindicacao_status', 'confirmada');
    $reloadedOrg->save();
    $this->assertSame('confirmada', $reloadedOrg->getClaimStatus());
  }

  /**
   * Valida papéis, publicação separada e poderes de coproprietários.
   */
  public function testRolesAndDelegatedPermissions(): void {
    /** @var \Drupal\aculta_user\Organization\OrganizationMembershipManager $memberManager */
    $memberManager = $this->container->get('aculta_user.organization_membership_manager');
    /** @var \Drupal\aculta_user\Organization\OrganizationAccessService $accessService */
    $accessService = $this->container->get('aculta_user.organization_access_service');

    $org = EventOrganization::create([
      'title' => 'Fundação Cultural Viva',
      'field_slug' => 'fundacao-cultural-viva',
      'reivindicada' => TRUE,
      'reivindicacao_status' => 'confirmada',
      'titular' => (int) $this->editorUser->id(),
      'status' => 1,
    ]);
    $org->save();
    $memberManager->addMembership($this->editorUser, (string) $org->id(), 'owner', 'approved', TRUE, TRUE);

    // Coproprietário com poderes configuráveis.
    $coOwner = User::create(['name' => 'co_owner_user', 'mail' => 'coowner@example.com', 'status' => 1]);
    $coOwner->addRole('content_editor');
    $coOwner->save();
    $memberManager->addMembership($coOwner, (string) $org->id(), 'co_owner', 'approved', TRUE, FALSE);

    $this->assertTrue($accessService->canManageOrganization($coOwner, (string) $org->id()));
    $this->assertFalse($accessService->canPublishForOrganization($coOwner, (string) $org->id()));

    // Publicador (delegado de publicação).
    $publisher = User::create(['name' => 'publisher_user', 'mail' => 'publisher@example.com', 'status' => 1]);
    $publisher->addRole('content_editor');
    $publisher->save();
    $memberManager->addMembership($publisher, (string) $org->id(), 'publisher', 'approved', FALSE, TRUE);

    $this->assertTrue($accessService->canPublishForOrganization($publisher, (string) $org->id()));
    $this->assertFalse($accessService->canManageOrganization($publisher, (string) $org->id()));

    // Editor simples (sem publicação).
    $editor = User::create(['name' => 'simple_editor', 'mail' => 'editor_simple@example.com', 'status' => 1]);
    $editor->addRole('content_editor');
    $editor->save();
    $memberManager->addMembership($editor, (string) $org->id(), 'editor', 'approved', FALSE, FALSE);

    $this->assertTrue($accessService->canActOnBehalf($editor, (string) $org->id()));
    $this->assertFalse($accessService->canPublishForOrganization($editor, (string) $org->id()));

    // Evento associado à organização.
    $event = Event::create([
      'title' => 'Festival Viva Arte',
      'field_status' => 'publicado',
      'field_organizacao' => (int) $org->id(),
    ]);
    $event->save();

    // Cada membro com papel ativo pode editar o evento.
    $this->assertTrue($event->access('update', $this->editorUser));
    $this->assertTrue($event->access('update', $coOwner));
    $this->assertTrue($event->access('update', $publisher));
    $this->assertTrue($event->access('update', $editor));

    // Apenas titular e publicador têm capacidade de publicação.
    $this->assertTrue($accessService->canPublishEvent($event, $this->editorUser));
    $this->assertTrue($accessService->canPublishEvent($event, $publisher));
    $this->assertFalse($accessService->canPublishEvent($event, $editor));
    $this->assertFalse($accessService->canPublishEvent($event, $coOwner));
  }

  /**
   * Valida transferência de titularidade e recuperação administrativa.
   */
  public function testOwnershipTransferLifecycleAndAdministrativeRecovery(): void {
    /** @var \Drupal\aculta_user\Organization\OrganizationMembershipManager $memberManager */
    $memberManager = $this->container->get('aculta_user.organization_membership_manager');
    /** @var \Drupal\aculta_user\Organization\OwnershipClaimService $claimService */
    $claimService = $this->container->get('aculta_user.ownership_claim_service');

    $org = EventOrganization::create([
      'title' => 'Instituto de Cinema',
      'field_slug' => 'instituto-cinema',
      'reivindicada' => TRUE,
      'reivindicacao_status' => 'confirmada',
      'titular' => (int) $this->editorUser->id(),
      'status' => 1,
    ]);
    $org->save();
    $memberManager->addMembership($this->editorUser, (string) $org->id(), 'owner', 'approved', TRUE, TRUE);

    $coOwner = $this->anotherEditorUser;
    $memberManager->addMembership($coOwner, (string) $org->id(), 'co_owner', 'approved', TRUE, TRUE);

    // 1. Titular inicia transferência.
    $claimService->initiateOwnershipTransfer($org, $this->editorUser, $coOwner);
    $reloadedOrg = EventOrganization::load($org->id());
    $this->assertSame((int) $coOwner->id(), (int) $reloadedOrg->get('pending_owner_uid')->target_id);

    // 2. Coproprietário aceita a titularidade.
    $claimService->acceptOwnershipTransfer($reloadedOrg, $coOwner);
    $finalOrg = EventOrganization::load($org->id());
    $this->assertSame((int) $coOwner->id(), $finalOrg->getOwnerId());
    $this->assertNull($finalOrg->get('pending_owner_uid')->target_id);

    // O antigo titular foi rebaixado para coproprietário aprovado.
    $this->assertSame('co_owner', $memberManager->getMemberRole($this->editorUser, (string) $org->id()));
    $this->assertSame('owner', $memberManager->getMemberRole($coOwner, (string) $org->id()));

    // 3. Recuperação administrativa excepcional por administrador.
    $claimService->administrativeRecovery($finalOrg, $this->adminUser, $this->editorUser, 'Recuperação administrativa comprovada.');
    $recoveredOrg = EventOrganization::load($org->id());
    $this->assertSame((int) $this->editorUser->id(), $recoveredOrg->getOwnerId());
  }

  /**
   * Valida coorganização de eventos: acesso de edição e revogação.
   */
  public function testEventCoOrganizationAccessAndRevocation(): void {
    /** @var \Drupal\aculta_user\Organization\OrganizationMembershipManager $memberManager */
    $memberManager = $this->container->get('aculta_user.organization_membership_manager');
    /** @var \Drupal\aculta_user\Organization\OrganizationAccessService $accessService */
    $accessService = $this->container->get('aculta_user.organization_access_service');

    $orgPrincipal = EventOrganization::create([
      'title' => 'Organização Matriz',
      'reivindicada' => TRUE,
      'titular' => (int) $this->editorUser->id(),
      'status' => 1,
    ]);
    $orgPrincipal->save();
    $memberManager->addMembership($this->editorUser, (string) $orgPrincipal->id(), 'owner', 'approved', TRUE, TRUE);

    $orgCo = EventOrganization::create([
      'title' => 'Organização Coorganizadora',
      'reivindicada' => TRUE,
      'titular' => (int) $this->anotherEditorUser->id(),
      'status' => 1,
    ]);
    $orgCo->save();
    $memberManager->addMembership($this->anotherEditorUser, (string) $orgCo->id(), 'owner', 'approved', TRUE, TRUE);

    $event = Event::create([
      'title' => 'Grande Encontro Cultural Parceria',
      'field_status' => 'publicado',
      'field_organizacao' => (int) $orgPrincipal->id(),
      'field_coorganizadores' => [(int) $orgCo->id()],
    ]);
    $event->save();

    // Editor da coorganizadora ganha acesso para editar o evento.
    $this->assertTrue($event->access('update', $this->anotherEditorUser));

    // Mas a coorganização NÃO concede capacidade de publicação do evento.
    $this->assertFalse($accessService->canPublishEvent($event, $this->anotherEditorUser));
    $this->assertTrue($accessService->canPublishEvent($event, $this->editorUser));

    // Ao remover a coorganizadora, o acesso herdado é revogado imediatamente.
    $event->set('field_coorganizadores', []);
    $event->save();
    $this->container->get('entity_type.manager')->getAccessControlHandler('aculta_event')->resetCache();

    $this->assertFalse($event->access('update', $this->anotherEditorUser));
    $this->assertTrue($event->access('update', $this->editorUser));
  }

  /**
   * Valida integração com denúncias e moderação UGC.
   */
  public function testUgcReportIntegration(): void {
    /** @var \Drupal\aculta_ugc\Service\ReportService $reportService */
    $reportService = $this->container->get('aculta_ugc.report_service');

    $org = EventOrganization::create([
      'title' => 'Organização para Auditoria',
      'field_slug' => 'organizacao-auditoria',
      'reivindicada' => FALSE,
      'field_status' => 'publicado',
      'status' => 1,
    ]);
    $org->save();

    $this->assertTrue($reportService->canReport($org, $this->commonUser));
    $report = $reportService->createReport(
      $org,
      $this->commonUser,
      'conteudo_inadequado'
    );

    $this->assertInstanceOf(UgcReport::class, $report);
    $this->assertSame('aculta_event_organization', $report->get('target_type')->value);
    $this->assertSame((int) $org->id(), (int) $report->get('target_id')->value);

    // O perfil fica acessível publicamente durante análise administrativa.
    $this->assertTrue($org->access('view', $this->commonUser));
  }

  /**
   * Valida projeção do índice: ID canônico, ocultação de cidade/UF e sem CEP.
   */
  public function testContentIndexOrganizationProjectionAndPrivacy(): void {
    $eventProvider = $this->container->get('aculta_events.index_provider.event');
    $orgProvider = $this->container->get('aculta_events.index_provider.organization');

    $this->assertSame(4, $eventProvider->getProjectionVersion());
    $this->assertSame(1, $orgProvider->getProjectionVersion());

    $org = EventOrganization::create([
      'title' => 'Centro Cultural Protegido',
      'field_slug' => 'centro-cultural-protegido',
      'localidade' => 'Anápolis',
      'uf' => 'GO',
      'ocultar_localidade' => TRUE,
      'field_status' => 'publicado',
      'status' => 1,
      'titular' => (int) $this->editorUser->id(),
    ]);
    $org->save();

    $orgProj = $orgProvider->project($org);
    $this->assertNotNull($orgProj);
    $this->assertSame('aculta_event_organization', $orgProj->entityTypeId);
    $this->assertSame((string) $org->id(), $orgProj->entityId);
    $this->assertSame('Centro Cultural Protegido', $orgProj->label);
    $this->assertSame((int) $this->editorUser->id(), $orgProj->ownerUid);

    // Ocultar localidade marcado: nenhuma relação de localidade é projetada.
    $ufRelations = array_filter($orgProj->relations, static fn ($r) => $r->type === 'location_uf');
    $this->assertEmpty($ufRelations);

    // Quando não oculto, a UF entra na projeção.
    $org->set('ocultar_localidade', FALSE);
    $org->save();
    $orgProjVisible = $orgProvider->project($org);
    $this->assertNotNull($orgProjVisible);
    $ufRelationsVisible = array_filter($orgProjVisible->relations, static fn ($r) => $r->type === 'location_uf');
    $this->assertCount(1, $ufRelationsVisible);

    // Projeção do Evento associado: relação organizer com ID canônico da org.
    $event = Event::create([
      'title' => 'Espetáculo de Dança Contemporânea',
      'field_status' => 'publicado',
      'field_organizacao' => (int) $org->id(),
      'field_organizador' => (int) $this->editorUser->id(),
    ]);
    $event->save();

    $eventProj = $eventProvider->project($event);
    $this->assertNotNull($eventProj);
    $orgRelations = array_filter($eventProj->relations, static fn ($r) => $r->type === 'organizer');
    $this->assertCount(1, $orgRelations);
    $orgRel = reset($orgRelations);
    $this->assertSame('aculta_event_organization', $orgRel->targetEntityTypeId);
    $this->assertSame((string) $org->id(), $orgRel->targetEntityId);

    // Garante que nenhuma relação vazou o UID do usuário como organizer.
    $leakedUserRelations = array_filter($eventProj->relations, static fn ($r) => $r->targetEntityTypeId === 'user');
    $this->assertEmpty($leakedUserRelations);
  }

  /**
   * Valida rejeição de reivindicação e resolução de pedidos concorrentes.
   */
  public function testClaimRejectionAndConcurrentClaimsResolution(): void {
    /** @var \Drupal\aculta_user\Organization\OwnershipClaimService $claimService */
    $claimService = $this->container->get('aculta_user.ownership_claim_service');

    // Caso 1: Rejeição de pedido único restaura status para nao_reivindicada.
    $org1 = EventOrganization::create([
      'title' => 'Galpão Cultural Solitário',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'pendente',
      'field_status' => 'publicado',
      'status' => 1,
    ]);
    $org1->save();

    $claimSingle = $claimService->submitClaim(
      $org1,
      $this->editorUser,
      'Tentativa de reivindicação com documento incompleto.'
    );
    $this->assertSame('pending', (string) $claimSingle->get('status')->value);

    // Administrador rejeita o pedido.
    $claimService->decideClaim($claimSingle, $this->adminUser, FALSE, 'Documentação comprobatória insuficiente.');

    $reloadedOrg1 = EventOrganization::load($org1->id());
    $this->assertNotNull($reloadedOrg1);
    $this->assertFalse($reloadedOrg1->isClaimed());
    $this->assertSame('nao_reivindicada', $reloadedOrg1->getClaimStatus(), 'Status restaurado para nao_reivindicada.');
    $this->assertSame('rejected', (string) $claimSingle->get('status')->value);

    // Caso 2: Resolução de pedidos concorrentes.
    $org2 = EventOrganization::create([
      'title' => 'Associação em Disputa Fraterna',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'pendente',
      'field_status' => 'publicado',
      'status' => 1,
    ]);
    $org2->save();

    $claimA = $claimService->submitClaim($org2, $this->editorUser, 'Sou o diretor eleito.');
    $claimB = $claimService->submitClaim($org2, $this->anotherEditorUser, 'Sou o coordenador fundador.');

    $this->assertSame('pending', (string) $claimA->get('status')->value);
    $this->assertSame('pending', (string) $claimB->get('status')->value);

    // Administrador aprova o pedido A.
    $claimService->decideClaim($claimA, $this->adminUser, TRUE, 'Ata oficial de eleição apresentada.');

    // Pedido A foi aprovado.
    $this->assertSame('approved', (string) $claimA->get('status')->value);

    // Pedido B concorrente foi automaticamente rejeitado/encerrado.
    $reloadedClaimB = $this->container->get('entity_type.manager')
      ->getStorage('aculta_ownership_claim')
      ->load($claimB->id());
    $this->assertNotNull($reloadedClaimB);
    $this->assertSame('rejected', (string) $reloadedClaimB->get('status')->value);
    $this->assertStringContainsString('Pedido concorrente encerrado', (string) $reloadedClaimB->get('decision_notes')->value);

    // Organização foi atribuída ao titular A.
    $reloadedOrg2 = EventOrganization::load($org2->id());
    $this->assertNotNull($reloadedOrg2);
    $this->assertTrue($reloadedOrg2->isClaimed());
    $this->assertSame((int) $this->editorUser->id(), $reloadedOrg2->getOwnerId());
  }

}
