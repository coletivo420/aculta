<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Controller\EventLocationContributionAdminController;
use Drupal\aculta_events\Controller\EventContributionAdminController;
use Drupal\aculta_events\Controller\EventContributionWorkspaceController;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Controller\EventOrganizationSuggestionAdminController;
use Drupal\aculta_events\Form\EventOrganizationContributionReviewForm;
use Drupal\aculta_events\Form\EventContributionRevisionForm;
use Drupal\aculta_events\Form\EventContributionSubmissionForm;
use Drupal\aculta_events\Form\EventUpdateContributionSubmissionForm;
use Drupal\aculta_events\Form\EventLocationModalForm;
use Drupal\aculta_contributions\Entity\Contribution;
use Drupal\aculta_image\FileRemover;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_ugc\Entity\UgcReport;
use Drupal\aculta_user\Entity\OwnershipClaim;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\DomainInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
    'aculta_contributions',
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
   * Whether the active test purpose is Events for all accounts.
   */
  private bool $eventsPurposeForAllAccounts = FALSE;

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
    $this->installEntitySchema('aculta_event_org_suggestion');
    $this->installEntitySchema('aculta_contribution');
    $this->installConfig(['system', 'user', 'taxonomy', 'domain', 'aculta_contributions']);

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

    // Usuários autorizados podem sugerir alterações, mas não salvar o perfil
    // público diretamente.
    $this->assertTrue($org->access('suggest', $this->editorUser));
    $this->assertTrue($org->access('suggest', $this->anotherEditorUser));
    $this->assertFalse($org->access('update', $this->editorUser));

    // Usuário comum sem permissão de criar eventos não pode editar.
    $this->assertFalse($org->access('update', $this->commonUser));
    $this->assertFalse($org->access('suggest', $this->commonUser));
  }

  /**
   * Verifies unclaimed organization edits remain pending until approved.
   */
  public function testUnclaimedOrganizationChangesRequireAdministrativeApproval(): void {
    $organization = EventOrganization::create([
      'title' => 'Coletivo Original',
      'field_slug' => 'coletivo-original',
      'body' => 'Descrição original.',
      'localidade' => 'Recife',
      'uf' => 'PE',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'nao_reivindicada',
      'field_status' => 'publicado',
      'status' => 1,
    ]);
    $organization->save();

    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_organization');
    $baseOrganization = $this->container->get('entity_type.manager')
      ->getStorage('aculta_event_organization')
      ->loadUnchanged($organization->id());
    $baseVersion = $adapter->currentVersion('organization_update', $baseOrganization);
    $form = $this->container->get('entity_type.manager')->getFormObject('aculta_event_organization', 'edit');
    $form->setEntity($organization);
    $organization->set('title', 'Coletivo Proposto');
    $organization->set('body', 'Descrição proposta.');
    $organization->set('reivindicada', TRUE);
    $formState = new FormState();
    $formState->setValue('contribution_rationale', 'O nome e a descrição precisam refletir o perfil atual.');
    $formState->setValue('contribution_submission_token', str_repeat('e', 64));
    $formState->setValue('contribution_base_version', $baseVersion);
    $form->save([], $formState);

    $this->container->get('entity_type.manager')->getStorage('aculta_event_organization')->resetCache();
    $unchanged = EventOrganization::load($organization->id());
    $this->assertSame('Coletivo Original', $unchanged->label());
    $this->assertSame('Descrição original.', $unchanged->get('body')->value);
    $this->assertFalse($unchanged->isClaimed());

    $contributions = $this->container->get('entity_type.manager')->getStorage('aculta_contribution');
    $ids = $contributions->getQuery()->accessCheck(FALSE)
      ->condition('source', 'events_organization')
      ->condition('intent', 'organization_update')
      ->execute();
    $this->assertCount(1, $ids);
    $proposal = $contributions->load(reset($ids));
    $this->assertInstanceOf(Contribution::class, $proposal);
    $this->assertSame('submitted', $proposal->get('state')->value);
    $this->assertTrue($proposal->access('view', $this->editorUser));
    $this->assertFalse($proposal->access('view', $this->commonUser));
    $this->assertTrue($proposal->access('view', $this->adminUser));
    $this->assertArrayNotHasKey('reivindicada', $proposal->getPayload());
    $this->container->get('current_user')->setAccount($this->adminUser);
    $controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventOrganizationSuggestionAdminController::class);
    $queue = $controller->overview();
    $this->assertCount(1, $queue['table']['#rows']);
    $reviewForm = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventOrganizationContributionReviewForm::class);
    $review = $reviewForm->buildForm([], new FormState(), $proposal);
    $this->assertArrayHasKey('changes', $review);
    $this->assertArrayHasKey('accepted', $review['decision']['#options']);
    $this->container->get('aculta_contributions.lifecycle')->decide(
      $proposal,
      'accepted',
      'Dados conferidos.',
      ['title', 'body'],
      (int) $proposal->getRevisionId(),
    );
    $updated = EventOrganization::load($organization->id());
    $this->assertSame('Coletivo Proposto', $updated->label());
    $this->assertSame('Descrição proposta.', $updated->get('body')->value);
    $this->assertFalse($updated->isClaimed());
    $contributions->resetCache();
    $decided = $contributions->loadUnchanged($proposal->id());
    $this->assertSame('accepted', $decided->get('state')->value);
    $queueItem = $this->container->get('queue')->get('aculta_events_contribution_events')->claimItem();
    $this->assertNotFalse($queueItem);
    $this->assertSame([
      'schema_version',
      'event',
      'source',
      'source_uuid',
      'source_revision_id',
      'recipient_uid',
      'outcome',
    ], array_keys($queueItem->data));
    $this->assertSame('accepted', $queueItem->data['outcome']);
    $this->assertArrayNotHasKey('rationale', $queueItem->data);
    $this->assertArrayNotHasKey('payload', $queueItem->data);
  }

  /**
   * A contribution keeps profile edits private until selected approval. */
  public function testOrganizationContributionAppliesOnlySelectedFields(): void {
    $organization = EventOrganization::create([
      'title' => 'Perfil original',
      'field_slug' => 'perfil-original',
      'body' => 'Texto original.',
      'localidade' => 'Goiânia',
      'uf' => 'GO',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'nao_reivindicada',
      'field_status' => 'publicado',
      'status' => 1,
    ]);
    $organization->save();

    $this->usePurposeByCurrentAccount();

    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_organization');
    $freshOrganization = $this->container->get('entity_type.manager')
      ->getStorage('aculta_event_organization')
      ->loadUnchanged($organization->id());
    try {
      $adapter->validatePayload('organization_update', ['reivindicada' => TRUE], $freshOrganization);
      self::fail('Ownership must not be an applicable contribution field.');
    }
    catch (UnprocessableEntityHttpException) {
      self::assertTrue(TRUE);
    }
    $payload = $adapter->validatePayload('organization_update', [
      'title' => 'Nome proposto',
      'body' => 'Texto proposto.',
    ], $freshOrganization);
    self::assertSame(['title' => 'Nome proposto', 'body' => 'Texto proposto.'], $payload);

    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'events_organization',
      'organization_update',
      (string) $organization->uuid(),
      'Atualização do perfil',
      'O perfil precisa refletir o nome e a apresentação atuais.',
      $payload,
      str_repeat('c', 64),
      [],
      $adapter->currentVersion('organization_update', $freshOrganization),
    );
    $this->container->get('current_user')->setAccount($this->commonUser);
    self::assertFalse($proposal->access('view', $this->commonUser));
    $unchanged = $this->container->get('entity_type.manager')->getStorage('aculta_event_organization')->loadUnchanged($organization->id());
    self::assertSame('Perfil original', $unchanged->label());
    self::assertSame('Texto original.', $unchanged->get('body')->value);

    $this->container->get('current_user')->setAccount($this->editorUser);
    $workspace = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventContributionWorkspaceController::class);
    self::assertCount(1, $workspace->overview()['table']['#rows']);
    self::assertSame('Em análise', $workspace->view((string) $proposal->uuid())['state']['#markup']);

    $this->container->get('current_user')->setAccount($this->adminUser);
    $decided = $lifecycle->decide($proposal, 'accepted', 'Dados confirmados.', ['body'], (int) $proposal->getRevisionId());
    self::assertSame('accepted', $decided->get('state')->value);
    $applied = $this->container->get('entity_type.manager')->getStorage('aculta_event_organization')->loadUnchanged($organization->id());
    self::assertSame('Perfil original', $applied->label());
    self::assertSame('Texto proposto.', $applied->get('body')->value);

    $staleOrganization = EventOrganization::create([
      'title' => 'Perfil sujeito a conflito',
      'field_slug' => 'perfil-conflito',
      'body' => 'Texto de base.',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'nao_reivindicada',
      'field_status' => 'publicado',
      'status' => 1,
    ]);
    $staleOrganization->save();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $staleTarget = $this->container->get('entity_type.manager')
      ->getStorage('aculta_event_organization')
      ->loadUnchanged($staleOrganization->id());
    $staleProposal = $lifecycle->submit(
      'events_organization',
      'organization_update',
      (string) $staleTarget->uuid(),
      'Correção do perfil',
      'O texto pode ser atualizado.',
      ['body' => 'Texto proposto após revisão.'],
      str_repeat('d', 64),
      [],
      $adapter->currentVersion('organization_update', $staleTarget),
    );
    $staleTarget->set('body', 'Alteração independente.')->save();
    $this->container->get('current_user')->setAccount($this->adminUser);
    try {
      $lifecycle->decide($staleProposal, 'accepted', 'Revisão concluída.', ['body'], (int) $staleProposal->getRevisionId());
      self::fail('A aplicação deve detectar a alteração da origem.');
    }
    catch (ConflictHttpException) {
      $conflicted = $this->container->get('entity_type.manager')
        ->getStorage('aculta_contribution')
        ->load($staleProposal->id());
      self::assertSame('conflict', $conflicted->get('state')->value);
    }
    $staleReloaded = $this->container->get('entity_type.manager')
      ->getStorage('aculta_event_organization')
      ->loadUnchanged($staleOrganization->id());
    self::assertSame('Alteração independente.', $staleReloaded->get('body')->value);
  }

  /**
   * Claimed organizations stay outside the community contribution adapter.
   */
  public function testContributionCannotSubmitOrApplyToClaimedOrganization(): void {
    $organization = EventOrganization::create([
      'title' => 'Organização reivindicada',
      'field_slug' => 'organizacao-reivindicada',
      'body' => 'Perfil sob titularidade.',
      'reivindicada' => TRUE,
      'reivindicacao_status' => 'confirmada',
      'titular' => $this->anotherEditorUser->id(),
      'field_status' => 'publicado',
      'status' => 1,
    ]);
    $organization->save();

    $this->usePurposeByCurrentAccount();
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_organization');
    $this->container->get('current_user')->setAccount($this->editorUser);
    self::assertFalse($adapter->submitAccess($this->editorUser, 'organization_update', $organization));

    $this->container->get('current_user')->setAccount($this->adminUser);
    self::assertFalse($adapter->applyAccess($this->adminUser, 'organization_update', $organization));
    self::assertSame((int) $this->anotherEditorUser->id(), (int) $organization->get('titular')->target_id);
  }

  /**
   * A new organization is absent until its proposal is accepted.
   */
  public function testOrganizationCreateContributionWaitsForApproval(): void {
    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_organization');
    $payload = $adapter->validatePayload('organization_create', [
      'title' => 'Novo Coletivo Proposto',
      'body' => 'Apresentação proposta.',
      'localidade' => 'Natal',
      'uf' => 'RN',
    ]);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'events_organization',
      'organization_create',
      NULL,
      'Criação da organização Novo Coletivo Proposto',
      'O coletivo precisa de um perfil público para ser selecionado em Eventos.',
      $payload,
      str_repeat('f', 64),
    );
    self::assertNull($proposal->get('target_uuid')->value);
    self::assertSame([], $this->container->get('entity_type.manager')->getStorage('aculta_event_organization')
      ->loadByProperties(['title' => 'Novo Coletivo Proposto']));
    self::assertFalse($proposal->access('view', $this->commonUser));

    $workspace = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventContributionWorkspaceController::class);
    self::assertCount(1, $workspace->overview()['table']['#rows']);
    self::assertSame('Em análise', $workspace->view((string) $proposal->uuid())['state']['#markup']);

    $this->container->get('current_user')->setAccount($this->adminUser);
    $decided = $lifecycle->decide(
      $proposal,
      'accepted',
      'Cadastro conferido.',
      ['title', 'body'],
      (int) $proposal->getRevisionId(),
    );
    self::assertSame('accepted', $decided->get('state')->value);
    $created = $this->container->get('entity_type.manager')->getStorage('aculta_event_organization')
      ->loadByProperties(['title' => 'Novo Coletivo Proposto']);
    self::assertCount(1, $created);
    $organization = reset($created);
    self::assertTrue($organization->isUnclaimed());
    self::assertSame('nao_reivindicada', $organization->getClaimStatus());
    self::assertSame('Apresentação proposta.', $organization->get('body')->value);
  }

  /**
   * A Location remains private until review and is created as a draft. */
  public function testLocationCreateContributionWaitsForApproval(): void {
    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_location');
    $address = [[
      'country_code' => 'BR',
      'address_line1' => 'Rua das Flores, 120',
      'address_line2' => 'Sala 4',
      'dependent_locality' => 'Centro',
      'locality' => 'Natal',
      'administrative_area' => 'RN',
      'postal_code' => '59000-000',
    ],
    ];
    $payload = $adapter->validatePayload('location_create', [
      'title' => 'Espaço Cultural Proposto',
      'field_endereco' => $address,
      'field_precisao' => 'aproximado',
      'field_descricao' => 'Espaço comunitário de cultura.',
    ]);
    try {
      $adapter->validatePayload('location_create', ['field_endereco' => [['country_code' => 'US']]]);
      self::fail('Non-Brazilian addresses must be rejected server-side.');
    }
    catch (UnprocessableEntityHttpException) {
      self::assertTrue(TRUE);
    }
    try {
      $adapter->validatePayload('location_create', ['field_status' => 'publicado']);
      self::fail('Proponents cannot submit publication status.');
    }
    catch (UnprocessableEntityHttpException) {
      self::assertTrue(TRUE);
    }

    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'events_location',
      'location_create',
      NULL,
      'Criação do Local Espaço Cultural Proposto',
      'Este espaço precisa entrar no catálogo para organizar atividades.',
      $payload,
      str_repeat('9', 64),
    );
    self::assertSame([], $this->container->get('entity_type.manager')->getStorage('aculta_event_location')
      ->loadByProperties(['title' => 'Espaço Cultural Proposto']));
    self::assertFalse($proposal->access('view', $this->commonUser));

    $workspace = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventContributionWorkspaceController::class);
    self::assertCount(1, $workspace->overview()['table']['#rows']);
    self::assertSame('Em análise', $workspace->view((string) $proposal->uuid())['state']['#markup']);

    $this->container->get('current_user')->setAccount($this->adminUser);
    $decided = $lifecycle->decide(
      $proposal,
      'accepted',
      'Endereço revisado; seguir como rascunho.',
      ['title', 'field_endereco', 'field_precisao', 'field_descricao'],
      (int) $proposal->getRevisionId(),
    );
    self::assertSame('accepted', $decided->get('state')->value);
    $locations = $this->container->get('entity_type.manager')->getStorage('aculta_event_location')
      ->loadByProperties(['title' => 'Espaço Cultural Proposto']);
    self::assertCount(1, $locations);
    $location = reset($locations);
    self::assertInstanceOf(EventLocation::class, $location);
    self::assertSame('rascunho', $location->get('field_status')->value);
    self::assertSame((int) $this->editorUser->id(), (int) $location->getOwnerId());
    self::assertSame('BR', $location->get('field_endereco')->country_code);
    self::assertSame('aproximado', $location->get('field_precisao')->value);
    self::assertFalse($location->access('view', $this->commonUser));
    self::assertNull($this->container->get('aculta_index.provider_registry')->findForBundle('aculta_event_location', 'aculta_event_location')->project($location));

    $this->container->get('current_user')->setAccount($this->editorUser);
    $duplicateProposal = $lifecycle->submit(
      'events_location',
      'location_create',
      NULL,
      'Criação duplicada do Local Espaço Cultural Proposto',
      'Confirmo o cadastro do espaço.',
      $payload,
      str_repeat('6', 64),
    );
    $this->container->get('current_user')->setAccount($this->adminUser);
    try {
      $lifecycle->decide(
        $duplicateProposal,
        'accepted',
        'O Local já existe.',
        ['title', 'field_endereco'],
        (int) $duplicateProposal->getRevisionId(),
      );
      self::fail('Duplicate locations must be rejected during application.');
    }
    catch (ConflictHttpException) {
      self::assertSame('submitted', $this->container->get('entity_type.manager')
        ->getStorage('aculta_contribution')->loadUnchanged($duplicateProposal->id())->get('state')->value);
    }
    self::assertCount(1, $this->container->get('entity_type.manager')->getStorage('aculta_event_location')
      ->loadByProperties(['title' => 'Espaço Cultural Proposto']));
  }

  /**
   * Event creation proposals stay private and acceptance creates a draft.
   */
  public function testEventCreateContributionWaitsForApprovalAndCreatesDates(): void {
    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    $payload = $adapter->validatePayload('event_create', [
      'title' => 'Mostra Comunitária Proposta',
      'body' => 'Descrição pública proposta.',
      'dates' => [[
        'title' => 'Abertura',
        'starts_at' => '2027-01-10T18:00:00-03:00',
        'ends_at' => '2027-01-10T20:00:00-03:00',
        'location_uuid' => '',
      ],
      ],
    ]);
    try {
      $adapter->validatePayload('event_create', [
        'title' => 'Tentativa de publicação',
        'field_status' => 'publicado',
        'dates' => [[
          'starts_at' => '2027-01-10T18:00:00Z',
          'ends_at' => '2027-01-10T20:00:00Z',
        ],
        ],
      ]);
      self::fail('Publication status must not be accepted from a proposal.');
    }
    catch (UnprocessableEntityHttpException) {
      self::assertTrue(TRUE);
    }

    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'events_event',
      'event_create',
      NULL,
      'Criação da Mostra Comunitária',
      'A comunidade propõe esta atividade cultural.',
      $payload,
      str_repeat('a', 64),
    );
    self::assertSame([], $this->container->get('entity_type.manager')->getStorage('aculta_event')
      ->loadByProperties(['title' => 'Mostra Comunitária Proposta']));
    self::assertFalse($proposal->access('view', $this->commonUser));

    $workspace = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventContributionWorkspaceController::class);
    $overview = $workspace->overview();
    self::assertCount(1, $overview['table']['#rows']);
    $status = $workspace->view((string) $proposal->uuid());
    self::assertSame('Em análise', $status['state']['#markup']);
    $this->container->get('current_user')->setAccount($this->commonUser);
    try {
      $workspace->view((string) $proposal->uuid());
      self::fail('Another account must not read a private Event proposal.');
    }
    catch (NotFoundHttpException) {
      self::assertTrue(TRUE);
    }

    $this->container->get('current_user')->setAccount($this->adminUser);
    $queueController = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventContributionAdminController::class);
    $queue = $queueController->overview();
    self::assertCount(1, $queue['table']['#rows']);
    self::assertSame('Criação da Mostra Comunitária', $queue['table']['#rows'][0]['event']['data']['#plain_text']);
    $reviewForm = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventOrganizationContributionReviewForm::class);
    $review = $reviewForm->buildForm([], new FormState(), $proposal);
    self::assertArrayHasKey('changes', $review);
    self::assertSame(['title', 'body', 'dates'], array_keys($review['selected_fields']['#options']));
    $decided = $lifecycle->decide(
      $proposal,
      'accepted',
      'Datas revisadas e aprovadas.',
      ['title', 'body', 'dates'],
      (int) $proposal->getRevisionId(),
    );
    self::assertSame('accepted', $decided->get('state')->value);
    $outcome = $this->container->get('queue')->get('aculta_events_contribution_events')->claimItem();
    self::assertNotFalse($outcome);
    self::assertSame('events_event', $outcome->data['source']);
    self::assertSame('accepted', $outcome->data['outcome']);
    self::assertSame((int) $proposal->getOwnerId(), $outcome->data['recipient_uid']);
    self::assertArrayNotHasKey('payload', $outcome->data);
    self::assertArrayNotHasKey('rationale', $outcome->data);
    $events = $this->container->get('entity_type.manager')->getStorage('aculta_event')
      ->loadByProperties(['title' => 'Mostra Comunitária Proposta']);
    self::assertCount(1, $events);
    $event = reset($events);
    self::assertSame('rascunho', $event->get('field_status')->value);
    self::assertSame((int) $this->editorUser->id(), (int) $event->getOwnerId());
    $dates = $this->container->get('entity_type.manager')->getStorage('aculta_event_date')
      ->loadByProperties(['field_evento' => $event->id()]);
    self::assertCount(1, $dates);
    self::assertSame(
      (new \DateTimeImmutable('2027-01-10T21:00:00Z'))->getTimestamp(),
      $dates[array_key_first($dates)]->get('field_quando')->start_date->getTimestamp(),
    );
  }

  /**
   * Event creation conflicts with an existing title/date case-insensitively.
   */
  public function testEventCreateContributionRejectsDuplicateTitleAndDate(): void {
    $event = Event::create([
      'title' => 'Mostra Cultural Existente',
      'field_status' => 'publicado',
      'field_organizador' => (int) $this->adminUser->id(),
    ]);
    $event->save();
    EventDate::create([
      'title' => 'Abertura',
      'field_evento' => (int) $event->id(),
      'field_quando' => [
        'value' => '2027-05-10T18:00:00',
        'end_value' => '2027-05-10T20:00:00',
      ],
    ])->save();

    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    $payload = $adapter->validatePayload('event_create', [
      'title' => 'mostra cultural existente',
      'dates' => [
        [
          'title' => 'Abertura proposta',
          'starts_at' => '2027-05-10T18:00:00Z',
          'ends_at' => '2027-05-10T20:00:00Z',
        ],
      ],
    ]);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'events_event',
      'event_create',
      NULL,
      'Proposta duplicada de Evento',
      'Este Evento já existe.',
      $payload,
      str_repeat('d', 64),
    );

    $this->container->get('current_user')->setAccount($this->adminUser);
    try {
      $lifecycle->decide(
        $proposal,
        'accepted',
        'Conflita com um Evento existente.',
        ['title', 'dates'],
        (int) $proposal->getRevisionId(),
      );
      self::fail('An existing Event with the same title and date must conflict.');
    }
    catch (ConflictHttpException) {
      $reloaded = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')
        ->loadUnchanged($proposal->id());
      self::assertSame('submitted', $reloaded->get('state')->value);
    }

    self::assertCount(1, $this->container->get('entity_type.manager')->getStorage('aculta_event')
      ->loadByProperties(['title' => 'Mostra Cultural Existente']));
  }

  /**
   * Only the author can resubmit an Events proposal returned for changes.
   */
  public function testEventContributionAuthorCanResubmitAfterRequestedChanges(): void {
    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    $payload = $adapter->validatePayload('event_create', [
      'title' => 'Evento com ajustes',
      'dates' => [[
        'title' => 'Abertura',
        'starts_at' => '2027-02-10T18:00:00Z',
        'ends_at' => '2027-02-10T20:00:00Z',
        'location_uuid' => '',
      ],
      ],
    ]);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'events_event',
      'event_create',
      NULL,
      'Criação do Evento com ajustes',
      'A proposta descreve a atividade cultural.',
      $payload,
      str_repeat('e', 64),
    );
    self::assertFalse($proposal->access('update', $this->editorUser));

    $this->container->get('current_user')->setAccount($this->adminUser);
    $returned = $lifecycle->decide(
      $proposal,
      'needs_changes',
      'Informe o horário de encerramento correto.',
      [],
      (int) $proposal->getRevisionId(),
    );
    $this->container->get('current_user')->setAccount($this->editorUser);
    self::assertTrue($returned->access('update', $this->editorUser));

    $formObject = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventContributionRevisionForm::class);
    $formState = new FormState();
    $form = $formObject->buildForm([], $formState, (string) $proposal->uuid());
    self::assertArrayHasKey('review_feedback', $form);
    self::assertArrayHasKey('dates', $form['payload']);
    $formState->setValue('revision_id', (int) $returned->getRevisionId());
    $formState->setValue('subject', 'Evento com ajustes revisados');
    $formState->setValue('rationale', 'Horário de encerramento corrigido conforme pedido.');
    $formState->setValue('payload', [
      'title' => 'Evento com horário corrigido',
      'dates' => [[
        'title' => 'Abertura',
        'starts_at' => '2027-02-10T18:00:00Z',
        'ends_at' => '2027-02-10T21:00:00Z',
        'location_uuid' => '',
      ],
      ],
    ]);
    $formObject->validateForm($form, $formState);
    self::assertFalse($formState->hasAnyErrors());
    $formObject->submitForm($form, $formState);

    $revised = $this->container->get('entity_type.manager')
      ->getStorage('aculta_contribution')->loadUnchanged($proposal->id());
    self::assertSame('submitted', $revised->get('state')->value);
    self::assertSame('Evento com horário corrigido', $revised->getPayload()['title']);
    self::assertSame('2027-02-10T21:00:00', $revised->getPayload()['dates'][0]['ends_at']);
    self::assertGreaterThan((int) $returned->getRevisionId(), (int) $revised->getRevisionId());
    self::assertFalse($revised->access('update', $this->editorUser));

    $this->container->get('current_user')->setAccount($this->commonUser);
    try {
      $formObject->buildForm([], new FormState(), (string) $proposal->uuid());
      self::fail('Another account must not open the revision form.');
    }
    catch (NotFoundHttpException) {
      self::assertTrue(TRUE);
    }
  }

  /**
   * A returned Location proposal rebuilds its address with Brazil fixed.
   */
  public function testLocationContributionRevisionUsesCentralBrazilAddress(): void {
    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $proposal = $this->container->get('entity_type.manager')
      ->getStorage('aculta_contribution')->create([
        'uid' => (int) $this->editorUser->id(),
        'subject' => 'Ajustar endereço do Local',
        'source' => 'events_location',
        'source_module' => 'aculta_events',
        'purpose' => 'eventos',
        'intent' => 'location_create',
        'base_revision' => str_repeat('a', 64),
        'payload' => [
          'title' => 'Local em revisão',
          'field_tipo' => [],
          'field_endereco' => [[
            'country_code' => 'BR',
            'address_line1' => 'Rua da Cultura 120',
            'address_line2' => 'Sala 2',
            'postal_code' => '01000-000',
            'locality' => 'São Paulo',
            'administrative_area' => 'SP',
            'dependent_locality' => 'Centro',
          ],
          ],
          'field_precisao' => 'aproximado',
        ],
        'rationale' => 'Corrigir endereço informado.',
        'state' => 'needs_changes',
      ]);
    $proposal->setNewRevision(TRUE);
    $proposal->isDefaultRevision(TRUE);
    $proposal->save();

    $formObject = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventContributionRevisionForm::class);
    $form = $formObject->buildForm([], new FormState(), (string) $proposal->uuid());

    self::assertSame('fieldset', $form['payload']['field_endereco']['#type']);
    self::assertSame('address', $form['payload']['field_endereco'][0]['#type']);
    self::assertSame('BR', $form['payload']['field_endereco'][0]['#default_value']['country_code']);
    self::assertSame(['BR'], $form['payload']['field_endereco'][0]['#available_countries']);
    self::assertArrayHasKey('field_endereco', $form['payload']);
  }

  /**
   * Event proposal submission stores only a private Contribution and no Event.
   */
  public function testEventContributionSubmissionDoesNotCreateOfficialEvent(): void {
    $this->usePurposeByCurrentAccount();
    $organization = EventOrganization::create([
      'title' => 'Organização da proposta',
      'field_slug' => 'organizacao-da-proposta',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'nao_reivindicada',
      'field_status' => 'publicado',
      'status' => 1,
    ]);
    $organization->save();
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    try {
      $adapter->validatePayload('event_create', [
        'title' => 'Proposta com organização duplicada',
        'field_organizacao' => $organization->uuid(),
        'field_coorganizadores' => [$organization->uuid()],
        'dates' => [
          [
            'starts_at' => '2027-05-10T18:00:00Z',
            'ends_at' => '2027-05-10T20:00:00Z',
          ],
        ],
      ]);
      self::fail('The primary organization cannot also be a co-organizer.');
    }
    catch (UnprocessableEntityHttpException $exception) {
      self::assertStringContainsString('simultaneamente', $exception->getMessage());
    }
    $this->container->get('current_user')->setAccount($this->commonUser);
    try {
      $this->container->get('class_resolver')
        ->getInstanceFromDefinition(EventContributionSubmissionForm::class)
        ->buildForm([], new FormState());
      self::fail('An account without Event create capability must not open proposal submission.');
    }
    catch (AccessDeniedHttpException) {
      self::assertTrue(TRUE);
    }
    $this->container->get('current_user')->setAccount($this->editorUser);
    $formObject = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventContributionSubmissionForm::class);
    $formState = new FormState();
    $form = $formObject->buildForm([], $formState);
    self::assertArrayHasKey('dates', $form['payload']);
    self::assertArrayHasKey('rationale', $form);
    $formState->setValue('payload', [
      'title' => 'Mostra proposta pela comunidade',
      'field_subtitulo' => 'Edição de outono',
      'body' => 'Descrição inicial do evento.',
      'field_url' => '',
      'field_organizacao' => '',
      'field_coorganizadores' => [],
      'dates' => [[
        'title' => 'Abertura',
        'starts_at' => '2027-05-10T18:00:00-03:00',
        'ends_at' => '2027-05-10T20:00:00-03:00',
        'location_uuid' => '',
      ],
      ],
    ]);
    $formState->setValue('rationale', 'A atividade amplia a programação cultural local.');
    $formState->setValue('submission_token', $form['submission_token']['#value']);
    $formObject->validateForm($form, $formState);
    self::assertFalse($formState->hasAnyErrors());
    $formObject->submitForm($form, $formState);

    $contributions = $this->container->get('entity_type.manager')
      ->getStorage('aculta_contribution')->loadByProperties([
        'uid' => (int) $this->editorUser->id(),
        'source' => 'events_event',
      ]);
    self::assertCount(1, $contributions);
    $proposal = reset($contributions);
    self::assertSame('submitted', $proposal->get('state')->value);
    self::assertSame('2027-05-10T21:00:00', $proposal->getPayload()['dates'][0]['starts_at']);
    $queueItem = $this->container->get('queue')
      ->get('aculta_events_contribution_events', TRUE)->claimItem();
    self::assertNotFalse($queueItem);
    self::assertSame([
      'schema_version',
      'event',
      'source',
      'source_uuid',
      'source_revision_id',
      'recipient_uid',
      'outcome',
    ], array_keys($queueItem->data));
    self::assertSame('submitted', $queueItem->data['outcome']);
    $this->container->get('queue')->get('aculta_events_contribution_events', TRUE)->deleteItem($queueItem);
    self::assertCount(0, $this->container->get('entity_type.manager')
      ->getStorage('aculta_event')->loadByProperties(['title' => 'Mostra proposta pela comunidade']));
  }

  /**
   * Event updates remain private proposals until editorial approval.
   */
  public function testEventUpdateContributionSubmissionDoesNotChangeEvent(): void {
    $this->usePurposeByCurrentAccount(TRUE);
    $event = Event::create([
      'title' => 'Evento publicado original',
      'body' => 'Descrição oficial original.',
      'field_organizador' => (int) $this->adminUser->id(),
      'field_status' => 'publicado',
    ]);
    $event->save();

    $this->container->get('current_user')->setAccount($this->commonUser);
    try {
      $this->container->get('class_resolver')
        ->getInstanceFromDefinition(EventUpdateContributionSubmissionForm::class)
        ->buildForm([], new FormState(), $event);
      self::fail('An account without Event update access must not open an update proposal.');
    }
    catch (NotFoundHttpException) {
      self::assertTrue(TRUE);
    }

    $this->container->get('current_user')->setAccount($this->adminUser);
    $formObject = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventUpdateContributionSubmissionForm::class);
    $formState = new FormState();
    $form = $formObject->buildForm([], $formState, $event);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    $freshTarget = $adapter->loadTarget('event_update', (string) $event->uuid());
    self::assertSame(
      $formState->get('expected_version'),
      $adapter->currentVersion('event_update', $freshTarget),
    );
    $formState->setValue('payload', [
      'title' => 'Evento publicado original',
      'field_subtitulo' => '',
      'body' => 'Descrição proposta pela comunidade.',
      'field_url' => '',
      'field_organizacao' => '',
      'field_coorganizadores' => [],
      'date' => ['title' => '', 'starts_at' => '', 'ends_at' => '', 'location_uuid' => ''],
    ]);
    $formState->setValue('rationale', 'A descrição precisa informar a programação cultural.');
    $formState->setValue('submission_token', $form['submission_token']['#value']);
    $formObject->validateForm($form, $formState);
    self::assertFalse($formState->hasAnyErrors());
    $formObject->submitForm($form, $formState);
    self::assertFalse($formState->hasAnyErrors(), var_export($formState->getErrors(), TRUE));

    $unchanged = $this->container->get('entity_type.manager')->getStorage('aculta_event')->loadUnchanged($event->id());
    self::assertSame('Descrição oficial original.', $unchanged->get('body')->value);
    $proposals = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')
      ->loadByProperties(['source' => 'events_event', 'intent' => 'event_update', 'target_uuid' => $event->uuid()]);
    self::assertCount(1, $proposals);
    $proposal = reset($proposals);
    self::assertSame('submitted', $proposal->get('state')->value);
    self::assertSame('Descrição proposta pela comunidade.', $proposal->getPayload()['body']);
    self::assertSame($event->uuid(), $proposal->get('target_uuid')->value);
    $queueItem = $this->container->get('queue')
      ->get('aculta_events_contribution_events', TRUE)->claimItem();
    self::assertNotFalse($queueItem);
    self::assertSame('submitted', $queueItem->data['outcome']);
    $this->container->get('queue')->get('aculta_events_contribution_events', TRUE)->deleteItem($queueItem);
  }

  /**
   * Event creation is denied if the author's configured capability is revoked.
   */
  public function testEventCreateContributionRevalidatesAuthorCapability(): void {
    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    $payload = $adapter->validatePayload('event_create', [
      'title' => 'Mostra sem autorização atual',
      'dates' => [[
        'starts_at' => '2027-03-10T18:00:00Z',
        'ends_at' => '2027-03-10T20:00:00Z',
        'location_uuid' => '',
      ],
      ],
    ]);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'events_event',
      'event_create',
      NULL,
      'Criação de Evento',
      'Evento cujo papel será revogado.',
      $payload,
      str_repeat('d', 64),
    );

    $this->editorUser->removeRole('content_editor');
    $this->editorUser->save();
    $this->container->get('current_user')->setAccount($this->adminUser);
    try {
      $lifecycle->decide($proposal, 'accepted', 'A autorização do autor foi revogada.', ['title', 'dates'], (int) $proposal->getRevisionId());
      self::fail('Revoked create capability must prevent Event creation.');
    }
    catch (ConflictHttpException) {
      $reloaded = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')->load($proposal->id());
    }

    self::assertSame('submitted', $reloaded->get('state')->value);
    self::assertCount(0, $this->container->get('entity_type.manager')->getStorage('aculta_event')
      ->loadByProperties(['title' => 'Mostra sem autorização atual']));
  }

  /**
   * An Event update applies only selected fields and appends Dates.
   */
  public function testEventUpdateContributionAppliesSelectedFieldsAndAppendsDates(): void {
    $this->usePurposeByCurrentAccount(TRUE);
    $this->container->get('current_user')->setAccount($this->adminUser);
    $event = Event::create([
      'title' => 'Evento original',
      'body' => 'Descrição oficial.',
      'field_organizador' => $this->adminUser->id(),
      'field_status' => 'publicado',
    ]);
    $event->save();
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    $payload = $adapter->validatePayload('event_update', [
      'title' => 'Novo título proposto',
      'body' => 'Nova descrição proposta.',
      'dates' => [[
        'starts_at' => '2027-02-10T18:00:00Z',
        'ends_at' => '2027-02-10T20:00:00Z',
        'location_uuid' => '',
      ],
      ],
    ], $event);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'events_event',
      'event_update',
      (string) $event->uuid(),
      'Atualização do Evento original',
      'A descrição e a próxima data foram revisadas.',
      $payload,
      str_repeat('b', 64),
    );

    $this->usePurposeByCurrentAccount();
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    self::assertTrue($adapter->reviewAccess($this->container->get('current_user'), 'event_update', $event));
    $accepted = $lifecycle->decide(
      $proposal,
      'accepted',
      'Título conferido; descrição e data aceitas.',
      ['body', 'dates'],
      (int) $proposal->getRevisionId(),
    );
    self::assertSame('accepted', $accepted->get('state')->value);
    $reloaded = $this->container->get('entity_type.manager')->getStorage('aculta_event')->loadUnchanged($event->id());
    self::assertSame('Evento original', $reloaded->label(), 'Unselected title is not applied.');
    self::assertSame('Nova descrição proposta.', $reloaded->get('body')->value);
    self::assertSame('publicado', $reloaded->get('field_status')->value, 'The adapter does not alter publication state.');
    self::assertCount(1, $this->container->get('entity_type.manager')->getStorage('aculta_event_date')
      ->loadByProperties(['field_evento' => $event->id()]));
  }

  /**
   * Event update is denied when the author's update access is revoked.
   */
  public function testEventUpdateContributionRevalidatesAuthorAccess(): void {
    $this->usePurposeByCurrentAccount(TRUE);
    $this->container->get('current_user')->setAccount($this->editorUser);
    $event = Event::create([
      'title' => 'Evento de acesso revogado',
      'field_organizador' => (int) $this->editorUser->id(),
      'field_status' => 'publicado',
    ]);
    $event->save();
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    $payload = $adapter->validatePayload('event_update', ['body' => 'Texto proposto.'], $event);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'events_event',
      'event_update',
      (string) $event->uuid(),
      'Atualização de Evento',
      'Acesso será revogado antes da decisão.',
      $payload,
      str_repeat('e', 64),
    );
    $this->editorUser->removeRole('content_editor');
    $this->editorUser->save();
    $this->container->get('current_user')->setAccount($this->adminUser);
    $this->usePurposeByCurrentAccount();
    try {
      $lifecycle->decide($proposal, 'accepted', 'Acesso não está mais válido.', ['body'], (int) $proposal->getRevisionId());
      self::fail('Revoked update access must prevent Event changes.');
    }
    catch (ConflictHttpException) {
      $reloaded = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')->load($proposal->id());
    }

    self::assertSame('submitted', $reloaded->get('state')->value);
    $unchanged = $this->container->get('entity_type.manager')->getStorage('aculta_event')->loadUnchanged($event->id());
    self::assertSame('', (string) $unchanged->get('body')->value);
  }

  /**
   * Event update conflicts if author loses access to its claimed Organization.
   */
  public function testEventUpdateContributionRevalidatesOrganizationMembership(): void {
    $this->usePurposeByCurrentAccount(TRUE);
    $memberManager = $this->container->get('aculta_user.organization_membership_manager');
    $organization = EventOrganization::create([
      'title' => 'Organização de acesso revogado',
      'reivindicada' => TRUE,
      'titular' => (int) $this->anotherEditorUser->id(),
      'status' => 1,
    ]);
    $organization->save();
    $member = $memberManager->addMembership($this->editorUser, (string) $organization->id(), 'editor', 'approved');
    $event = Event::create([
      'title' => 'Evento com Organização autorizada',
      'field_organizador' => (int) $this->editorUser->id(),
      'field_organizacao' => (int) $organization->id(),
      'field_status' => 'publicado',
    ]);
    $event->save();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    $payload = $adapter->validatePayload('event_update', ['body' => 'Texto proposto.'], $event);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit('events_event', 'event_update', (string) $event->uuid(), 'Atualização do Evento', 'Atualizar descrição.', $payload, str_repeat('f', 64));

    $memberManager->revokeMembership($member, $this->adminUser, 'Revogação para o teste.');
    $this->container->get('current_user')->setAccount($this->adminUser);
    $this->usePurposeByCurrentAccount();
    try {
      $lifecycle->decide($proposal, 'accepted', 'Autorização da relação revogada.', ['body'], (int) $proposal->getRevisionId());
      self::fail('A perda de vínculo com a Organização deve impedir a aplicação.');
    }
    catch (ConflictHttpException) {
      self::assertSame('submitted', $this->container->get('entity_type.manager')->getStorage('aculta_contribution')
        ->loadUnchanged($proposal->id())->get('state')->value);
    }
    self::assertSame('', (string) $this->container->get('entity_type.manager')->getStorage('aculta_event')
      ->loadUnchanged($event->id())->get('body')->value);
  }

  /**
   * Event update conflicts if a proposed Location becomes private.
   */
  public function testEventUpdateContributionRevalidatesDateLocationAccess(): void {
    $this->usePurposeByCurrentAccount(TRUE);
    $location = EventLocation::create([
      'title' => 'Local que ficará privado',
      'field_organizador' => (int) $this->anotherEditorUser->id(),
      'field_status' => 'publicado',
    ]);
    $location->save();
    $event = Event::create([
      'title' => 'Evento com nova Data',
      'field_organizador' => (int) $this->editorUser->id(),
      'field_status' => 'publicado',
    ]);
    $event->save();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    $payload = $adapter->validatePayload('event_update', [
      'dates' => [[
        'title' => 'Data com Local',
        'starts_at' => '2027-04-10T18:00:00Z',
        'ends_at' => '2027-04-10T20:00:00Z',
        'location_uuid' => (string) $location->uuid(),
      ],
      ],
    ], $event);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit('events_event', 'event_update', (string) $event->uuid(), 'Adicionar Data', 'Usar o Local público.', $payload, str_repeat('1', 64));

    $location->set('field_status', 'privado')->save();
    $this->container->get('entity_type.manager')->getAccessControlHandler('aculta_event_location')->resetCache();
    self::assertFalse($location->access('view', $this->editorUser));
    $this->container->get('current_user')->setAccount($this->adminUser);
    $this->usePurposeByCurrentAccount();
    try {
      $lifecycle->decide($proposal, 'accepted', 'Local deixou de ser público.', ['dates'], (int) $proposal->getRevisionId());
      self::fail('O Local privado não pode ser associado à Data na aplicação.');
    }
    catch (ConflictHttpException) {
      self::assertSame('submitted', $this->container->get('entity_type.manager')->getStorage('aculta_contribution')
        ->loadUnchanged($proposal->id())->get('state')->value);
    }
    self::assertCount(0, $this->container->get('entity_type.manager')->getStorage('aculta_event_date')
      ->loadByProperties(['field_evento' => $event->id()]));
  }

  /**
   * Updating the Event after submission produces a conflict, not an overwrite.
   */
  public function testEventUpdateContributionDetectsChangedSource(): void {
    $this->usePurposeByCurrentAccount(TRUE);
    $this->container->get('current_user')->setAccount($this->adminUser);
    $event = Event::create([
      'title' => 'Evento alterado em paralelo',
      'body' => 'Versão inicial.',
      'field_organizador' => $this->adminUser->id(),
      'field_status' => 'publicado',
    ]);
    $event->save();
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_event');
    $payload = $adapter->validatePayload('event_update', ['body' => 'Texto da proposta.'], $event);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'events_event',
      'event_update',
      (string) $event->uuid(),
      'Atualização de Evento',
      'O texto proposto precisa de revisão.',
      $payload,
      str_repeat('c', 64),
    );

    $event->set('body', 'Alteração independente mais recente.');
    $event->save();
    $this->eventsPurposeForAllAccounts = FALSE;
    try {
      $lifecycle->decide(
        $proposal,
        'accepted',
        'O conteúdo foi revisado, mas a origem mudou.',
        ['body'],
        (int) $proposal->getRevisionId(),
      );
      self::fail('A changed source must report a conflict.');
    }
    catch (ConflictHttpException) {
      $accepted = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')->load($proposal->id());
    }
    self::assertSame('conflict', $accepted->get('state')->value);
    $reloaded = $this->container->get('entity_type.manager')->getStorage('aculta_event')->loadUnchanged($event->id());
    self::assertSame('Alteração independente mais recente.', $reloaded->get('body')->value);
  }

  /**
   * A Location update proposal detects stale origin before applying fields. */
  public function testLocationUpdateContributionDetectsConflicts(): void {
    $location = EventLocation::create([
      'title' => 'Local de Teste',
      'field_organizador' => (int) $this->editorUser->id(),
      'field_status' => 'publicado',
      'field_endereco' => [[
        'country_code' => 'BR',
        'address_line1' => 'Rua A, 10',
        'locality' => 'Natal',
        'administrative_area' => 'RN',
        'postal_code' => '59000-000',
      ],
      ],
    ]);
    $location->save();
    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_location');
    $fresh = $this->container->get('entity_type.manager')->getStorage('aculta_event_location')->loadUnchanged($location->id());
    $version = $adapter->currentVersion('location_update', $fresh);
    $proposal = $this->container->get('aculta_contributions.lifecycle')->submit(
      'events_location',
      'location_update',
      (string) $location->uuid(),
      'Ajuste de endereço: Local de Teste',
      'Ajustar o número do endereço do local.',
      [
        'field_endereco' => [[
          'country_code' => 'BR',
          'address_line1' => 'Rua A, 12',
          'locality' => 'Natal',
          'administrative_area' => 'RN',
          'postal_code' => '59000-000',
        ],
        ],
      ],
      str_repeat('8', 64),
      [],
      $version,
    );
    $location->set('field_descricao', 'Alteração administrativa concorrente.')->save();
    $this->container->get('current_user')->setAccount($this->adminUser);
    try {
      $this->container->get('aculta_contributions.lifecycle')->decide(
        $proposal,
        'accepted',
        'Revisado.',
        ['field_endereco'],
        (int) $proposal->getRevisionId(),
      );
      self::fail('A alteração concorrente deve produzir conflito.');
    }
    catch (ConflictHttpException) {
      $freshProposal = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')
        ->load($proposal->id());
      self::assertSame('conflict', $freshProposal->get('state')->value);
    }
    $unchanged = $this->container->get('entity_type.manager')->getStorage('aculta_event_location')->loadUnchanged($location->id());
    self::assertSame('Rua A, 10', $unchanged->get('field_endereco')->address_line1);
    self::assertSame('Alteração administrativa concorrente.', $unchanged->get('field_descricao')->value);
  }

  /**
   * A linked Event change invalidates a sensitive Location address proposal.
   */
  public function testLocationAddressContributionDetectsChangedLinkedEvent(): void {
    $location = EventLocation::create([
      'title' => 'Local com Evento vinculado',
      'field_organizador' => (int) $this->editorUser->id(),
      'field_status' => 'publicado',
      'field_endereco' => [[
        'country_code' => 'BR',
        'address_line1' => 'Rua A, 10',
        'locality' => 'Natal',
        'administrative_area' => 'RN',
        'postal_code' => '59000-000',
      ],
      ],
    ]);
    $location->save();
    $event = Event::create([
      'title' => 'Evento no Local',
      'field_organizador' => (int) $this->adminUser->id(),
      'field_status' => 'publicado',
    ]);
    $event->save();
    $date = EventDate::create([
      'title' => 'Data no Local',
      'field_evento' => (int) $event->id(),
      'field_local' => (int) $location->id(),
      'field_quando' => [
        'value' => '2027-05-10T18:00:00',
        'end_value' => '2027-05-10T20:00:00',
      ],
    ]);
    $date->save();

    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $adapter = $this->container->get('aculta_contributions.adapter_registry')->get('events_location');
    $freshLocation = $this->container->get('entity_type.manager')->getStorage('aculta_event_location')
      ->loadUnchanged($location->id());
    $proposal = $this->container->get('aculta_contributions.lifecycle')->submit(
      'events_location',
      'location_update',
      (string) $location->uuid(),
      'Ajuste de endereço: Local com Evento vinculado',
      'O endereço precisa de revisão.',
      [
        'field_endereco' => [[
          'country_code' => 'BR',
          'address_line1' => 'Rua A, 12',
          'locality' => 'Natal',
          'administrative_area' => 'RN',
          'postal_code' => '59000-000',
        ],
        ],
      ],
      str_repeat('9', 64),
      [],
      $adapter->currentVersion('location_update', $freshLocation),
    );

    $date->set('field_quando', [
      'value' => '2027-05-11T18:00:00',
      'end_value' => '2027-05-11T20:00:00',
    ])->save();
    $this->container->get('current_user')->setAccount($this->adminUser);
    try {
      $this->container->get('aculta_contributions.lifecycle')->decide(
        $proposal,
        'accepted',
        'Endereço revisado.',
        ['field_endereco'],
        (int) $proposal->getRevisionId(),
      );
      self::fail('A mudança na Data do Evento vinculado deve produzir conflito.');
    }
    catch (ConflictHttpException) {
      $freshProposal = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')
        ->load($proposal->id());
      self::assertSame('conflict', $freshProposal->get('state')->value);
    }
    $unchanged = $this->container->get('entity_type.manager')->getStorage('aculta_event_location')
      ->loadUnchanged($location->id());
    self::assertSame('Rua A, 10', $unchanged->get('field_endereco')->address_line1);
  }

  /**
   * A modal Local proposal creates no entity or Event reference before review. */
  public function testLocationModalSubmissionRemainsPrivate(): void {
    $this->usePurposeByCurrentAccount();
    $this->container->get('current_user')->setAccount($this->editorUser);
    $formObject = EventLocationModalForm::create($this->container);
    $formState = new FormState();
    $formState->set('mode', 'create');
    $formState->setTriggeringElement(['#name' => 'op_create_save']);
    $form = $formObject->buildForm([], $formState);
    $formState->setValue('novo_nome', 'Modal de Local Proposto');
    $formState->setValue(['field_endereco', 0], [
      'country_code' => 'BR',
      'address_line1' => 'Avenida do Sol, 50',
      'locality' => 'Natal',
      'administrative_area' => 'RN',
      'postal_code' => '59000-000',
    ]);
    $formState->setValue('field_precisao', 'aproximado');
    $formState->setValue('contribution_rationale', 'O local precisa ser encontrado por organizadores.');
    $formState->setValue('contribution_submission_token', str_repeat('7', 64));
    $formObject->validateForm($form, $formState);
    self::assertSame([], $formState->getErrors());
    $formObject->submitCreateLocation($form, $formState);
    self::assertTrue($formState->get('proposal_submitted'));
    self::assertNull($formState->get('selected_location_id'));
    self::assertSame([], $this->container->get('entity_type.manager')->getStorage('aculta_event_location')
      ->loadByProperties(['title' => 'Modal de Local Proposto']));
    $proposals = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')
      ->loadByProperties(['intent' => 'location_create']);
    self::assertCount(1, $proposals);
    $proposal = reset($proposals);
    self::assertSame('submitted', $proposal->get('state')->value);
    self::assertSame('O local precisa ser encontrado por organizadores.', $proposal->get('rationale')->value);

    $this->container->get('current_user')->setAccount($this->adminUser);
    $controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventLocationContributionAdminController::class);
    $queue = $controller->overview();
    self::assertCount(1, $queue['table']['#rows']);
    $reviewForm = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventOrganizationContributionReviewForm::class);
    $reviewState = new FormState();
    $review = $reviewForm->buildForm([], $reviewState, $proposal);
    self::assertArrayHasKey('changes', $review);
    self::assertArrayHasKey('accepted', $review['decision']['#options']);
    $addressRow = array_values(array_filter($review['changes']['#rows'], static fn(array $row): bool => $row['field']['#plain_text'] === 'Endereço e CEP (Brasil)'))[0];
    self::assertSame('Endereço privado — revele somente se necessário', $addressRow['proposed']['#plain_text']);
    $reviewState->setValue('reveal_private_address', TRUE);
    $revealedReview = $reviewForm->buildForm([], $reviewState, $proposal);
    $revealedAddressRow = array_values(array_filter($revealedReview['changes']['#rows'], static fn(array $row): bool => $row['field']['#plain_text'] === 'Endereço e CEP (Brasil)'))[0];
    self::assertStringContainsString('Avenida do Sol, 50', $revealedAddressRow['proposed']['#plain_text']);
  }

  /**
   * A stale proposal is not applied if the organization changed in the queue.
   */
  public function testStaleOrganizationSuggestionCannotOverwriteNewerData(): void {
    $organization = EventOrganization::create([
      'title' => 'Coletivo Estável',
      'field_slug' => 'coletivo-estavel',
      'body' => 'Versão inicial.',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'nao_reivindicada',
      'status' => 1,
    ]);
    $organization->save();
    $service = $this->container->get('aculta_events.organization_suggestion');
    $suggestion = $service->submit($organization, $this->editorUser, [
      'body' => 'Proposta atrasada.',
      'reivindicada' => TRUE,
    ]);

    $organization->set('body', 'Atualização administrativa.');
    $organization->save();

    $this->container->get('entity_type.manager')->getStorage('aculta_event_organization')->resetCache();
    $this->assertFalse($service->approve($suggestion, $this->adminUser));
    $updated = EventOrganization::load($organization->id());
    $this->assertSame('Atualização administrativa.', $updated->get('body')->value);
    $this->assertFalse($updated->isClaimed());
    $suggestionStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_org_suggestion');
    $suggestionStorage->resetCache();
    $suggestion = $suggestionStorage->loadUnchanged($suggestion->id());
    $this->assertSame('stale', $suggestion->get('field_status')->value);
  }

  /**
   * A user without event creation access cannot submit an organization edit.
   */
  public function testOrganizationSuggestionRequiresCreateEventAccess(): void {
    $organization = EventOrganization::create([
      'title' => 'Coletivo Restrito',
      'field_slug' => 'coletivo-restrito',
      'reivindicada' => FALSE,
      'reivindicacao_status' => 'nao_reivindicada',
      'status' => 1,
    ]);
    $organization->save();
    $service = $this->container->get('aculta_events.organization_suggestion');

    $this->expectException(\RuntimeException::class);
    $service->submit($organization, $this->commonUser, ['title' => 'Tentativa negada']);
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

    // Titulares podem editar diretamente organizações reivindicadas.
    $this->container->get('current_user')->setAccount($this->editorUser);
    $organizationForm = $this->container->get('entity_type.manager')->getFormObject('aculta_event_organization', 'edit');
    $organizationForm->setEntity($org);
    $org->set('title', 'Fundação Cultural Atualizada');
    $organizationForm->save([], new FormState());
    $this->assertSame('Fundação Cultural Atualizada', EventOrganization::load($org->id())->label());

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

  /**
   * Supplies MAIN/Events purpose from the active test account. */
  private function usePurposeByCurrentAccount(bool $eventsForAllAccounts = FALSE): void {
    $this->eventsPurposeForAllAccounts = $eventsForAllAccounts;
    $mainDomain = $this->createMock(DomainInterface::class);
    $mainDomain->method('id')->willReturn('aculta_org');
    $eventsDomain = $this->createMock(DomainInterface::class);
    $eventsDomain->method('id')->willReturn('eventos_aculta_org');
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturnCallback(function () use ($mainDomain, $eventsDomain): DomainInterface {
      return $this->eventsPurposeForAllAccounts || (int) $this->container->get('current_user')->id() === (int) $this->editorUser->id()
        ? $eventsDomain
        : $mainDomain;
    });
    $this->container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

}
