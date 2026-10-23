<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Kernel;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\KeyValueStore\DatabaseStorageExpirable;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\profile\Entity\ProfileType;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeAccessRebuild;
use Drupal\node\NodeGrantsHelper;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_wiki\Upload\WikiLeadImageUploader;
use Drupal\aculta_wiki\Controller\WikiController;
use Drupal\aculta_wiki\Service\WikiProposalSubmission;
use Drupal\aculta_wiki\Service\WikiEditorialQueue;
use Drupal\aculta_wiki\Service\WikiProposalDecision;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\aculta_wiki\Form\WikiProposalDecisionForm;
use Drupal\aculta_wiki\Form\WikiProposalSubmissionForm;
use Drupal\aculta_wiki\Hook\WikiProposalLinkHooks;
use Drupal\aculta_wiki\Controller\WikiProposalStatusController;
use Drupal\aculta_wiki\Notifier\WikiProposalOutcomeNotifier;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests Wiki lead image publication and rollback with real entity storage.
 */
#[Group('aculta_wiki')]
#[RunTestsInSeparateProcesses]
final class WikiLeadImageLifecycleKernelTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * Temporary private scheme root for this isolated Kernel.
   */
  private string $privateDirectory;

  /**
   * {@inheritdoc}
   */
  protected function bootKernel(): void {
    // Portal consumes the shared account ledger when aculta_user is present.
    static::$modules = $this->resolveModuleDependencyClosure([
      'aculta_lms',
      'aculta_studio',
      'aculta_user',
      'aculta_wiki',
      'aculta_wiki_access_test',
    ]);
    parent::bootKernel();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->privateDirectory = sys_get_temp_dir() . '/aculta-wiki-private-' . bin2hex(random_bytes(6));
    parent::setUp();
    $this->setSetting('file_private_path', $this->privateDirectory);
    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('domain');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_wiki_proposal');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->container->get('database')->schema()->createTable(
      'key_value_expire',
      DatabaseStorageExpirable::schemaDefinition(),
    );
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'file', 'media', 'node', 'domain', 'filter']);

    foreach (['aculta_org' => 'main', 'wiki420_aculta_org' => 'wiki'] as $id => $purpose) {
      Domain::create([
        'id' => $id,
        'hostname' => $purpose . '.example.invalid',
        'name' => $purpose,
        'scheme' => 'https',
        'weight' => 0,
        'status' => TRUE,
      ])->save();
    }

    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
      'cardinality' => 1,
    ])->save();
    NodeType::create(['type' => 'wiki_entry', 'name' => 'Wiki entry'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => WikiLeadImageUploader::FIELD,
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'media'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => WikiLeadImageUploader::FIELD,
      'label' => 'Wiki lead image',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => ['target_bundles' => ['image' => 'image']],
      ],
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'body',
      'type' => 'text_with_summary',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'body',
      'label' => 'Body',
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_wiki_summary',
      'type' => 'text_long',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'field_wiki_summary',
      'label' => 'Summary',
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_wiki_test_grant_uid',
      'type' => 'integer',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'field_wiki_test_grant_uid',
      'label' => 'Test grant owner UID',
    ])->save();

    foreach (['anonymous', 'authenticated'] as $roleId) {
      $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load($roleId);
      $role->grantPermission('access content')->grantPermission('view media')->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', PrivateStream::class)
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->container->get('file_system')->deleteRecursive($this->privateDirectory);
    parent::tearDown();
  }

  /**
   * A lead image is public only when the submitted Wiki revision is published.
   *
   * @dataProvider publicationStates
   */
  public function testMediaAndPrivateFileFollowNodePublication(bool $published): void {
    $owner = $this->createOwner();
    $file = $this->createTemporaryPrivateImage($owner->id());
    $state = $this->uploadState((int) $file->id());
    $form = [];
    $uploader = $this->uploader();
    $uploader->applyUpload($form, $state);

    $mediaId = (int) $state->getValue([WikiLeadImageUploader::FIELD, 0, 'target_id']);
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Imagem com publicação controlada',
      'uid' => $owner->id(),
      'status' => $published,
      WikiLeadImageUploader::FIELD => ['target_id' => $mediaId],
    ]);
    $formObject = $this->entityForm($node, static fn () => $node->save());
    $state->setFormObject($formObject);

    $uploader->prepareNodeSave($form, $state);
    $uploader->saveNode($form, $state);

    $media = Media::load($mediaId);
    self::assertInstanceOf(Media::class, $media);
    self::assertSame($published, $media->isPublished());
    self::assertSame($published, $media->access('view', new AnonymousUserSession()));
    self::assertStringStartsWith('private://wiki/imagens/', $file->getFileUri());
    self::assertSame($published, $file->access('download', new AnonymousUserSession()));
  }

  /**
   * A failed Node save rolls back staged Media and File permanence together.
   */
  public function testFailedNodeSaveRollsBackMediaAndFileState(): void {
    $owner = $this->createOwner();
    $file = $this->createTemporaryPrivateImage($owner->id());
    $fileId = (int) $file->id();
    $state = $this->uploadState($fileId);
    $form = [];
    $uploader = $this->uploader();
    $uploader->applyUpload($form, $state);
    $mediaId = (int) $state->getValue([WikiLeadImageUploader::FIELD, 0, 'target_id']);
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Falha de salvamento',
      'uid' => $owner->id(),
      'status' => 0,
      WikiLeadImageUploader::FIELD => ['target_id' => $mediaId],
    ]);
    $formObject = $this->entityForm($node, static function () use ($node): void {
      $node->save();
      throw new \RuntimeException('Simulated failure after Node persistence.');
    });
    $state->setFormObject($formObject);
    $uploader->prepareNodeSave($form, $state);

    try {
      $uploader->saveNode($form, $state);
      self::fail('The simulated Node save failure should be rethrown.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('Simulated failure after Node persistence.', $exception->getMessage());
    }

    self::assertNull(Node::load((int) $node->id()));
    self::assertNull(Media::load($mediaId));
    $fileStorage = $this->container->get('entity_type.manager')->getStorage('file');
    $fileStorage->resetCache([$fileId]);
    self::assertTrue($fileStorage->load($fileId)->isTemporary());
  }

  /**
   * Removing an image preserves it while an older Node revision references it.
   */
  public function testRemovingImagePreservesMediaReferencedByOlderRevision(): void {
    $owner = $this->createOwner();
    $file = $this->createTemporaryPrivateImage($owner->id());
    $state = $this->uploadState((int) $file->id());
    $form = [];
    $uploader = $this->uploader();
    $uploader->applyUpload($form, $state);

    $mediaId = (int) $state->getValue([WikiLeadImageUploader::FIELD, 0, 'target_id']);
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Imagem preservada no histórico',
      'uid' => $owner->id(),
      'status' => TRUE,
      WikiLeadImageUploader::FIELD => ['target_id' => $mediaId],
    ]);
    $formObject = $this->entityForm($node, static fn () => $node->save());
    $state->setFormObject($formObject);
    $uploader->prepareNodeSave($form, $state);
    $uploader->saveNode($form, $state);
    $oldRevisionId = (int) $node->getRevisionId();

    $removalState = new FormState();
    $removalState->setValues([WikiLeadImageUploader::TAB => [WikiLeadImageUploader::REMOVE => TRUE]]);
    $uploader->applyUpload($form, $removalState);
    $node->setNewRevision(TRUE);
    $node->set(WikiLeadImageUploader::FIELD, $removalState->getValue([WikiLeadImageUploader::FIELD]));
    $node->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $oldRevision = $storage->loadRevision($oldRevisionId);
    $currentNode = $storage->load($node->id());
    self::assertSame($mediaId, (int) $oldRevision->get(WikiLeadImageUploader::FIELD)->target_id);
    self::assertTrue($currentNode->get(WikiLeadImageUploader::FIELD)->isEmpty());

    $media = Media::load($mediaId);
    self::assertInstanceOf(Media::class, $media);
    self::assertTrue($media->isPublished());
    self::assertTrue($file->access('download', new AnonymousUserSession()));
    self::assertFileExists($file->getFileUri());
  }

  /**
   * Public Wiki routes are discovered and enforce their permission contract.
   */
  public function testPublicRoutesAndListingViewAreDiscoveredWithAccess(): void {
    $routeProvider = $this->container->get('router.route_provider');
    $accessManager = $this->container->get('access_manager');
    $routePaths = [
      'aculta_portal.wiki_home' => '/wiki',
      'aculta_portal.wiki_search' => '/wiki/busca',
      'aculta_portal.wiki_search_preview' => '/wiki/busca/previa',
    ];
    foreach ($routePaths as $routeName => $path) {
      $route = $routeProvider->getRouteByName($routeName);
      self::assertSame($path, $route->getPath());
      self::assertSame('access content', $route->getRequirement('_permission'));
      self::assertTrue($accessManager->checkNamedRoute($routeName, [], new AnonymousUserSession()));
    }

    $proposalRoute = $routeProvider->getRouteByName('aculta_wiki.proposal_submit');
    self::assertSame('/wiki/verbetes/{node}/contribuir', $proposalRoute->getPath());
    self::assertSame('submit wiki proposals', $proposalRoute->getRequirement('_permission'));
    self::assertSame('node.view', $proposalRoute->getRequirement('_entity_access'));
    self::assertFalse($accessManager->checkNamedRoute('aculta_wiki.proposal_submit', ['node' => 1], new AnonymousUserSession()));
    $decisionRoute = $routeProvider->getRouteByName('aculta_wiki.proposal_decide');
    self::assertSame('/studio/ganjalab/wiki/propostas/{proposal}/decidir', $decisionRoute->getPath());
    self::assertSame('moderate wiki proposals', $decisionRoute->getRequirement('_permission'));
    self::assertSame('proposal.update', $decisionRoute->getRequirement('_entity_access'));

    $proposalTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Alvo do formulário de contribuição',
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'body' => ['value' => 'Texto público', 'format' => 'plain_text'],
    ]);
    $proposalTarget->save();
    Role::create([
      'id' => 'wiki_form_contributor_test',
      'label' => 'Wiki Form contributor test',
      'permissions' => ['submit wiki proposals'],
    ])->save();
    $contributor = new UserSession(['uid' => 7, 'roles' => ['authenticated', 'wiki_form_contributor_test']]);
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $contributor->id());
    $this->container->get('current_user')->setAccount($contributor);
    $proposalForm = WikiProposalSubmissionForm::create($this->container);
    $proposalBuild = $proposalForm->buildForm([], new FormState(), $proposalTarget);
    self::assertSame('aculta_wiki_proposal_submission', $proposalForm->getFormId());
    self::assertArrayHasKey('proposed_text', $proposalBuild);
    self::assertArrayHasKey('rationale', $proposalBuild);
    self::assertArrayHasKey('source_uris', $proposalBuild);

    Role::load('anonymous')->revokePermission('access content')->save();
    self::assertFalse($accessManager->checkNamedRoute(
      'aculta_portal.wiki_home',
      [],
      new AnonymousUserSession(),
    ));

    $viewFile = dirname(DRUPAL_ROOT) . '/config/sync/views.view.wiki_entries.yml';
    View::create(Yaml::parseFile($viewFile))->save();
    $this->container->get('router.builder')->rebuild();
    $listingRoute = $routeProvider->getRouteByName('view.wiki_entries.page_1');
    self::assertSame('/wiki/verbetes', $listingRoute->getPath());
  }

  /**
   * Home collaboration action follows live Node create access and its cache.
   */
  public function testHomeCollaborationUsesRuntimeNodeCreateAccess(): void {
    Role::create([
      'id' => 'wiki_home_creator_test',
      'label' => 'Wiki home creator test',
      'permissions' => ['create wiki_entry content'],
    ])->save();
    $creator = new UserSession(['uid' => 14, 'roles' => ['authenticated', 'wiki_home_creator_test']]);
    $reader = new UserSession(['uid' => 15, 'roles' => ['authenticated']]);
    $wikiDomain = Domain::load('wiki420_aculta_org');
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain($wikiDomain);
    $currentUser = $this->container->get('current_user');
    $controller = WikiController::create($this->container);

    self::assertTrue($creator->hasPermission('create wiki_entry content'));
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $creator->id());
    $currentUser->setAccount($creator);
    $creatorBuild = $controller->home();
    self::assertTrue($creatorBuild['collaboration']['#access']->isAllowed());
    self::assertContains('user.permissions', $creatorBuild['collaboration']['#access']->getCacheContexts());

    $currentUser->setAccount($reader);
    $readerBuild = $controller->home();
    self::assertFalse($readerBuild['collaboration']['#access']->isAllowed());
    self::assertContains('user.permissions', $readerBuild['collaboration']['#access']->getCacheContexts());
  }

  /**
   * Search filters results by grants between users with the same role.
   */
  public function testSearchSeparatesResultsForSameRoleUsersWithDifferentGrants(): void {
    // UID 1 bypasses normal access checks, so the actors use UIDs 2 and 3.
    $firstReader = new UserSession(['uid' => 2, 'roles' => ['authenticated']]);
    $secondReader = new UserSession(['uid' => 3, 'roles' => ['authenticated']]);
    self::assertGreaterThan(1, (int) $firstReader->id());
    self::assertGreaterThan(1, (int) $secondReader->id());
    $firstNode = $this->createSearchEntry('Cannabis exclusiva A', (int) $firstReader->id());
    $secondNode = $this->createSearchEntry('Cannabis exclusiva B', (int) $secondReader->id());
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    $this->container->get('node.view_all_nodes_memory_cache')->deleteAll();

    $wikiDomain = Domain::load('wiki420_aculta_org');
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain($wikiDomain);
    $currentUser = $this->container->get('current_user');
    $controller = WikiController::create($this->container);

    $currentUser->setAccount($firstReader);
    self::assertTrue($this->container->get('module_handler')->moduleExists('aculta_wiki_access_test'));
    self::assertTrue($this->container->get('module_handler')->hasImplementations('query_node_access_alter'));
    self::assertEquals(0, $this->container->get('entity_type.manager')->getAccessControlHandler('node')->checkAllGrants($firstReader));
    $grants = $this->container->get(NodeGrantsHelper::class)->nodeAccessGrants('view', $firstReader);
    self::assertSame([(string) $firstReader->id()], $grants['wiki_test_author']);
    self::assertSame('wiki', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    $firstEntity = Node::load($firstNode);
    $firstAccess = $firstEntity->access('view', $firstReader, TRUE);
    self::assertTrue($firstAccess->isAllowed(), 'The real Wiki entity must allow its author in the Wiki Domain.');
    $secondEntity = Node::load($secondNode);
    $secondAccess = $secondEntity->access('view', $firstReader, TRUE);
    self::assertFalse($secondAccess->isAllowed(), 'The other author’s Wiki entry must be denied.');
    $firstSearch = $controller->search(Request::create('/wiki/busca', 'GET', ['q' => 'Cannabis']));
    self::assertSame([$firstNode], array_map('intval', array_keys($firstSearch['results'])));

    $currentUser->setAccount($secondReader);
    $secondSearch = $controller->search(Request::create('/wiki/busca', 'GET', ['q' => 'Cannabis']));
    self::assertSame([$secondNode], array_map('intval', array_keys($secondSearch['results'])));

    $currentUser->setAccount($firstReader);
    $emptyBuild = $controller->search(Request::create('/wiki/busca'));
    self::assertArrayNotHasKey('results', $emptyBuild);
    self::assertContains('user.node_grants:view', $emptyBuild['#cache']['contexts']);
    $negativeBuild = $controller->search(Request::create('/wiki/busca', 'GET', ['q' => 'termo-inexistente']));
    self::assertArrayNotHasKey('results', $negativeBuild);
    self::assertContains('user.node_grants:view', $negativeBuild['#cache']['contexts']);
  }

  /**
   * Proposal submission is private, revision-based, and Wiki-purpose bound.
   */
  public function testProposalSubmissionUsesPrivateRevisionedEntityAndAccess(): void {
    Role::create([
      'id' => 'wiki_proposal_submitter_test',
      'label' => 'Wiki proposal submitter test',
      'permissions' => ['submit wiki proposals', 'view own wiki proposals'],
    ])->save();
    Role::create([
      'id' => 'wiki_proposal_reviewer_test',
      'label' => 'Wiki proposal reviewer test',
      'permissions' => ['view any wiki proposals', 'moderate wiki proposals'],
    ])->save();

    $submitter = new UserSession(['uid' => 2, 'roles' => ['authenticated', 'wiki_proposal_submitter_test']]);
    $other = new UserSession(['uid' => 3, 'roles' => ['authenticated', 'wiki_proposal_submitter_test']]);
    $unconfirmed = new UserSession(['uid' => 6, 'roles' => ['authenticated', 'wiki_proposal_submitter_test']]);
    ProfileType::create(['id' => 'authenticated', 'label' => 'Authenticated profile'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'profile',
      'bundle' => 'authenticated',
      'field_name' => 'field_nickname',
      'label' => 'Nickname',
    ])->save();
    User::create([
      'uid' => 2,
      'name' => 'wiki-proposal-author',
      'mail' => 'author@example.invalid',
      'status' => 1,
      'roles' => ['wiki_proposal_submitter_test'],
    ])->save();
    $target = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete público para proposta',
      'uid' => 3,
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_test_grant_uid' => 2,
      'body' => ['value' => 'Conteúdo publicado', 'format' => 'plain_text'],
    ]);
    $target->save();
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    $this->container->get('node.view_all_nodes_memory_cache')->deleteAll();
    $wikiDomain = Domain::load('wiki420_aculta_org');
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain($wikiDomain);
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $submitter->id());
    $this->container->get('current_user')->setAccount($submitter);

    $display = $this->createMock(EntityViewDisplayInterface::class);
    $linkHooks = $this->container->get(WikiProposalLinkHooks::class);
    self::assertInstanceOf(WikiProposalLinkHooks::class, $linkHooks);
    $proposalBuild = [];
    $linkHooks->entityView($proposalBuild, $target, $display, 'full');
    self::assertArrayHasKey('wiki_proposal_link', $proposalBuild);
    self::assertContains('user.permissions', $proposalBuild['#cache']['contexts']);

    $unconfirmedBuild = [];
    $this->container->get('current_user')->setAccount($unconfirmed);
    $linkHooks->entityView($unconfirmedBuild, $target, $display, 'full');
    self::assertArrayNotHasKey('wiki_proposal_link', $unconfirmedBuild);

    $this->container->get('current_user')->setAccount($submitter);

    $submission = $this->container->get('aculta_wiki.proposal_submission');
    self::assertInstanceOf(WikiProposalSubmission::class, $submission);
    $this->container->get('current_user')->setAccount($unconfirmed);
    try {
      $submission->submit($target, 'correction', 'Texto', 'Motivo');
      self::fail('An unconfirmed account with the submit permission must be denied.');
    }
    catch (AccessDeniedHttpException) {
      self::assertSame(0, $this->container->get('entity_type.manager')
        ->getStorage('aculta_wiki_proposal')->getQuery()->accessCheck(FALSE)->count()->execute());
    }
    $this->container->get('current_user')->setAccount($submitter);
    $staleTarget = clone $target;
    $target->setNewRevision(TRUE);
    $target->setRevisionLogMessage('Atualização concorrente para teste');
    $target->save();
    $this->container->get('node.view_all_nodes_memory_cache')->deleteAll();

    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain(Domain::load('aculta_org'));
    try {
      $submission->submit($staleTarget, 'reference', 'Texto', 'Justificativa');
      self::fail('Submission outside the Wiki purpose must be denied.');
    }
    catch (AccessDeniedHttpException) {
      self::assertTrue(TRUE);
    }
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain($wikiDomain);

    try {
      $submission->submit($staleTarget, 'reference', 'Texto', 'Justificativa', ['javascript:alert(1)']);
      self::fail('Non-HTTP source URLs must be rejected.');
    }
    catch (UnprocessableEntityHttpException) {
      self::assertSame(0, $this->container->get('entity_type.manager')
        ->getStorage('aculta_wiki_proposal')->getQuery()->accessCheck(FALSE)->count()->execute());
    }

    $proposal = $submission->submit(
      $staleTarget,
      'reference',
      'Dados do estudo revisado',
      'A referência atual não cobre este ponto.',
      ['https://example.invalid/pesquisa', 'https://example.invalid/dados'],
      str_repeat('a', 64),
    );
    $competitorKey = 'wiki_proposal_idempotency_competitor';
    $database = $this->container->get('database');
    Database::addConnectionInfo($competitorKey, 'default', $database->getConnectionOptions());
    $competitor = Database::getConnection('default', $competitorKey);
    $competitorLock = new DatabaseLockBackend($competitor);
    $contendedSubmission = new WikiProposalSubmission(
      $this->container->get('entity_type.manager'),
      $this->container->get('current_user'),
      $this->container->get('aculta_portal.domain_purpose'),
      $this->container->get('aculta_portal.email_confirmation_policy'),
      $this->container->get('flood'),
      new DatabaseLockBackend($database),
      $this->container->get('keyvalue.expirable'),
      $database,
    );
    $idempotencyLockName = 'aculta_wiki.proposal_submission:' . hash(
      'sha256',
      $submitter->id() . ':' . str_repeat('a', 64),
    );
    self::assertTrue($competitorLock->acquire($idempotencyLockName, 30.0));
    try {
      $contendedSubmission->submit(
        $staleTarget,
        'reference',
        'Mesmo token durante lock externo',
        'A segunda conexão não pode criar outro registro.',
        [],
        str_repeat('a', 64),
      );
      self::fail('A competing connection must prevent a duplicate submission.');
    }
    catch (TooManyRequestsHttpException) {
      self::assertSame(1, $this->container->get('entity_type.manager')
        ->getStorage('aculta_wiki_proposal')->getQuery()->accessCheck(FALSE)->count()->execute());
    }
    finally {
      $competitorLock->release($idempotencyLockName);
      Database::removeConnection($competitorKey);
    }
    $replayedProposal = $submission->submit(
      $staleTarget,
      'reference',
      'Payload duplicado ignorado',
      'A mesma chave de envio precisa retornar a proposta original.',
      [],
      str_repeat('a', 64),
    );
    self::assertSame((int) $proposal->id(), (int) $replayedProposal->id());
    self::assertSame('Dados do estudo revisado', $replayedProposal->get('proposed_text')->value);

    self::assertSame((int) $submitter->id(), (int) $proposal->getOwnerId());
    self::assertSame('submitted', $proposal->get('state')->value);
    self::assertSame('reference', $proposal->get('intent')->value);
    self::assertCount(2, $proposal->get('source_uri'));
    self::assertSame((int) $target->getRevisionId(), (int) $proposal->get('target_revision_id')->value);
    self::assertTrue($proposal->access('view', $submitter));
    self::assertFalse($proposal->access('view', $other));
    self::assertFalse($proposal->access('update', $submitter));
    self::assertTrue($proposal->access('view', new UserSession([
      'uid' => 4,
      'roles' => ['authenticated', 'wiki_proposal_reviewer_test'],
    ])));
    self::assertSame('wiki', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    $proposalStatusRoute = $this->container->get('router.route_provider')->getRouteByName('aculta_wiki.proposal_status');
    self::assertSame('/wiki/propostas/{proposal}', $proposalStatusRoute->getPath());
    $accessManager = $this->container->get('access_manager');
    self::assertTrue($accessManager->checkNamedRoute(
      'aculta_wiki.proposal_status',
      ['proposal' => $proposal->id()],
      $submitter,
    ));
    self::assertFalse($accessManager->checkNamedRoute(
      'aculta_wiki.proposal_status',
      ['proposal' => $proposal->id()],
      $other,
    ));

    $proposalStorage = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal');
    for ($index = 0; $index < 25; $index++) {
      $seedProposal = $proposalStorage->create([
        'uid' => $submitter->id(),
        'subject' => 'Correção de teste ' . $index,
        'target' => $target->id(),
        'target_revision_id' => $target->getRevisionId(),
        'intent' => 'correction',
        'proposed_text' => 'Correção ' . $index,
        'rationale' => 'Justificativa curta.',
        'state' => 'submitted',
      ]);
      $seedProposal->setNewRevision(TRUE);
      $seedProposal->isDefaultRevision(TRUE);
      $seedProposal->save();
    }
    $flood = $this->container->get('flood');
    $flood->clear('aculta_wiki.proposal_submission', (string) $submitter->id());
    for ($index = 0; $index < 10; $index++) {
      $flood->register('aculta_wiki.proposal_submission', 3600, (string) $submitter->id());
    }
    try {
      $submission->submit($staleTarget, 'correction', 'Excesso', 'Tentativa além do limite.');
      self::fail('An author must be rate limited after ten proposals in one hour.');
    }
    catch (TooManyRequestsHttpException) {
      self::assertSame(26, $proposalStorage->getQuery()->accessCheck(FALSE)->count()->execute());
    }
    $flood->clear('aculta_wiki.proposal_submission', (string) $submitter->id());
    $reviewer = new UserSession([
      'uid' => 4,
      'roles' => ['authenticated', 'wiki_proposal_reviewer_test'],
    ]);
    $this->container->get('current_user')->setAccount($reviewer);
    $queue = $this->container->get('aculta_wiki.editorial_queue');
    self::assertInstanceOf(WikiEditorialQueue::class, $queue);
    $firstPage = $queue->proposals();
    self::assertCount(25, $firstPage->getItems());
    self::assertNotNull($firstPage->getNextCursor());
    self::assertContains('user.permissions', $firstPage->getCacheContexts());
    $secondPage = $queue->proposals($firstPage->getNextCursor());
    self::assertCount(1, $secondPage->getItems());
    self::assertNull($secondPage->getNextCursor());
    $firstPageIds = array_map(static fn (WikiProposal $item): int => (int) $item->id(), $firstPage->getItems());
    $secondPageIds = array_map(static fn (WikiProposal $item): int => (int) $item->id(), $secondPage->getItems());
    self::assertCount(26, array_unique(array_merge($firstPageIds, $secondPageIds)));
    self::assertSame($firstPageIds, array_map(static fn (WikiProposal $item): int => (int) $item->id(), $queue->proposals('invalid-cursor')->getItems()));

    $wikiTab = $this->container->get('aculta_studio.tab_manager')->createInstance('wiki');
    self::assertTrue($wikiTab->access($reviewer)->isAllowed());
    self::assertArrayHasKey('moderacao', $wikiTab->sections($reviewer));
    self::assertArrayHasKey('propostas', $wikiTab->sections($reviewer)['moderacao']['tabs']);
    self::assertArrayNotHasKey('verbetes', $wikiTab->sections($reviewer));
    self::assertArrayNotHasKey('revisoes', $wikiTab->sections($reviewer));
    self::assertArrayNotHasKey('moderacao', $wikiTab->sections($submitter));
    $studioProposalListing = $this->container->get('aculta_studio.listing')
      ->build(['entity' => 'wiki_proposal_queue'], $reviewer);
    self::assertCount(25, $studioProposalListing['table']['#rows']);
    self::assertContains('user.permissions', $studioProposalListing['#cache']['contexts']);

    $proposalToDecide = $firstPage->getItems()[0];
    $oldProposalRevisionId = (int) $proposalToDecide->getRevisionId();
    $decisionService = $this->container->get('aculta_wiki.proposal_decision');
    self::assertInstanceOf(WikiProposalDecision::class, $decisionService);
    $decisionForm = WikiProposalDecisionForm::create($this->container);
    $decisionState = new FormState();
    $decisionForm->buildForm([], $decisionState, $proposalToDecide);
    $decisionState->setValues([
      'expected_revision_id' => $oldProposalRevisionId,
      'decision' => 'needs_changes',
      'decision_note' => 'Inclua uma fonte para esta afirmação.',
    ]);
    $decisionBuild = [];
    $decisionForm->validateForm($decisionBuild, $decisionState);
    self::assertFalse($decisionState->hasAnyErrors());
    $decisionForm->submitForm($decisionBuild, $decisionState);
    $decidedProposal = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
      ->load($proposalToDecide->id());
    self::assertSame('needs_changes', $decidedProposal->get('state')->value);
    self::assertSame('Inclua uma fonte para esta afirmação.', $decidedProposal->get('decision_note')->value);
    self::assertNotSame($oldProposalRevisionId, (int) $decidedProposal->getRevisionId());
    self::assertSame('submitted', $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
      ->loadRevision($oldProposalRevisionId)->get('state')->value);

    try {
      $decisionService->decide($proposalToDecide, $oldProposalRevisionId, 'declined', 'Revisão obsoleta.');
      self::fail('A proposal changed after the reviewer opened the form.');
    }
    catch (ConflictHttpException) {
      self::assertSame('needs_changes', $decidedProposal->get('state')->value);
    }

    try {
      $decisionService->decide($decidedProposal, (int) $decidedProposal->getRevisionId(), 'declined', ' ');
      self::fail('An editorial decision requires a non-empty note.');
    }
    catch (UnprocessableEntityHttpException) {
      self::assertSame('needs_changes', $decidedProposal->get('state')->value);
    }

    $this->container->get('current_user')->setAccount($other);
    try {
      $decisionService->decide($decidedProposal, (int) $decidedProposal->getRevisionId(), 'declined', 'Sem permissão.');
      self::fail('A contributor must not moderate proposals.');
    }
    catch (AccessDeniedHttpException) {
      self::assertSame('needs_changes', $decidedProposal->get('state')->value);
    }

    $this->container->get('current_user')->setAccount($reviewer);
    $proposalToDecline = $secondPage->getItems()[0];
    $declinedProposal = $decisionService->decide(
      $proposalToDecline,
      (int) $proposalToDecline->getRevisionId(),
      'declined',
      'A evidência apresentada não sustenta esta alteração.',
    );
    self::assertSame('declined', $declinedProposal->get('state')->value);
    self::assertSame('A evidência apresentada não sustenta esta alteração.', $declinedProposal->get('decision_note')->value);

    $historyProposal = $decidedProposal;
    for ($index = 0; $index < 19; $index++) {
      $historyProposal = $decisionService->decide(
        $historyProposal,
        (int) $historyProposal->getRevisionId(),
        'needs_changes',
        'Nota editorial ' . $index,
      );
    }
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed(2);
    $this->container->get('current_user')->setAccount($submitter);
    $statusController = WikiProposalStatusController::create($this->container);
    $historyBuild = $statusController->view(
      $historyProposal,
      Request::create('/wiki/propostas/' . $historyProposal->id()),
    );
    self::assertCount(20, $historyBuild['history']['table']['#rows']);
    self::assertNotEmpty($historyBuild['history']['pager']['#url']);
    $historyCursor = $historyBuild['history']['pager']['#url']->getOption('query')['revision_cursor'];
    $historyNextPage = $statusController->view(
      $historyProposal,
      Request::create('/wiki/propostas/' . $historyProposal->id(), 'GET', ['revision_cursor' => $historyCursor]),
    );
    self::assertCount(1, $historyNextPage['history']['table']['#rows']);
    self::assertSame([], $historyNextPage['history']['pager']);

    $statusBuild = $statusController->view(
      $declinedProposal,
      Request::create('/wiki/propostas/' . $declinedProposal->id()),
    );
    self::assertSame(
      'A evidência apresentada não sustenta esta alteração.',
      $statusBuild['decision_note']['text']['#plain_text'],
    );
    self::assertContains('user', $statusBuild['#cache']['contexts']);
    self::assertCount(2, $statusBuild['history']['table']['#rows']);
    self::assertSame('Recusada', $statusBuild['history']['table']['#rows'][0]['state']);
    self::assertSame(
      'A evidência apresentada não sustenta esta alteração.',
      $statusBuild['history']['table']['#rows'][0]['feedback'],
    );
    self::assertSame('Enviada', $statusBuild['history']['table']['#rows'][1]['state']);
    $memoryQueue = new class() {

      /** @var list<\Drupal\aculta_mailer\Message\MailMessage> */
      public array $items = [];

      /** Stores a message captured by the notifier test. */
      public function enqueue(MailMessage $message): void {
        $this->items[] = $message;
      }

    };
    $outcomeNotifier = new WikiProposalOutcomeNotifier(
      $this->container->get('string_translation'),
      $this->container->get('aculta_portal.email_confirmation_policy'),
      $this->container->get('aculta_portal.domain_purpose'),
      $this->container->get('uuid'),
      $memoryQueue,
    );
    self::assertTrue($outcomeNotifier->notify($declinedProposal));
    self::assertCount(1, $memoryQueue->items);
    self::assertSame('aculta_wiki_proposal_outcome', $memoryQueue->items[0]->type);
    self::assertSame('author@example.invalid', $memoryQueue->items[0]->to);
    self::assertSame([
      'subject',
      'state',
      'decision_note',
      'proposal_url',
    ], array_keys($memoryQueue->items[0]->context));

    $this->container->get('current_user')->setAccount($submitter);
    $formState = new FormState();
    $proposalForm = WikiProposalSubmissionForm::create($this->container);
    $proposalForm->buildForm([], $formState, $target);
    $formState->setValues([
      'intent' => 'correction',
      'proposed_text' => 'Texto sugerido pelo Form API',
      'rationale' => 'Ajuste comprovado pela fonte.',
      'source_uris' => 'https://example.invalid/referencia',
      'submission_token' => $formState->get('wiki_proposal_submission_token'),
    ]);
    $build = [];
    $proposalForm->validateForm($build, $formState);
    self::assertFalse($formState->hasAnyErrors());
    $proposalForm->submitForm($build, $formState);
    self::assertSame('aculta_wiki.proposal_status', $formState->getRedirect()->getRouteName());
    $redirectProposal = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
      ->load($formState->getRedirect()->getRouteParameters()['proposal']);
    self::assertInstanceOf(WikiProposal::class, $redirectProposal);
    self::assertSame('correction', $redirectProposal->get('intent')->value);
    $firstFormSubmissionId = (int) $redirectProposal->id();
    $proposalForm->submitForm($build, $formState);
    self::assertSame($firstFormSubmissionId, (int) $formState->getRedirect()->getRouteParameters()['proposal']);
    self::assertSame(27, (int) $this->container->get('entity_type.manager')
      ->getStorage('aculta_wiki_proposal')->getQuery()->accessCheck(FALSE)->count()->execute());

    $this->container->get('current_user')->setAccount($other);
    try {
      $queue->proposals();
      self::fail('Accounts without the moderation permission must not read the queue.');
    }
    catch (AccessDeniedHttpException) {
      self::assertTrue(TRUE);
    }
    $this->container->get('current_user')->setAccount($other);
    $this->expectException(AccessDeniedHttpException::class);
    $submission->submit($target, 'correction', 'Correção', 'Motivo');
  }

  /**
   * Pending Wiki Node revisions are queried under Wiki grants and exact VID.
   */
  public function testEditorialQueueReturnsAuthorizedLatestReviewRevision(): void {
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['workflows', 'content_moderation']);
    $workflowFile = dirname(DRUPAL_ROOT) . '/config/sync/workflows.workflow.wiki_editorial.yml';
    Workflow::create(Yaml::parseFile($workflowFile))->save();

    Role::create([
      'id' => 'wiki_queue_submitter_test',
      'label' => 'Wiki queue submitter test',
      'permissions' => ['use wiki_editorial transition draft_to_review'],
    ])->save();
    Role::create([
      'id' => 'wiki_queue_reviewer_test',
      'label' => 'Wiki queue reviewer test',
      'permissions' => [
        'edit any wiki_entry content',
        'view any unpublished content',
        'view wiki_entry revisions',
        'use wiki_editorial transition review_to_draft',
        'use wiki_editorial transition review_to_published',
      ],
    ])->save();

    $submitter = new UserSession(['uid' => 5, 'roles' => ['authenticated', 'wiki_queue_submitter_test']]);
    $reviewer = new UserSession(['uid' => 4, 'roles' => ['authenticated', 'wiki_queue_reviewer_test']]);
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $reviewer->id());
    $this->container->get('current_user')->setAccount($submitter);
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete aguardando edição editorial',
      'uid' => 5,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_test_grant_uid' => 4,
      'field_wiki_summary' => ['value' => 'Resumo em revisão'],
      'body' => ['value' => 'Texto em revisão', 'format' => 'plain_text'],
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Enviado para revisão no teste');
    $node->set('moderation_state', 'review');
    $node->save();
    $reviewRevisionId = (string) $node->getRevisionId();
    self::assertSame('review', $node->get('moderation_state')->value);
    self::assertSame($reviewRevisionId, (string) $this->container->get('entity_type.manager')
      ->getStorage('node')->getLatestRevisionId($node->id()));
    $moderationStates = $this->container->get('entity_type.manager')
      ->getStorage('content_moderation_state')->getQuery()
      ->accessCheck(FALSE)
      ->condition('content_entity_type_id', 'node')
      ->condition('workflow', 'wiki_editorial')
      ->condition('moderation_state', 'review')
      ->condition('content_entity_revision_id', $reviewRevisionId)
      ->execute();
    self::assertNotEmpty($moderationStates);
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    $this->container->get('node.view_all_nodes_memory_cache')->deleteAll();

    $mainDomain = Domain::load('aculta_org');
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain($mainDomain);
    $this->container->get('current_user')->setAccount($reviewer);
    $reviewEntity = $this->container->get('entity_type.manager')->getStorage('node')->loadRevision($reviewRevisionId);
    self::assertNotEmpty($this->container->get('content_moderation.state_transition_validation')
      ->getValidTransitions($reviewEntity, $reviewer));
    self::assertTrue($reviewer->hasPermission('edit any wiki_entry content'));
    $reviewAccess = $this->container->get('aculta_portal.domain_purpose')->runInPurpose('wiki', function () use ($reviewRevisionId, $reviewer): array {
      $revision = $this->container->get('entity_type.manager')->getStorage('node')->loadRevision($reviewRevisionId);
      $updateAccess = $revision->access('update', $reviewer, TRUE);
      return [
        $revision->access('view', $reviewer, TRUE)->isAllowed(),
        $revision->access('view revision', $reviewer, TRUE)->isAllowed(),
        $updateAccess,
      ];
    });
    self::assertSame([TRUE, TRUE], array_slice($reviewAccess, 0, 2));
    self::assertTrue($reviewAccess[2]->isAllowed());
    $queue = $this->container->get('aculta_wiki.editorial_queue');
    $page = $queue->entryRevisions();

    self::assertCount(1, $page->getItems());
    self::assertSame($reviewRevisionId, (string) $page->getItems()[0]->getRevisionId());
    self::assertSame('review', $page->getItems()[0]->get('moderation_state')->value);
    self::assertContains('user.node_grants:view', $page->getCacheContexts());
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    self::assertNull($page->getNextCursor());

    $wikiTab = $this->container->get('aculta_studio.tab_manager')->createInstance('wiki');
    self::assertArrayHasKey('revisoes', $wikiTab->sections($reviewer)['moderacao']['tabs']);
    $studioReviewListing = $this->container->get('aculta_studio.listing')
      ->build(['entity' => 'wiki_entry_review_queue'], $reviewer);
    self::assertCount(1, $studioReviewListing['table']['#rows']);
    self::assertSame('Abrir para revisão', (string) $studioReviewListing['table']['#rows'][0]['action']['data']['#title']);

    Role::create([
      'id' => 'wiki_queue_grant_reviewer_test',
      'label' => 'Wiki queue grant reviewer test',
      'permissions' => [
        'edit any wiki_entry content',
        'view wiki_entry revisions',
        'use wiki_editorial transition review_to_draft',
        'use wiki_editorial transition review_to_published',
      ],
    ])->save();
    $grantReviewer = new UserSession(['uid' => 4, 'roles' => ['authenticated', 'wiki_queue_grant_reviewer_test']]);
    $this->container->get('current_user')->setAccount($submitter);
    for ($index = 0; $index < 26; $index++) {
      $unreadable = Node::create([
        'type' => 'wiki_entry',
        'title' => 'Verbete sem grant ' . $index,
        'uid' => 5,
        'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
        'field_wiki_test_grant_uid' => 99,
        'body' => ['value' => 'Texto restrito', 'format' => 'plain_text'],
        'moderation_state' => 'draft',
      ]);
      $unreadable->save();
      $unreadable->setNewRevision(TRUE);
      $unreadable->set('moderation_state', 'review');
      $unreadable->save();
    }
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    $this->container->get('node.view_all_nodes_memory_cache')->deleteAll();
    $this->container->get('current_user')->setAccount($grantReviewer);
    $restrictedPage = $queue->entryRevisions();
    self::assertCount(1, $restrictedPage->getItems());
    self::assertSame($reviewRevisionId, (string) $restrictedPage->getItems()[0]->getRevisionId());
  }

  /**
   * Creates one published Wiki entry in the real Wiki Domain.
   */
  private function createSearchEntry(string $title, int $uid): int {
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => $title,
      'uid' => $uid,
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_summary' => ['value' => 'Resumo do verbete'],
      'body' => ['value' => 'Corpo do verbete', 'format' => 'plain_text'],
      'field_wiki_test_grant_uid' => $uid,
    ]);
    $node->save();
    return (int) $node->id();
  }

  /**
   * Publication states covered by the image visibility test.
   *
   * @return array<string, array{0: bool}>
   *   Whether the submitted Node revision is published.
   */
  public static function publicationStates(): array {
    return [
      'draft keeps Media and private file inaccessible' => [FALSE],
      'published entry allows its lead image' => [TRUE],
    ];
  }

  /**
   * Gets the real Wiki uploader service and swaps the test author into it.
   */
  private function uploader(): WikiLeadImageUploader {
    $owner = $this->container->get('current_user');
    $owner->setAccount($this->owner);
    return $this->container->get('aculta_wiki.wiki_lead_image_uploader');
  }

  /**
   * Test author used for uploads and nodes.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $owner;

  /**
   * Creates a confirmed, authenticated upload owner.
   */
  private function createOwner(): AccountInterface {
    $this->owner = new AnonymousUserSession();
    return $this->owner;
  }

  /**
   * Creates a temporary managed file containing a valid 1x1 PNG privately.
   */
  private function createTemporaryPrivateImage(int $uid): File {
    $directory = WikiLeadImageUploader::DIRECTORY;
    self::assertTrue($this->container->get('file_system')->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    ));
    $uri = 'private://wiki/imagens/' . $this->randomMachineName() . '.png';
    $repository = $this->container->get('file.repository');
    $file = $repository->writeData(
      base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pksAAAAASUVORK5CYII='),
      $uri,
      FileExists::Rename,
    );
    $file->setOwnerId($uid);
    $file->setTemporary();
    $file->save();
    return $file;
  }

  /**
   * Builds an upload state for the submitted managed file.
   */
  private function uploadState(int $fid): FormState {
    $state = new FormState();
    $state->setValues([
      WikiLeadImageUploader::TAB => [
        WikiLeadImageUploader::FILE => [$fid],
        WikiLeadImageUploader::ALT => 'Imagem de teste',
      ],
    ]);
    return $state;
  }

  /**
   * Builds a form object that invokes a real Node save callback.
   */
  private function entityForm(Node $node, callable $save): EntityFormInterface {
    $form = $this->createMock(EntityFormInterface::class);
    $form->method('getEntity')->willReturn($node);
    $form->method('save')->willReturnCallback(static function (array $form, FormState $state) use ($save): void {
      $save();
    });
    return $form;
  }

  /**
   * Resolves the installed module dependency closure in dependency order.
   *
   * @param string[] $roots
   *   Module IDs to boot.
   *
   * @return string[]
   *   Ordered module IDs including dependencies.
   */
  private function resolveModuleDependencyClosure(array $roots): array {
    $discovery = new ExtensionDiscovery(DRUPAL_ROOT);
    $discovery->setProfileDirectories([]);
    $extensions = $discovery->scan('module');
    $ordered = [];
    $visited = [];
    $visiting = [];
    $visit = function (string $module) use (&$visit, &$ordered, &$visited, &$visiting, $extensions): void {
      if (isset($visited[$module])) {
        return;
      }
      if (isset($visiting[$module])) {
        throw new \LogicException(sprintf('Circular module dependency detected at %s.', $module));
      }
      if (!isset($extensions[$module])) {
        throw new \RuntimeException(sprintf('Required module %s is unavailable.', $module));
      }
      $visiting[$module] = TRUE;
      $info = Yaml::parseFile(DRUPAL_ROOT . '/' . $extensions[$module]->getPathname());
      foreach ($info['dependencies'] ?? [] as $dependency) {
        if (!is_string($dependency)) {
          continue;
        }
        $dependency = substr($dependency, strrpos($dependency, ':') === FALSE ? 0 : strrpos($dependency, ':') + 1);
        if (isset($extensions[$dependency])) {
          $visit($dependency);
        }
      }
      unset($visiting[$module]);
      $visited[$module] = TRUE;
      $ordered[] = $module;
    };
    foreach ($roots as $root) {
      $visit($root);
    }
    return $ordered;
  }

}
