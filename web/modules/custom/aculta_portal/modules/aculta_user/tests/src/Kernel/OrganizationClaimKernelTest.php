<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_user\Entity\OwnershipClaim;
use Drupal\aculta_user\Organization\ClaimNotAllowedException;
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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Valida contratos de vínculo de organização, reivindicação e escopo (ARQ-05).
 *
 * Cobre D-01 (content_editor com escopo), D-02 (moderação com padrão fechado)
 * e C-02/C-03 (padrão fechado e atuação em nome de organização).
 */
#[Group('aculta_user')]
#[RunTestsInSeparateProcesses]
final class OrganizationClaimKernelTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'entity',
    'profile',
    'path_alias',
    'field',
    'file',
    'image',
    'text',
    'options',
    'taxonomy',
    'datetime',
    'aculta_calendar',
    'aculta_events',
    'aculta_user',
  ];

  private User $adminUser;
  private User $editorUser;
  private User $commonUser;

  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_portal\\', dirname(__DIR__, 5) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_image\\', dirname(__DIR__, 5) . '/modules/aculta_image/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_user\\', dirname(__DIR__, 3) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_events\\', dirname(__DIR__, 5) . '/modules/aculta_events/src', TRUE);

    $container->register('aculta_portal.domain_purpose', DomainPurposeManager::class)
      ->addArgument($this->createMock(EntityTypeManagerInterface::class))
      ->addArgument($this->createMock(DomainNegotiatorInterface::class))
      ->addArgument($this->createMock(ConfigFactoryInterface::class))
      ->addArgument(new RequestStack());

    $container->register('aculta_image.render', \stdClass::class);
    $container->register('aculta_image.file_remover', \Drupal\aculta_image\FileRemover::class)
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
    $this->installEntitySchema('aculta_event');
    $this->installConfig(['system', 'user']);

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
      'id' => 'admin_claims',
      'label' => 'Administrador de Reivindicações',
    ]);
    $roleAdmin->grantPermission('administer aculta claims');
    $roleAdmin->grantPermission('administer aculta events');
    $roleAdmin->save();

    $rootUser = User::create(['name' => 'root', 'mail' => 'root@example.com', 'status' => 1]);
    $rootUser->save();

    $this->adminUser = User::create(['name' => 'admin_claims', 'mail' => 'admin@example.com', 'status' => 1]);
    $this->adminUser->addRole('admin_claims');
    $this->adminUser->save();

    $this->editorUser = User::create(['name' => 'editor_user', 'mail' => 'editor@example.com', 'status' => 1]);
    $this->editorUser->addRole('content_editor');
    $this->editorUser->save();

    $this->commonUser = User::create(['name' => 'common_user', 'mail' => 'common@example.com', 'status' => 1]);
    $this->commonUser->save();
  }

  public function testOwnershipClaimClosedByDefault(): void {
    /** @var \Drupal\aculta_user\Organization\OwnershipClaimService $claimService */
    $claimService = $this->container->get('aculta_user.ownership_claim_service');

    $event = Event::create([
      'title' => 'Evento Privado ou Fechado',
      'field_status' => 'publicado',
      'field_allow_claiming' => FALSE,
    ]);
    $event->save();

    $this->assertFalse($claimService->canClaim($event, $this->commonUser));

    $this->expectException(ClaimNotAllowedException::class);
    $claimService->submitClaim($event, $this->commonUser, 'Quero gerenciar este evento');
  }

  public function testOwnershipClaimLifecycleAndApproval(): void {
    /** @var \Drupal\aculta_user\Organization\OwnershipClaimService $claimService */
    $claimService = $this->container->get('aculta_user.ownership_claim_service');

    $originalOwner = $this->editorUser;

    $event = Event::create([
      'title' => 'Festival Cultural Comunitário',
      'field_status' => 'publicado',
      'field_allow_claiming' => TRUE,
      'field_organizador' => (int) $originalOwner->id(),
    ]);
    $event->save();

    $this->assertFalse($claimService->canClaim($event, $originalOwner));
    $this->assertTrue($claimService->canClaim($event, $this->commonUser));

    $claim = $claimService->submitClaim(
      $event,
      $this->commonUser,
      'Sou o novo produtor executivo do festival.',
      'contato@festival.org'
    );

    $this->assertInstanceOf(OwnershipClaim::class, $claim);
    $this->assertSame('pending', $claim->get('status')->value);
    $this->assertSame((int) $this->commonUser->id(), (int) $claim->get('uid')->target_id);

    $reloadedEvent = Event::load($event->id());
    $this->assertSame((int) $originalOwner->id(), (int) $reloadedEvent->get('field_organizador')->target_id);

    $this->assertFalse($claimService->canClaim($event, $this->commonUser));

    try {
      $claimService->decideClaim($claim, $this->commonUser, TRUE, 'Tentativa não autorizada');
      $this->fail('Deveria ter lançado AccessDeniedHttpException');
    }
    catch (AccessDeniedHttpException $e) {
      $this->assertTrue(TRUE);
    }

    $claimService->decideClaim($claim, $this->adminUser, TRUE, 'Documentação e posse comprovadas pela ata.');

    $this->assertSame('approved', $claim->get('status')->value);
    $this->assertSame((int) $this->adminUser->id(), (int) $claim->get('decided_by')->value);

    $reloadedEvent = Event::load($event->id());
    $this->assertSame((int) $this->commonUser->id(), (int) $reloadedEvent->get('field_organizador')->target_id);

    $this->expectException(\LogicException::class);
    $claimService->decideClaim($claim, $this->adminUser, FALSE, 'Tentativa de reabrir');
  }

  public function testOwnershipClaimRejection(): void {
    /** @var \Drupal\aculta_user\Organization\OwnershipClaimService $claimService */
    $claimService = $this->container->get('aculta_user.ownership_claim_service');

    $event = Event::create([
      'title' => 'Show Aberto',
      'field_status' => 'publicado',
      'field_allow_claiming' => TRUE,
      'field_organizador' => (int) $this->editorUser->id(),
    ]);
    $event->save();

    $claim = $claimService->submitClaim($event, $this->commonUser, 'Reivindicação sem documentos');

    $claimService->decideClaim($claim, $this->adminUser, FALSE, 'Comprovante não apresentado.');

    $this->assertSame('rejected', $claim->get('status')->value);
    $this->assertSame('Comprovante não apresentado.', $claim->get('decision_notes')->value);

    $reloadedEvent = Event::load($event->id());
    $this->assertSame((int) $this->editorUser->id(), (int) $reloadedEvent->get('field_organizador')->target_id);
  }

  public function testOrganizationMembershipAndBehalf(): void {
    /** @var \Drupal\aculta_user\Organization\OrganizationMembershipManager $memberManager */
    $memberManager = $this->container->get('aculta_user.organization_membership_manager');
    /** @var \Drupal\aculta_user\Organization\OrganizationAccessService $accessService */
    $accessService = $this->container->get('aculta_user.organization_access_service');

    $orgId = 'instituto-cultural-go';

    $this->assertFalse($accessService->canActOnBehalf($this->commonUser, $orgId));

    $member = $memberManager->addMembership($this->commonUser, $orgId, 'editor', 'pending');
    $this->assertFalse($accessService->canActOnBehalf($this->commonUser, $orgId));

    $memberManager->approveMembership($member, $this->adminUser, 'Vínculo estatutário comprovado.');
    $this->assertTrue($accessService->canActOnBehalf($this->commonUser, $orgId));

    $memberManager->revokeMembership($member, $this->adminUser, 'Término de mandato.');
    $this->assertFalse($accessService->canActOnBehalf($this->commonUser, $orgId));
  }

  public function testContentEditorScopedAccess(): void {
    $eventOwn = Event::create([
      'title' => 'Evento Próprio do Editor',
      'field_status' => 'publicado',
      'field_organizador' => (int) $this->editorUser->id(),
    ]);
    $eventOwn->save();

    $eventOther = Event::create([
      'title' => 'Evento de Outro Organizador',
      'field_status' => 'publicado',
      'field_organizador' => (int) $this->commonUser->id(),
    ]);
    $eventOther->save();

    $this->assertTrue($eventOwn->access('update', $this->editorUser));
    $this->assertFalse($eventOther->access('update', $this->editorUser));

    $this->assertTrue($eventOwn->access('update', $this->adminUser));
    $this->assertTrue($eventOther->access('update', $this->adminUser));

    $this->assertFalse($eventOwn->access('update', $this->commonUser));
    $this->assertFalse($eventOther->access('update', $this->commonUser));
  }

  public function testOwnershipClaimAdminControllerAndDecisionForm(): void {
    $event = Event::create([
      'title' => 'Festival Comunitário Reivindicável',
      'field_status' => 'publicado',
      'field_allow_claiming' => 1,
    ]);
    $event->save();

    /** @var \Drupal\aculta_user\Organization\OwnershipClaimService $claimService */
    $claimService = $this->container->get('aculta_user.ownership_claim_service');
    $claim = $claimService->submitClaim($event, $this->commonUser, 'Sou o produtor executivo do festival.');

    // 1. Controller overview.
    $controller = \Drupal\aculta_user\Controller\OwnershipClaimAdminController::create($this->container);
    $request = \Symfony\Component\HttpFoundation\Request::create('/painel-administrativo/pessoas/reivindicacoes');
    $build = $controller->overview($request);
    $this->assertArrayHasKey('table', $build);
    $this->assertCount(1, $build['table']['#rows']);
    $this->assertSame($claim->id(), $build['table']['#rows'][0]['id']);

    // 2. Decision form build & submission.
    $formObject = \Drupal\aculta_user\Form\OwnershipClaimDecisionForm::create($this->container);
    $formState = new \Drupal\Core\Form\FormState();
    $form = $formObject->buildForm([], $formState, $claim);
    $this->assertArrayHasKey('decision', $form);

    // Test form submit with approval.
    $formState->setValue('decision', 'approved');
    $formState->setValue('decision_notes', 'Aprovado com base no contrato social apresentado.');
    $this->container->get('current_user')->setAccount($this->adminUser);
    $formObject->submitForm($form, $formState);

    // Verify claim status and event organizer update.
    $reloadedClaim = OwnershipClaim::load($claim->id());
    $this->assertNotNull($reloadedClaim);
    $this->assertSame('approved', $reloadedClaim->get('status')->value);
    $this->assertSame((int) $this->adminUser->id(), (int) $reloadedClaim->get('decided_by')->value);

    $reloadedEvent = Event::load($event->id());
    $this->assertNotNull($reloadedEvent);
    $this->assertSame((int) $this->commonUser->id(), (int) $reloadedEvent->get('field_organizador')->target_id);
  }

}