<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Kernel;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
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
use Drupal\editor\Entity\Editor;
use Drupal\aculta_portal\Cleanup\FileReferenceStatus;
use Drupal\aculta_portal\Domain\FrontPathPolicy;
use Drupal\aculta_portal\PathProcessor\PurposeFrontPathProcessor;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\profile\Entity\ProfileType;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\node\NodeAccessRebuild;
use Drupal\node\NodeGrantsHelper;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
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
use Drupal\aculta_wiki\Form\WikiProposalWithdrawalForm;
use Drupal\aculta_wiki\Hook\WikiProposalLinkHooks;
use Drupal\aculta_wiki\Hook\WikiEntryTitleSuggestions;
use Drupal\aculta_wiki\Controller\WikiProposalStatusController;
use Drupal\aculta_wiki\Controller\WikiContributionIntentController;
use Drupal\aculta_wiki\Notifier\WikiProposalOutcomeNotifier;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
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
      'ckeditor5',
      'freelinking',
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
    $this->installConfig(['system', 'user', 'file', 'media', 'node', 'domain', 'filter', 'editor', 'ckeditor5']);

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
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_wiki_test_update_uid',
      'type' => 'integer',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'field_wiki_test_update_uid',
      'label' => 'Test grant editor UID',
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
   * An existing lead image follows later publication state changes.
   */
  public function testExistingLeadMediaFollowsNodePublicationChanges(): void {
    $owner = $this->createOwner();
    $file = $this->createTemporaryPrivateImage((int) $owner->id());
    $file->setPermanent();
    $file->save();
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Imagem existente',
      'uid' => $owner->id(),
      'status' => FALSE,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'Descrição da imagem existente',
      ],
    ]);
    $media->save();
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Imagem existente acompanha o workflow',
      'uid' => $owner->id(),
      'status' => FALSE,
      WikiLeadImageUploader::FIELD => ['target_id' => $media->id()],
    ]);
    $node->save();
    $sharedNode = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Outro verbete usa a mesma imagem',
      'uid' => $owner->id(),
      'status' => TRUE,
      WikiLeadImageUploader::FIELD => ['target_id' => $media->id()],
    ]);
    $sharedNode->save();

    $node->setPublished();
    $node->save();
    $mediaStorage = $this->container->get('entity_type.manager')->getStorage('media');
    $mediaStorage->resetCache([(int) $media->id()]);
    self::assertTrue($mediaStorage->load($media->id())->isPublished());
    self::assertTrue($file->access('download', new AnonymousUserSession()));
    $downloadResults = $this->container->get('module_handler')->invokeAll('file_download', [$file->getFileUri()]);
    self::assertNotContains(-1, $downloadResults, 'The active published Wiki reference permits the private download.');

    $node->setUnpublished();
    $node->save();
    $mediaStorage->resetCache([(int) $media->id()]);
    self::assertTrue($mediaStorage->load($media->id())->isPublished(), 'A second published Wiki entry still uses the Media.');
    $downloadResults = $this->container->get('module_handler')->invokeAll('file_download', [$file->getFileUri()]);
    self::assertNotContains(-1, $downloadResults, 'The other published Wiki reference keeps the image downloadable.');

    $sharedNode->setUnpublished();
    $sharedNode->save();
    $mediaStorage->resetCache([(int) $media->id()]);
    self::assertFalse($mediaStorage->load($media->id())->isPublished());
    self::assertTrue($file->access('download', new AnonymousUserSession()), 'Core still authorizes a file referenced by a published historical revision.');
    $downloadResults = $this->container->get('module_handler')->invokeAll('file_download', [$file->getFileUri()]);
    self::assertContains(-1, $downloadResults, 'The Wiki download hook denies a historical-only reference.');
  }

  /**
   * A pending non-default revision cannot expose its replacement lead image.
   */
  public function testPendingRevisionKeepsPublishedLeadImageAndHidesReplacement(): void {
    $owner = $this->createOwner();
    $publishedFile = $this->createTemporaryPrivateImage((int) $owner->id());
    $publishedFile->setPermanent();
    $publishedFile->save();
    $publishedMedia = Media::create([
      'bundle' => 'image',
      'name' => 'Imagem da revisão pública',
      'uid' => $owner->id(),
      'status' => TRUE,
      'field_media_image' => [
        'target_id' => $publishedFile->id(),
        'alt' => 'Imagem pública',
      ],
    ]);
    $publishedMedia->save();

    $pendingFile = $this->createTemporaryPrivateImage((int) $owner->id());
    $pendingFile->setPermanent();
    $pendingFile->save();
    $pendingMedia = Media::create([
      'bundle' => 'image',
      'name' => 'Imagem da revisão pendente',
      'uid' => $owner->id(),
      'status' => FALSE,
      'field_media_image' => [
        'target_id' => $pendingFile->id(),
        'alt' => 'Imagem pendente',
      ],
    ]);
    $pendingMedia->save();

    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete publicado com imagem',
      'uid' => $owner->id(),
      'status' => TRUE,
      WikiLeadImageUploader::FIELD => ['target_id' => $publishedMedia->id()],
    ]);
    $node->save();
    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    $node->setUnpublished();
    $node->set(WikiLeadImageUploader::FIELD, ['target_id' => $pendingMedia->id()]);
    $node->save();

    $nodeStorage = $this->container->get('entity_type.manager')->getStorage('node');
    $defaultRevision = $nodeStorage->load((int) $node->id());
    self::assertInstanceOf(NodeInterface::class, $defaultRevision);
    self::assertTrue($defaultRevision->isPublished());
    self::assertSame((int) $publishedMedia->id(), (int) $defaultRevision->get(WikiLeadImageUploader::FIELD)->target_id);

    $mediaStorage = $this->container->get('entity_type.manager')->getStorage('media');
    $mediaStorage->resetCache([(int) $publishedMedia->id(), (int) $pendingMedia->id()]);
    self::assertTrue($mediaStorage->load($publishedMedia->id())->isPublished());
    self::assertTrue($mediaStorage->load($publishedMedia->id())->access('view', new AnonymousUserSession()));
    self::assertFalse($mediaStorage->load($pendingMedia->id())->isPublished());
    self::assertFalse($mediaStorage->load($pendingMedia->id())->access('view', new AnonymousUserSession()));
    self::assertTrue($publishedFile->access('download', new AnonymousUserSession()));
    self::assertFalse($pendingFile->access('download', new AnonymousUserSession()));
  }

  /**
   * Deleting the final published host makes its lead Media private.
   */
  public function testDeletingLastPublishedLeadImageReferenceUnpublishesMedia(): void {
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $owner = $this->createOwner();
    $file = $this->createTemporaryPrivateImage((int) $owner->id());
    $file->setPermanent();
    $file->save();
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Imagem do verbete removido',
      'uid' => $owner->id(),
      'status' => TRUE,
      'field_media_image' => ['target_id' => $file->id(), 'alt' => 'Imagem'],
    ]);
    $media->save();
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Último verbete com imagem',
      'uid' => $owner->id(),
      'status' => TRUE,
      WikiLeadImageUploader::FIELD => ['target_id' => $media->id()],
    ]);
    $node->save();

    $node->delete();

    $mediaStorage = $this->container->get('entity_type.manager')->getStorage('media');
    $mediaStorage->resetCache([(int) $media->id()]);
    self::assertFalse($mediaStorage->load($media->id())->isPublished());
  }

  /**
   * Retains old-revision images, then removes the orphan Media and File.
   */
  public function testLeadImageCleanupWaitsForHistoricalRevisionReferences(): void {
    $this->installEntitySchema('lms_lesson');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $owner = $this->createOwner();
    $old = $this->container->get('datetime.time')->getRequestTime() - 7200;
    $file = $this->createTemporaryPrivateImage((int) $owner->id());
    $file->setPermanent();
    $file->set('created', $old);
    $file->set('changed', $old);
    $file->save();
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Imagem histórica da Wiki',
      'uid' => $owner->id(),
      'status' => FALSE,
      'created' => $old,
      'field_media_image' => ['target_id' => $file->id(), 'alt' => 'Imagem histórica'],
    ]);
    $media->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_article_shared_image',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'media'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'field_article_shared_image',
      'label' => 'Shared article image',
    ])->save();
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete com imagem histórica',
      'uid' => $owner->id(),
      'status' => FALSE,
      WikiLeadImageUploader::FIELD => ['target_id' => $media->id()],
    ]);
    $node->save();
    $article = Node::create([
      'type' => 'article',
      'title' => 'Outro conteúdo que reutiliza a Media',
      'uid' => $owner->id(),
      'status' => FALSE,
      'field_article_shared_image' => ['target_id' => $media->id()],
    ]);
    $article->save();
    $oldRevisionId = (int) $node->getRevisionId();

    $node->setNewRevision(TRUE);
    $node->set(WikiLeadImageUploader::FIELD, []);
    $node->save();

    $cleanup = $this->container->get('aculta_wiki.lead_image_cleanup');
    self::assertSame(['processed' => 1, 'media_deleted' => 0, 'files_deleted' => 0], $cleanup->cleanup());
    $revision = $this->container->get('entity_type.manager')->getStorage('node')->loadRevision($oldRevisionId);
    self::assertSame((string) $media->id(), (string) $revision->get(WikiLeadImageUploader::FIELD)->target_id);
    self::assertInstanceOf(Media::class, Media::load($media->id()));
    self::assertInstanceOf(File::class, File::load($file->id()));

    $node->delete();
    self::assertSame(
      FileReferenceStatus::Referenced,
      $this->container->get('aculta_portal.file_reference_inspector')->inspect($media),
    );
    self::assertSame(['processed' => 1, 'media_deleted' => 0, 'files_deleted' => 0], $cleanup->cleanup());
    self::assertInstanceOf(Media::class, Media::load($media->id()));
    self::assertInstanceOf(File::class, File::load($file->id()));

    $article->delete();
    self::assertSame(
      FileReferenceStatus::Clear,
      $this->container->get('aculta_portal.file_reference_inspector')->inspect($media),
    );
    self::assertSame(['processed' => 1, 'media_deleted' => 1, 'files_deleted' => 1], $cleanup->cleanup());
    self::assertNull(Media::load($media->id()));
    self::assertNull(File::load($file->id()));
  }

  /**
   * Retains orphan candidates whenever global reference inspection fails.
   */
  public function testLeadImageCleanupFailsClosedWhenReferenceStorageIsUnavailable(): void {
    $this->installEntitySchema('lms_lesson');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $owner = $this->createOwner();
    $old = $this->container->get('datetime.time')->getRequestTime() - 7200;
    $file = $this->createTemporaryPrivateImage((int) $owner->id());
    $file->setPermanent();
    $file->set('created', $old);
    $file->save();
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Imagem cuja inspeção falhará',
      'uid' => $owner->id(),
      'status' => FALSE,
      'created' => $old,
      'field_media_image' => ['target_id' => $file->id(), 'alt' => 'Imagem de teste'],
    ]);
    $media->save();

    $revisionFieldTable = 'node_revision__' . WikiLeadImageUploader::FIELD;
    $schema = $this->container->get('database')->schema();
    self::assertTrue($schema->tableExists($revisionFieldTable));
    $schema->dropTable($revisionFieldTable);

    self::assertSame(
      ['processed' => 1, 'media_deleted' => 0, 'files_deleted' => 0],
      $this->container->get('aculta_wiki.lead_image_cleanup')->cleanup(),
    );
    self::assertInstanceOf(Media::class, Media::load($media->id()));
    self::assertInstanceOf(File::class, File::load($file->id()));
  }

  /**
   * Processes at most 25 old Wiki files during one cleanup pass.
   */
  public function testLeadImageCleanupUsesBoundedBatches(): void {
    $this->installEntitySchema('lms_lesson');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $owner = $this->createOwner();
    $old = $this->container->get('datetime.time')->getRequestTime() - 7200;
    $fileUsage = $this->container->get('file.usage');
    $files = [];
    for ($index = 0; $index < 26; $index++) {
      $file = $this->createTemporaryPrivateImage((int) $owner->id());
      $file->setPermanent();
      $file->set('created', $old);
      $file->save();
      $fileUsage->add($file, 'aculta_wiki_test', 'node', $index + 1);
      $files[] = $file;
    }

    self::assertSame(
      ['processed' => 25, 'media_deleted' => 0, 'files_deleted' => 0],
      $this->container->get('aculta_wiki.lead_image_cleanup')->cleanup(),
    );
    self::assertSame(
      (int) $files[24]->id(),
      (int) $this->container->get('keyvalue.expirable')->get('aculta_wiki.lead_image_cleanup')->get('last_fid'),
    );
    $fileUsage->delete($files[25], 'aculta_wiki_test', 'node', 26, 1);
    self::assertSame([], $fileUsage->listUsage($files[25]));
    self::assertSame(
      ['processed' => 25, 'media_deleted' => 0, 'files_deleted' => 1],
      $this->container->get('aculta_wiki.lead_image_cleanup')->cleanup(),
      'The rotating cursor reaches candidates beyond the first batch.',
    );
    self::assertNull(File::load($files[25]->id()));
    self::assertCount(25, array_filter($files, static fn (File $file): bool => File::load($file->id()) instanceof File));
  }

  /**
   * Retains recent permanent files until the one-hour grace period expires.
   */
  public function testLeadImageCleanupHonorsRetentionPeriod(): void {
    $owner = $this->createOwner();
    $file = $this->createTemporaryPrivateImage((int) $owner->id());
    $file->setPermanent();
    $file->save();

    self::assertSame(
      ['processed' => 0, 'media_deleted' => 0, 'files_deleted' => 0],
      $this->container->get('aculta_wiki.lead_image_cleanup')->cleanup(),
    );
    self::assertInstanceOf(File::class, File::load($file->id()));
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
   * Validation failure before submit creates no Media and keeps File temporary.
   */
  public function testUploadValidationFailureDoesNotPersistMediaOrFile(): void {
    $owner = $this->createOwner();
    $file = $this->createTemporaryPrivateImage((int) $owner->id());
    $fileId = (int) $file->id();
    $state = $this->uploadState($fileId);
    $state->setValue([WikiLeadImageUploader::TAB, WikiLeadImageUploader::ALT], '  ');

    $form = [];
    $this->uploader()->validateUpload($form, $state);

    self::assertArrayHasKey('wiki_lead_image][alt', $state->getErrors());
    $mediaCount = $this->container->get('entity_type.manager')->getStorage('media')->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_media_image.target_id', $fileId)
      ->count()
      ->execute();
    self::assertSame(0, (int) $mediaCount);
    $fileStorage = $this->container->get('entity_type.manager')->getStorage('file');
    $fileStorage->resetCache([$fileId]);
    self::assertTrue($fileStorage->load($fileId)->isTemporary());
  }

  /**
   * A form rebuild retries the same upload without a stale Media or File cache.
   */
  public function testRebuiltUploadCanBeRetriedWithoutOrphans(): void {
    $owner = $this->createOwner();
    $file = $this->createTemporaryPrivateImage($owner->id());
    $fileId = (int) $file->id();
    $state = $this->uploadState($fileId);
    $form = [];
    $uploader = $this->uploader();
    $uploader->applyUpload($form, $state);
    $rolledBackMediaId = (int) $state->getValue([WikiLeadImageUploader::FIELD, 0, 'target_id']);
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Imagem após reconstrução do formulário',
      'uid' => $owner->id(),
      'status' => TRUE,
      WikiLeadImageUploader::FIELD => ['target_id' => $rolledBackMediaId],
    ]);
    $attempt = 0;
    $formObject = $this->entityForm($node, function () use (&$attempt, $state, $node): void {
      if ($attempt++ === 0) {
        $state->setRebuild();
        return;
      }
      $state->setRebuild(FALSE);
      $node->save();
    });
    $state->setFormObject($formObject);

    $uploader->prepareNodeSave($form, $state);
    $uploader->saveNode($form, $state);

    self::assertNull(Media::load($rolledBackMediaId));
    self::assertNull(Node::load((int) $node->id()));
    self::assertNull($state->get(WikiLeadImageUploader::STORAGE_KEY));
    $fileStorage = $this->container->get('entity_type.manager')->getStorage('file');
    $fileStorage->resetCache([$fileId]);
    self::assertTrue($fileStorage->load($fileId)->isTemporary());

    $uploader->applyUpload($form, $state);
    $savedMediaId = (int) $state->getValue([WikiLeadImageUploader::FIELD, 0, 'target_id']);
    $node->set(WikiLeadImageUploader::FIELD, $state->getValue([WikiLeadImageUploader::FIELD]));
    $uploader->prepareNodeSave($form, $state);
    $uploader->saveNode($form, $state);

    $savedMedia = Media::load($savedMediaId);
    self::assertInstanceOf(Media::class, $savedMedia);
    self::assertTrue($savedMedia->isPublished());
    self::assertTrue(Node::load((int) $node->id())->get(WikiLeadImageUploader::FIELD)->entity->access('view', new AnonymousUserSession()));
    self::assertSame(1, (int) $this->container->get('entity_type.manager')->getStorage('media')
      ->getQuery()->accessCheck(FALSE)->condition('field_media_image.target_id', $fileId)->count()->execute());
    $fileStorage->resetCache([$fileId]);
    self::assertFalse($fileStorage->load($fileId)->isTemporary());
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
    $node = Node::load((int) $node->id());
    self::assertInstanceOf(Node::class, $node);
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
    self::assertFalse($media->isPublished());
    self::assertFalse($file->access('download', new AnonymousUserSession()));
    $downloadResults = $this->container->get('module_handler')->invokeAll('file_download', [$file->getFileUri()]);
    self::assertContains(-1, $downloadResults, 'A historical revision keeps the file but not public download access.');
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
    $purposeResolver = $this->container->get('aculta_portal.content_purpose_resolver');
    foreach ($routePaths as $routeName => $path) {
      $route = $routeProvider->getRouteByName($routeName);
      self::assertSame($path, $route->getPath());
      self::assertCount(1, $routeProvider->getRoutesByPattern($path), 'The Wiki path must have one route owner.');
      self::assertSame('access content', $route->getRequirement('_permission'));
      self::assertSame('wiki', $route->getOption('_aculta_domain_purpose'));
      self::assertSame('wiki', $purposeResolver->requiredPurpose($route, $routeName, Request::create($path)));
      self::assertTrue($accessManager->checkNamedRoute($routeName, [], new AnonymousUserSession()));
    }

    // The public Wiki host serves its root through the internal /wiki route;
    // direct requests to that internal path remain hidden by the shared policy.
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain(Domain::load('wiki420_aculta_org'));
    $frontProcessor = $this->container->get('aculta_portal.purpose_front_path_processor');
    $rootRequest = Request::create('/');
    self::assertSame('/wiki', $frontProcessor->processInbound('/', $rootRequest));
    $servedAsRoot = $rootRequest->attributes->get(PurposeFrontPathProcessor::FRONT_ATTRIBUTE, FALSE);
    self::assertTrue($servedAsRoot);
    self::assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('wiki', '/wiki', $servedAsRoot));

    $directRequest = Request::create('/wiki');
    self::assertSame('/wiki', $frontProcessor->processInbound('/wiki', $directRequest));
    self::assertFalse($directRequest->attributes->get(PurposeFrontPathProcessor::FRONT_ATTRIBUTE, FALSE));
    self::assertTrue(FrontPathPolicy::bloqueiaCaminhoInterno('wiki', '/wiki', FALSE));

    // The internal Wiki home is served from the Wiki root, but not from the
    // main Portal host. Exercise the real purpose subscriber for both cases.
    $purposeSubscriber = $this->container->get('aculta_portal.domain_purpose_subscriber');
    $wikiHomeRequest = Request::create('/wiki');
    $wikiHomeRequest->attributes->set(PurposeFrontPathProcessor::FRONT_ATTRIBUTE, TRUE);
    $wikiHomeRequest->attributes->set('_route', 'aculta_portal.wiki_home');
    $wikiHomeRequest->attributes->set('_route_object', $routeProvider->getRouteByName('aculta_portal.wiki_home'));
    $wikiHomeEvent = new RequestEvent(
      $this->container->get('kernel'),
      $wikiHomeRequest,
      HttpKernelInterface::MAIN_REQUEST,
    );
    $purposeSubscriber->onRequest($wikiHomeEvent);
    self::assertNull($wikiHomeEvent->getResponse(), 'The Wiki home is accepted when the Wiki root serves it.');

    $domainNegotiator = $this->container->get(DomainNegotiatorInterface::class);
    $domainNegotiator->setActiveDomain(Domain::load('aculta_org'));
    $mainHomeRequest = Request::create('https://main.example.invalid/wiki');
    $mainHomeRequest->attributes->set('_route', 'aculta_portal.wiki_home');
    $mainHomeRequest->attributes->set('_route_object', $routeProvider->getRouteByName('aculta_portal.wiki_home'));
    $mainHomeEvent = new RequestEvent(
      $this->container->get('kernel'),
      $mainHomeRequest,
      HttpKernelInterface::MAIN_REQUEST,
    );
    $purposeSubscriber->onRequest($mainHomeEvent);
    self::assertNotNull($mainHomeEvent->getResponse());
    self::assertSame(404, $mainHomeEvent->getResponse()->getStatusCode());
    $domainNegotiator->setActiveDomain(Domain::load('wiki420_aculta_org'));

    // Public Wiki routes must not be served from the main Portal domain.
    $domainNegotiator->setActiveDomain(Domain::load('aculta_org'));
    $searchRoute = $routeProvider->getRouteByName('aculta_portal.wiki_search');
    $wrongPurposeRequest = Request::create('https://main.example.invalid/wiki/busca');
    $wrongPurposeRequest->attributes->set('_route', 'aculta_portal.wiki_search');
    $wrongPurposeRequest->attributes->set('_route_object', $searchRoute);
    $wrongPurposeEvent = new RequestEvent(
      $this->container->get('kernel'),
      $wrongPurposeRequest,
      HttpKernelInterface::MAIN_REQUEST,
    );
    $this->container->get('aculta_portal.domain_purpose_subscriber')->onRequest($wrongPurposeEvent);
    self::assertNotNull($wrongPurposeEvent->getResponse());
    self::assertSame(404, $wrongPurposeEvent->getResponse()->getStatusCode());
    $domainNegotiator->setActiveDomain(Domain::load('wiki420_aculta_org'));

    $proposalRoute = $routeProvider->getRouteByName('aculta_wiki.proposal_submit');
    self::assertSame('/wiki/verbetes/{node}/contribuir', $proposalRoute->getPath());
    self::assertSame('submit wiki proposals', $proposalRoute->getRequirement('_permission'));
    self::assertSame('node.view', $proposalRoute->getRequirement('_entity_access'));
    self::assertSame('wiki', $proposalRoute->getOption('_aculta_domain_purpose'));
    self::assertSame('wiki', $purposeResolver->requiredPurpose(
      $proposalRoute,
      'aculta_wiki.proposal_submit',
      Request::create('/wiki/verbetes/1/contribuir'),
    ));
    $proposalTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Alvo do formulário de contribuição',
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'body' => ['value' => 'Texto público', 'format' => 'plain_text'],
    ]);
    $proposalTarget->save();
    self::assertFalse($accessManager->checkNamedRoute('aculta_wiki.proposal_submit', ['node' => $proposalTarget->id()], new AnonymousUserSession()));
    $domainNegotiator->setActiveDomain(Domain::load('aculta_org'));
    $wrongProposalRequest = Request::create('https://main.example.invalid/wiki/verbetes/' . $proposalTarget->id() . '/contribuir');
    $wrongProposalRequest->attributes->set('_route', 'aculta_wiki.proposal_submit');
    $wrongProposalRequest->attributes->set('_route_object', $proposalRoute);
    $wrongProposalEvent = new RequestEvent(
      $this->container->get('kernel'),
      $wrongProposalRequest,
      HttpKernelInterface::MAIN_REQUEST,
    );
    $this->container->get('aculta_portal.domain_purpose_subscriber')->onRequestBeforeRouter($wrongProposalEvent);
    self::assertNotNull($wrongProposalEvent->getResponse());
    self::assertSame(404, $wrongProposalEvent->getResponse()->getStatusCode());
    $domainNegotiator->setActiveDomain(Domain::load('wiki420_aculta_org'));
    $intentRoute = $routeProvider->getRouteByName('aculta_wiki.contribution_intent');
    self::assertSame('/wiki/verbetes/{node}/contribuir/como', $intentRoute->getPath());
    self::assertSame('wiki', $intentRoute->getOption('_aculta_domain_purpose'));
    self::assertSame('node.view', $intentRoute->getRequirement('_entity_access'));
    $withdrawRoute = $routeProvider->getRouteByName('aculta_wiki.proposal_withdraw');
    self::assertSame('/wiki/propostas/{proposal}/retirar', $withdrawRoute->getPath());
    self::assertSame('proposal.view', $withdrawRoute->getRequirement('_entity_access'));
    $decisionRoute = $routeProvider->getRouteByName('aculta_wiki.proposal_decide');
    self::assertSame('/studio/marley/wiki/propostas/{proposal}/decidir', $decisionRoute->getPath());
    self::assertSame('moderate wiki proposals', $decisionRoute->getRequirement('_permission'));
    self::assertSame('proposal.update', $decisionRoute->getRequirement('_entity_access'));

    Role::create([
      'id' => 'wiki_form_contributor_test',
      'label' => 'Wiki Form contributor test',
      'permissions' => ['submit wiki proposals'],
    ])->save();
    $contributor = new UserSession(['uid' => 7, 'roles' => ['authenticated', 'wiki_form_contributor_test']]);
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $contributor->id());
    $this->container->get('current_user')->setAccount($contributor);
    $requestStack = $this->container->get('request_stack');
    $requestStack->push(Request::create('/wiki/verbetes/' . $proposalTarget->id() . '/contribuir', 'GET', ['intent' => 'reference']));
    try {
      $proposalForm = WikiProposalSubmissionForm::create($this->container);
      $proposalBuild = $proposalForm->buildForm([], new FormState(), $proposalTarget);
    }
    finally {
      $requestStack->pop();
    }
    self::assertSame('aculta_wiki_proposal_submission', $proposalForm->getFormId());
    self::assertSame('reference', $proposalBuild['intent']['#default_value']);
    self::assertArrayHasKey('proposed_text', $proposalBuild);
    self::assertArrayHasKey('rationale', $proposalBuild);
    self::assertArrayHasKey('source_uris', $proposalBuild);
    $formBuilder = $this->container->get('form_builder');
    $actualFormState = new FormState();
    $actualFormState->addBuildInfo('args', [$proposalTarget]);
    $actualProposalFormObject = WikiProposalSubmissionForm::create($this->container);
    $processedProposalForm = $formBuilder->buildForm($actualProposalFormObject, $actualFormState);
    self::assertSame(['field_values', 'title'], $processedProposalForm['field_values']['title']['#parents']);
    self::assertSame(['field_values', 'image'], $processedProposalForm['field_values']['image']['#parents']);
    $actualFormState->setValue($processedProposalForm['intent']['#parents'], 'correction');
    $actualFormState->setValue($processedProposalForm['proposed_text']['#parents'], 'Atualizar o título com a forma conhecida.');
    $actualFormState->setValue($processedProposalForm['proposed_fields_selected']['#parents'], ['title' => 'title']);
    $actualFormState->setValue($processedProposalForm['field_values']['title']['#parents'], 'Título revisado pelo Form API');
    $actualFormState->setValue($processedProposalForm['rationale']['#parents'], 'A fonte comunitária confirma esta forma.');
    $actualFormState->setValue($processedProposalForm['source_uris']['#parents'], '');
    $actualFormState->setValue($processedProposalForm['submission_token']['#parents'], $processedProposalForm['submission_token']['#value']);
    $actualFormState->setTriggeringElement($processedProposalForm['actions']['continue']);
    $actualProposalFormObject->validateForm($processedProposalForm, $actualFormState);
    self::assertSame([], $actualFormState->getErrors());
    $actualProposalFormObject->continueToReview($processedProposalForm, $actualFormState);
    $processedReviewForm = $actualProposalFormObject->buildForm([], $actualFormState, $proposalTarget);
    $actualFormState->setRebuild(FALSE);
    $actualFormState->setTriggeringElement($processedReviewForm['actions']['submit']);
    $actualProposalFormObject->validateForm($processedReviewForm, $actualFormState);
    $this->container->get('aculta_portal.domain_purpose')->runInPurpose('wiki', function () use ($actualFormState, $actualProposalFormObject, &$processedReviewForm): void {
      $actualProposalFormObject->submitForm($processedReviewForm, $actualFormState);
    });
    self::assertSame('aculta_wiki.proposal_status', $actualFormState->getRedirect()->getRouteName());
    $actualProposal = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
      ->load($actualFormState->getRedirect()->getRouteParameters()['proposal']);
    self::assertInstanceOf(WikiProposal::class, $actualProposal);
    self::assertSame(['value' => 'Título revisado pelo Form API'], $actualProposal->getProposedFields()['title']);

    // Load the production Views used by the public Wiki home.
    foreach (['wiki_categories', 'wiki_entries'] as $viewId) {
      $viewFile = dirname(DRUPAL_ROOT) . '/config/sync/views.view.' . $viewId . '.yml';
      View::create(Yaml::parseFile($viewFile))->save();
    }
    $homeBuild = $this->container->get('aculta_portal.domain_purpose')->runInPurpose(
      'wiki',
      fn (): array => WikiController::create($this->container)->home(),
    );
    self::assertSame('wiki-home', $homeBuild['#attributes']['data-portal-ui']);
    self::assertArrayHasKey('categories', $homeBuild);
    self::assertArrayHasKey('recent', $homeBuild);
    self::assertArrayHasKey('changes', $homeBuild);

    // Render the Wiki home with the production theme in this isolated
    // Kernel. Installing the theme also validates its exported settings schema.
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy']);
    $this->container->get('theme_installer')->install(['aculta420']);
    $this->container->get('config.factory')->getEditable('system.theme')
      ->set('default', 'aculta420')
      ->save();
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $this->container->get(DomainNegotiatorInterface::class)
      ->setActiveDomain(Domain::load('wiki420_aculta_org'));
    $this->container->get('theme.manager')->resetActiveTheme();
    $homeHtml = $this->container->get('renderer')->renderRoot($homeBuild);
    self::assertStringContainsString('aculta-context-tabs', (string) $homeHtml);
    self::assertStringContainsString('aculta-wiki-home', (string) $homeHtml);

    Role::load('anonymous')->revokePermission('access content')->save();
    self::assertFalse($accessManager->checkNamedRoute(
      'aculta_portal.wiki_home',
      [],
      new AnonymousUserSession(),
    ));

    $this->container->get('router.builder')->rebuild();
    $listingRoute = $routeProvider->getRouteByName('view.wiki_entries.page_1');
    self::assertSame('/wiki/verbetes', $listingRoute->getPath());
    self::assertCount(1, $routeProvider->getRoutesByPattern('/wiki/verbetes'));
    self::assertSame('wiki', $listingRoute->getOption('_aculta_domain_purpose'));
    self::assertTrue($accessManager->checkNamedRoute(
      'view.wiki_entries.page_1',
      [],
      new UserSession(['uid' => 42, 'roles' => ['authenticated']]),
    ));
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

    Role::create([
      'id' => 'wiki_home_studio_creator_test',
      'label' => 'Wiki home Studio creator test',
      'permissions' => ['create wiki_entry content', 'access aculta portal'],
    ])->save();
    Domain::create([
      'id' => 'conta_aculta_org',
      'hostname' => 'account.example.invalid',
      'name' => 'account',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
      'weight' => 1,
    ])->save();
    $studioCreator = new UserSession([
      'uid' => 16,
      'roles' => ['authenticated', 'wiki_home_studio_creator_test'],
    ]);
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $studioCreator->id());
    $currentUser->setAccount($studioCreator);
    $studioBuild = $controller->home();
    self::assertSame('aculta_studio.we_sativa_start', $studioBuild['collaboration']['#url']->getRouteName());
    self::assertSame('conta_aculta_org', $studioBuild['collaboration']['#url']->getOption('domain')->id());
    self::assertContains('user.permissions', $studioBuild['collaboration']['#access']->getCacheContexts());
    self::assertSame('wiki', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
  }

  /**
   * The pending schema update installs proposal storage on an older site.
   */
  public function testPendingUpdateInstallsMissingProposalEntityStorage(): void {
    $entityDefinitionManager = $this->container->get('entity.definition_update_manager');
    $definition = $entityDefinitionManager->getEntityType('aculta_wiki_proposal');
    self::assertNotNull($definition);

    // Model an already-enabled module whose new content entity was never
    // installed in the site's entity schema.
    $entityDefinitionManager->uninstallEntityType($definition);
    self::assertFalse($this->container->get('database')->schema()->tableExists('aculta_wiki_proposal'));

    $this->container->get('module_handler')->loadInclude('aculta_wiki', 'install');
    aculta_wiki_update_10001();

    $schema = $this->container->get('database')->schema();
    foreach ([
      'aculta_wiki_proposal',
      'aculta_wiki_proposal_field_data',
      'aculta_wiki_proposal_revision',
      'aculta_wiki_proposal_field_revision',
    ] as $table) {
      self::assertTrue($schema->tableExists($table), $table);
    }
    self::assertNotNull($entityDefinitionManager->getFieldStorageDefinition('proposed_fields', 'aculta_wiki_proposal'));
  }

  /**
   * Published participation evidence is scoped to the author's public Wiki.
   */
  public function testPublishedWikiParticipationUsesOnlyPublishedWikiEntries(): void {
    $domainNegotiator = $this->container->get(DomainNegotiatorInterface::class);
    $domainNegotiator->setActiveDomain(Domain::load('aculta_org'));
    $lookup = $this->container->get('aculta_user.published_participation_lookup');
    self::assertNotNull($this->container->get('aculta_portal.domain_purpose')->getDomain('wiki'));

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
    $newAuthor = User::create(['name' => 'published-wiki-author', 'status' => TRUE]);
    $newAuthor->save();
    $otherAuthor = User::create(['name' => 'other-published-wiki-author', 'status' => TRUE]);
    $otherAuthor->save();
    $newAuthorId = (int) $newAuthor->id();
    $otherAuthorId = (int) $otherAuthor->id();
    $this->container->get('current_user')->setAccount($newAuthor);
    self::assertGreaterThan(0, $newAuthorId);
    $firstEvidence = $lookup->lookup($newAuthorId, 'wiki_entry');
    self::assertTrue($firstEvidence->isReliable());
    self::assertFalse($firstEvidence->hasPublishedContribution());
    self::assertTrue($firstEvidence->requiresReview());
    self::assertContains('user', $firstEvidence->getCacheContexts());
    self::assertContains('node_list:wiki_entry', $firstEvidence->getCacheTags());
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    $this->container->get('current_user')->setAccount($otherAuthor);
    $otherAccountEvidence = $lookup->lookup($newAuthorId, 'wiki_entry');
    self::assertSame(0, $otherAccountEvidence->getCacheMaxAge(), 'Evidence queried for another account is not reusable in the current-user cache context.');
    $this->container->get('current_user')->setAccount($newAuthor);
    $wikiDomain = Domain::load('wiki420_aculta_org');
    $wikiDomain->setStatus(FALSE)->save();
    $inactiveDomainEvidence = $lookup->lookup($newAuthorId, 'wiki_entry');
    self::assertFalse($inactiveDomainEvidence->isReliable(), 'An inactive Wiki Domain cannot provide publication evidence.');
    $wikiDomain->setStatus(TRUE)->save();

    Node::create([
      'type' => 'wiki_entry',
      'title' => 'Rascunho não conta como publicação',
      'uid' => $newAuthorId,
      'status' => FALSE,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ])->save();
    self::assertFalse($lookup->lookup($newAuthorId, 'wiki_entry')->hasPublishedContribution());

    Node::create([
      'type' => 'wiki_entry',
      'title' => 'Publicação em outro Domain não conta',
      'uid' => $newAuthorId,
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'aculta_org'],
    ])->save();
    self::assertFalse($lookup->lookup($newAuthorId, 'wiki_entry')->hasPublishedContribution());

    Node::create([
      'type' => 'wiki_entry',
      'title' => 'Publicação de outra autoria não conta',
      'uid' => $otherAuthorId,
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ])->save();
    self::assertFalse($lookup->lookup($newAuthorId, 'wiki_entry')->hasPublishedContribution());

    $published = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Primeiro verbete publicado',
      'uid' => $newAuthorId,
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $published->save();
    $publishedStorage = $this->container->get('entity_type.manager')->getStorage('node');
    $publishedDefault = $publishedStorage->load((int) $published->id());
    self::assertSame($newAuthorId, (int) $publishedDefault->getOwnerId());
    self::assertSame('wiki420_aculta_org', (string) $publishedDefault->get('field_domain_source')->target_id);
    self::assertTrue($publishedDefault->isPublished());
    self::assertTrue($lookup->lookup($newAuthorId, 'wiki_entry')->hasPublishedContribution(), 'A default published Wiki revision is evidence.');
    $published->setNewRevision(TRUE);
    $published->isDefaultRevision(FALSE);
    $published->setUnpublished();
    $published->save();

    $defaultRevision = $this->container->get('entity_type.manager')->getStorage('node')->load((int) $published->id());
    self::assertTrue($defaultRevision->isPublished(), 'The pending revision leaves the published default revision intact.');

    $publishedEvidence = $lookup->lookup($newAuthorId, 'wiki_entry');
    self::assertTrue($publishedEvidence->isReliable());
    self::assertTrue($publishedEvidence->hasPublishedContribution(), 'The existing published default revision remains evidence while a pending revision is non-default.');
    self::assertFalse($publishedEvidence->requiresReview());
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
  }

  /**
   * WE SATIVA keeps its entrypoint in Studio and lists only the author's Nodes.
   */
  public function testWeSativaWorkspaceUsesWikiOwnedAccessAndPurpose(): void {
    ProfileType::create(['id' => 'email_confirmed', 'label' => 'Confirmed account profile'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'profile',
      'bundle' => 'email_confirmed',
      'field_name' => 'field_nickname',
      'label' => 'Nickname',
    ])->save();
    Role::create([
      'id' => 'wiki_workspace_creator_test',
      'label' => 'Wiki workspace creator test',
      'permissions' => ['access content', 'create wiki_entry content', 'edit own wiki_entry content'],
    ])->save();
    $author = User::create([
      'uid' => 17,
      'name' => 'wiki-workspace-author',
      'mail' => 'wiki-workspace-author@example.invalid',
      'status' => TRUE,
      'roles' => ['wiki_workspace_creator_test'],
    ]);
    $author->save();
    $reader = User::create([
      'uid' => 18,
      'name' => 'wiki-workspace-reader',
      'mail' => 'wiki-workspace-reader@example.invalid',
      'status' => TRUE,
    ]);
    $reader->save();
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $author->id());

    $this->container->get('current_user')->setAccount($author);
    $own = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Meu verbete WE SATIVA',
      'uid' => $author->id(),
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_test_grant_uid' => $author->id(),
      'body' => ['value' => 'Conteúdo próprio', 'format' => 'plain_text'],
    ]);
    $own->setOwnerId((int) $author->id());
    $own->save();
    $other = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete de outra pessoa',
      'uid' => $reader->id(),
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_test_grant_uid' => $reader->id(),
      'body' => ['value' => 'Conteúdo alheio', 'format' => 'plain_text'],
    ]);
    $other->setOwnerId((int) $reader->id());
    $other->save();
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    $this->container->get('node.view_all_nodes_memory_cache')->deleteAll();
    $ownAccess = $this->container->get('aculta_portal.domain_purpose')->runInPurpose('wiki', fn () => $own->access('view', $author, TRUE));
    self::assertTrue($ownAccess->isAllowed());
    self::assertSame((string) $author->id(), (string) $own->getOwnerId());

    $routes = $this->container->get('router.route_provider');
    $route = $routes->getRouteByName('aculta_studio.we_sativa');
    self::assertSame('/studio/we-sativa', $route->getPath());
    self::assertSame('account', $route->getOption('_aculta_domain_purpose'));
    $startRoute = $routes->getRouteByName('aculta_studio.we_sativa_start');
    self::assertSame('/studio/we-sativa/inicio', $startRoute->getPath());
    self::assertSame('account', $startRoute->getOption('_aculta_domain_purpose'));
    $nodeAddRoute = $routes->getRouteByName('node.add');
    self::assertSame('/node/add/{node_type}', $nodeAddRoute->getPath());
    self::assertTrue($this->container->get('access_manager')->checkNamedRoute(
      'node.add',
      ['node_type' => 'wiki_entry'],
      $author,
    ));
    $exactEntrypoints = array_filter(
      $routes->getRoutesByPattern('/studio/we-sativa')->all(),
      static fn ($candidate): bool => $candidate->getPath() === '/studio/we-sativa',
    );
    self::assertCount(1, $exactEntrypoints);

    $workspace = $this->container->get('aculta_wiki.contributor_workspace');
    self::assertTrue($workspace->access($author)->isAllowed());
    self::assertFalse($workspace->access($reader)->isAllowed());
    try {
      $workspace->build($reader);
      self::fail('A contributor without creation access or own Wiki work must be denied.');
    }
    catch (AccessDeniedHttpException) {
      self::assertTrue(TRUE);
    }
    $mainDomain = Domain::load('aculta_org');
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain($mainDomain);
    $build = $workspace->build($author);
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    self::assertContains('user', $build['#cache']['contexts']);
    self::assertSame('WE SATIVA', $build['header']['#props']['title']);
    self::assertSame('aculta420:context-header', $build['header']['#component']);
    self::assertSame('Meus verbetes', $build['entries']['#props']['title']);
    self::assertSame('aculta420:content-section', $build['entries']['#component']);
    $renderedItems = $build['entries']['#slots']['body']['items']['#slots']['items'];
    self::assertCount(1, $renderedItems);
    self::assertSame('aculta420:card', $renderedItems[0]['#component']);
    self::assertStringContainsString('Meu verbete WE SATIVA', (string) $renderedItems[0]['#slots']['header']['#title']);
    self::assertSame('entity.node.edit_form', $renderedItems[0]['#slots']['footer']['#url']->getRouteName());
    self::assertSame('wiki420_aculta_org', $renderedItems[0]['#slots']['footer']['#url']->getOption('domain')->id());
    self::assertTrue($this->container->get('aculta_portal.domain_purpose')->runInPurpose('wiki', fn () => $this->container
      ->get('access_manager')->checkNamedRoute('entity.node.edit_form', ['node' => $own->id()], $author)));
    $createLink = $build['header']['#slots']['actions'];
    self::assertSame('node.add', $createLink['#url']->getRouteName());
    self::assertSame(['node_type' => 'wiki_entry'], $createLink['#url']->getRouteParameters());
    self::assertSame('wiki420_aculta_org', $createLink['#url']->getOption('domain')->id());
    self::assertSame('aculta420:content-grid', $build['entries']['#slots']['body']['items']['#component']);
    $startBuild = $workspace->buildStart($author);
    self::assertSame('Seu espaço de contribuição', $startBuild['header']['#props']['title']);
    self::assertSame('aculta420:context-header', $startBuild['header']['#component']);
    self::assertSame('node.add', $startBuild['header']['#slots']['actions']['#url']->getRouteName());
    self::assertSame('aculta420:content-section', $startBuild['guide']['#component']);
    $this->container->get('theme_installer')->install(['aculta420']);
    $this->container->get('config.factory')->getEditable('system.theme')
      ->set('default', 'aculta420')
      ->save();
    $this->container->get('theme.manager')->resetActiveTheme();
    $startHtml = (string) $this->container->get('renderer')->renderRoot($startBuild);
    self::assertStringContainsString('Seu espaço de contribuição', $startHtml);
    self::assertStringContainsString('Criar verbete', $startHtml);
    self::assertStringContainsString('Contribua com cuidado', $startHtml);
    $intentPage = WikiContributionIntentController::create($this->container)->page($own);
    $ownIntentCards = $intentPage['choices']['#slots']['body']['#slots']['items'];
    self::assertCount(1, $ownIntentCards, 'An editable own entry offers only the native full-edit action.');
    self::assertSame('entity.node.edit_form', $ownIntentCards[0]['#slots']['footer']['#url']->getRouteName());
    self::assertSame((string) $own->id(), (string) $ownIntentCards[0]['#slots']['footer']['#url']->getRouteParameters()['node']);
    $contributionLinkBuild = [];
    $this->container->get('aculta_portal.domain_purpose')->runInPurpose('wiki', function () use (&$contributionLinkBuild, $own): void {
      $this->container->get(WikiProposalLinkHooks::class)->entityView(
        $contributionLinkBuild,
        $own,
        $this->createMock(EntityViewDisplayInterface::class),
        'full',
      );
    });
    self::assertSame('Contribuir', (string) $contributionLinkBuild['wiki_proposal_link']['link']['#title']);
    self::assertSame('aculta_wiki.contribution_intent', $contributionLinkBuild['wiki_proposal_link']['link']['#url']->getRouteName());
  }

  /**
   * Owner-scoped entries and proposals continue by separate keyset cursors.
   */
  public function testWeSativaWorkspacePaginatesOwnerEntriesAndProposals(): void {
    ProfileType::create(['id' => 'email_confirmed', 'label' => 'Confirmed account profile'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'profile',
      'bundle' => 'email_confirmed',
      'field_name' => 'field_nickname',
      'label' => 'Nickname',
    ])->save();
    Role::create([
      'id' => 'wiki_workspace_pagination_test',
      'label' => 'Wiki workspace pagination test',
      'permissions' => [
        'access content',
        'create wiki_entry content',
        'edit own wiki_entry content',
        'view own wiki proposals',
      ],
    ])->save();
    $avatar = File::create([
      'uri' => 'public://wiki-workspace-pagination-avatar.png',
      'status' => 0,
    ]);
    $avatar->save();
    $author = User::create([
      'uid' => 17,
      'name' => 'wiki-workspace-pagination-author',
      'mail' => 'wiki-workspace-pagination-author@example.invalid',
      'status' => TRUE,
      'roles' => ['wiki_workspace_pagination_test', 'email_confirmed'],
      'user_picture' => ['target_id' => $avatar->id()],
    ]);
    $author->save();
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $author->id());
    $this->container->get('current_user')->setAccount($author);
    $ownedProposalIds = [];
    for ($index = 1; $index <= 21; $index++) {
      $node = Node::create([
        'type' => 'wiki_entry',
        'title' => sprintf('Verbete paginado %02d', $index),
        'uid' => $author->id(),
        'status' => TRUE,
        'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
        'field_wiki_test_grant_uid' => $author->id(),
      ]);
      $node->save();
      $proposal = WikiProposal::create([
        'uid' => $author->id(),
        'subject' => sprintf('Proposta paginada %02d', $index),
        'target' => ['target_id' => $node->id()],
        'target_revision_id' => $node->getRevisionId(),
        'intent' => 'correction',
        'proposed_text' => 'Correção com fonte.',
        'rationale' => 'Ajuste editorial.',
        'state' => 'submitted',
      ]);
      $proposal->save();
      $ownedProposalIds[] = (int) $proposal->id();
    }
    $otherAuthor = User::create([
      'uid' => 18,
      'name' => 'wiki-workspace-other-author',
      'mail' => 'wiki-workspace-other-author@example.invalid',
      'status' => TRUE,
    ]);
    $otherAuthor->save();
    $otherNode = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete de outra autoria no workspace',
      'uid' => $otherAuthor->id(),
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_test_grant_uid' => $otherAuthor->id(),
    ]);
    $otherNode->save();
    $otherProposal = WikiProposal::create([
      'uid' => $otherAuthor->id(),
      'subject' => 'Proposta privada de outra autoria',
      'target' => ['target_id' => $otherNode->id()],
      'target_revision_id' => $otherNode->getRevisionId(),
      'intent' => 'correction',
      'proposed_text' => 'Esta proposta não deve aparecer no workspace de outra pessoa.',
      'rationale' => 'Isolamento entre autorias.',
      'state' => 'submitted',
    ]);
    $otherProposal->save();
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    $this->container->get('node.view_all_nodes_memory_cache')->deleteAll();
    Domain::create([
      'id' => 'conta_aculta_org',
      'hostname' => 'account.example.invalid',
      'name' => 'account',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
      'weight' => 1,
    ])->save();
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain(Domain::load('aculta_org'));
    $workspace = $this->container->get('aculta_wiki.contributor_workspace');
    self::assertSame(17, (int) $author->id());
    self::assertSame(21, (int) $this->container->get('entity_type.manager')->getStorage('node')->getQuery()->accessCheck(FALSE)->condition('type', 'wiki_entry')->condition('uid', 17)->count()->execute());
    self::assertSame(21, (int) $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')->getQuery()->accessCheck(FALSE)->condition('uid', 17)->count()->execute());
    self::assertTrue($workspace->access($author)->isAllowed(), 'Owner-scoped work or create access should expose the workspace.');
    $requestStack = $this->container->get('request_stack');
    $requestStack->push(Request::create('/studio/we-sativa'));
    try {
      $firstPage = $workspace->build($author);
    }
    finally {
      $requestStack->pop();
    }

    $firstEntries = $firstPage['entries']['#slots']['body']['items']['#slots']['items'];
    $firstProposals = $firstPage['proposals']['#slots']['body']['items']['#slots']['items'];
    self::assertCount(20, $firstEntries);
    self::assertCount(20, $firstProposals);
    $firstProposalIds = array_map(
      static fn (array $item): int => (int) $item['#slots']['header']['#url']->getRouteParameters()['proposal'],
      $firstProposals,
    );
    self::assertNotContains((int) $otherProposal->id(), $firstProposalIds);
    self::assertArrayHasKey('next_page', $firstPage['entries']['#slots']['body']);
    self::assertArrayHasKey('next_page', $firstPage['proposals']['#slots']['body']);
    $entryUrl = $firstPage['entries']['#slots']['body']['next_page']['#url'];
    $proposalUrl = $firstPage['proposals']['#slots']['body']['next_page']['#url'];
    self::assertSame('conta_aculta_org', $entryUrl->getOption('domain')->id());
    self::assertSame('conta_aculta_org', $proposalUrl->getOption('domain')->id());
    $query = [
      'wiki_node_cursor' => $entryUrl->getOption('query')['wiki_node_cursor'],
      'wiki_proposal_cursor' => $proposalUrl->getOption('query')['wiki_proposal_cursor'],
    ];
    self::assertMatchesRegularExpression('/^[0-9]{1,12}-[1-9][0-9]{0,12}$/', $query['wiki_node_cursor']);
    self::assertMatchesRegularExpression('/^[0-9]{1,12}-[1-9][0-9]{0,12}$/', $query['wiki_proposal_cursor']);

    $requestStack->push(Request::create('/studio/we-sativa', 'GET', $query));
    try {
      $secondPage = $workspace->build($author);
    }
    finally {
      $requestStack->pop();
    }
    self::assertCount(1, $secondPage['entries']['#slots']['body']['items']['#slots']['items']);
    $secondProposals = $secondPage['proposals']['#slots']['body']['items']['#slots']['items'];
    self::assertCount(1, $secondProposals);
    $secondProposalId = (int) $secondProposals[0]['#slots']['header']['#url']->getRouteParameters()['proposal'];
    self::assertEqualsCanonicalizing($ownedProposalIds, array_merge($firstProposalIds, [$secondProposalId]));
    self::assertArrayNotHasKey('next_page', $secondPage['entries']['#slots']['body']);
    self::assertArrayNotHasKey('next_page', $secondPage['proposals']['#slots']['body']);
    self::assertSame(['url.query_args:wiki_node_cursor', 'url.query_args:wiki_proposal_cursor'], array_values(array_intersect(
      ['url.query_args:wiki_node_cursor', 'url.query_args:wiki_proposal_cursor'],
      $secondPage['#cache']['contexts'],
    )));

    Role::create([
      'id' => 'wiki_workspace_proposal_no_view_test',
      'label' => 'Wiki proposal submitter without own-view permission test',
      'permissions' => ['access content', 'submit wiki proposals'],
    ])->save();
    $restrictedAuthor = User::create([
      'uid' => 19,
      'name' => 'wiki-workspace-author-without-proposal-view',
      'mail' => 'wiki-workspace-author-without-proposal-view@example.invalid',
      'status' => TRUE,
      'roles' => ['wiki_workspace_proposal_no_view_test'],
    ]);
    $restrictedAuthor->save();
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $restrictedAuthor->id());
    $restrictedProposal = WikiProposal::create([
      'uid' => $restrictedAuthor->id(),
      'subject' => 'Proposta sem view own no workspace',
      'target' => ['target_id' => $node->id()],
      'target_revision_id' => $node->getRevisionId(),
      'intent' => 'correction',
      'proposed_text' => 'O candidato existe, mas Entity Access não autoriza leitura.',
      'rationale' => 'Verificar revalidação de acesso.',
      'state' => 'submitted',
    ]);
    $restrictedProposal->save();
    self::assertFalse($restrictedAuthor->hasPermission('view own wiki proposals'));
    self::assertTrue($workspace->access($restrictedAuthor)->isAllowed());
    $requestStack->push(Request::create('/studio/we-sativa'));
    try {
      $restrictedPage = $workspace->build($restrictedAuthor);
    }
    finally {
      $requestStack->pop();
    }
    self::assertSame('aculta420:empty-state', $restrictedPage['proposals']['#slots']['body']['items']['#component']);
  }

  /**
   * WE SATIVA entry filters follow workflow state and keep proposals separate.
   */
  public function testWeSativaWorkspaceFiltersOwnEntriesByWorkflowState(): void {
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['workflows', 'content_moderation']);
    $workflowFile = dirname(DRUPAL_ROOT) . '/config/sync/workflows.workflow.wiki_editorial.yml';
    Workflow::create(Yaml::parseFile($workflowFile))->save();
    ProfileType::create(['id' => 'email_confirmed', 'label' => 'Confirmed account profile'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'profile',
      'bundle' => 'email_confirmed',
      'field_name' => 'field_nickname',
      'label' => 'Nickname',
    ])->save();
    Role::create([
      'id' => 'wiki_workspace_state_filter_test',
      'label' => 'Wiki workspace state filter test',
      'permissions' => [
        'access content',
        'create wiki_entry content',
        'edit own wiki_entry content',
        'view own unpublished content',
        'use wiki_editorial transition create_new_draft',
        'use wiki_editorial transition draft_to_review',
      ],
    ])->save();
    $author = User::create([
      'uid' => 71,
      'name' => 'wiki-workspace-state-author',
      'status' => TRUE,
      'roles' => ['wiki_workspace_state_filter_test'],
    ]);
    $author->save();
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $author->id());
    $this->container->get('current_user')->setAccount($author);
    Domain::create([
      'id' => 'conta_aculta_org',
      'hostname' => 'account.example.invalid',
      'name' => 'account',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
      'weight' => 1,
    ])->save();

    $expectedDraftIds = [];
    foreach ([
      'draft' => ['Rascunho WE SATIVA', FALSE],
      'review' => ['Em revisão WE SATIVA', FALSE],
      'published' => ['Publicado WE SATIVA', TRUE],
    ] as $state => [$title, $published]) {
      $node = Node::create([
        'type' => 'wiki_entry',
        'title' => $title,
        'uid' => $author->id(),
        'status' => $published,
        'moderation_state' => $state,
        'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
        'field_wiki_test_grant_uid' => $author->id(),
        'field_wiki_test_update_uid' => $state === 'draft' ? $author->id() : NULL,
      ]);
      $node->save();
      if ($state === 'draft') {
        $expectedDraftIds[] = (int) $node->id();
      }
    }
    for ($index = 1; $index <= 22; $index++) {
      $node = Node::create([
        'type' => 'wiki_entry',
        'title' => sprintf('Rascunho paginado %02d', $index),
        'uid' => $author->id(),
        'status' => FALSE,
        'moderation_state' => 'draft',
        'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
        'field_wiki_test_grant_uid' => $author->id(),
        'field_wiki_test_update_uid' => $author->id(),
      ]);
      $node->save();
      $expectedDraftIds[] = (int) $node->id();
    }
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    $this->container->get('node.view_all_nodes_memory_cache')->deleteAll();

    $workspace = $this->container->get('aculta_wiki.contributor_workspace');
    $latestDraft = Node::load((int) end($expectedDraftIds));
    $latestDraftUpdateAccess = $this->container->get('aculta_portal.domain_purpose')
      ->runInPurpose('wiki', fn () => $latestDraft->access('update', $author, TRUE));
    self::assertTrue($latestDraftUpdateAccess->isAllowed());
    $startBuild = $workspace->buildStart($author);
    self::assertSame('Continuar de onde parou', $startBuild['continue']['#props']['title']);
    $continueLink = $startBuild['continue']['#slots']['body']['#slots']['header'];
    self::assertSame('entity.node.edit_form', $continueLink['#url']->getRouteName());
    self::assertStringContainsString('Rascunho paginado 22', (string) $continueLink['#title']);
    $requestStack = $this->container->get('request_stack');
    $cases = [
      'all' => ['count' => 20, 'state' => NULL],
      'in_progress' => ['count' => 20, 'state' => 'draft'],
      'review' => ['count' => 1, 'state' => 'review'],
      'published' => ['count' => 1, 'state' => 'published'],
      'malformed' => ['count' => 20, 'state' => NULL],
    ];
    foreach ($cases as $filter => $expectation) {
      $query = ['wiki_proposal_cursor' => '1720000000-9'];
      if ($filter === 'malformed') {
        $query['wiki_node_state'] = ['review'];
      }
      elseif ($filter !== 'all') {
        $query['wiki_node_state'] = $filter;
      }
      $requestStack->push(Request::create('/studio/we-sativa', 'GET', $query));
      try {
        $build = $workspace->build($author);
      }
      finally {
        $requestStack->pop();
      }

      $items = $build['entries']['#slots']['body']['items']['#slots']['items'];
      self::assertCount($expectation['count'], $items, 'Filter ' . $filter . ' returns the expected page size.');
      if ($expectation['state'] !== NULL) {
        foreach ($items as $item) {
          $nodeId = (int) $item['#slots']['header']['#url']->getRouteParameters()['node'];
          $node = Node::load($nodeId);
          self::assertSame($expectation['state'], $node->get('moderation_state')->value);
        }
      }
      self::assertContains('url.query_args:wiki_node_state', $build['#cache']['contexts']);

      $filterLinks = $build['entries']['#slots']['body']['filters']['#slots']['items']['#items'];
      self::assertCount(4, $filterLinks);
      foreach ($filterLinks as $link) {
        self::assertSame('conta_aculta_org', $link['#url']->getOption('domain')->id());
        self::assertSame('1720000000-9', $link['#url']->getOption('query')['wiki_proposal_cursor']);
        self::assertArrayNotHasKey('wiki_node_cursor', $link['#url']->getOption('query'));
      }
      $activeLinks = array_filter($filterLinks, static fn (array $link): bool => isset($link['#url']->getOption('attributes')['aria-current']));
      self::assertCount(1, $activeLinks);
      $activeLink = reset($activeLinks);
      self::assertSame($filter === 'malformed' ? 'all' : $filter, $activeLink['#url']->getOption('query')['wiki_node_state'] ?? 'all');
    }

    $requestStack->push(Request::create('/studio/we-sativa', 'GET', [
      'wiki_node_state' => 'in_progress',
      'wiki_proposal_cursor' => '1720000000-9',
    ]));
    try {
      $firstDraftPage = $workspace->build($author);
    }
    finally {
      $requestStack->pop();
    }
    $firstDraftItems = $firstDraftPage['entries']['#slots']['body']['items']['#slots']['items'];
    self::assertArrayHasKey('next_page', $firstDraftPage['entries']['#slots']['body']);
    $nextQuery = $firstDraftPage['entries']['#slots']['body']['next_page']['#url']->getOption('query');
    self::assertSame('in_progress', $nextQuery['wiki_node_state']);
    self::assertSame('1720000000-9', $nextQuery['wiki_proposal_cursor']);
    $requestStack->push(Request::create('/studio/we-sativa', 'GET', $nextQuery));
    try {
      $secondDraftPage = $workspace->build($author);
    }
    finally {
      $requestStack->pop();
    }
    $secondDraftItems = $secondDraftPage['entries']['#slots']['body']['items']['#slots']['items'];
    self::assertCount(3, $secondDraftItems);
    $draftIds = [];
    foreach (array_merge($firstDraftItems, $secondDraftItems) as $item) {
      $draftIds[] = (int) $item['#slots']['header']['#url']->getRouteParameters()['node'];
    }
    self::assertCount(23, array_unique($draftIds));
    self::assertEqualsCanonicalizing($expectedDraftIds, $draftIds);
    self::assertArrayNotHasKey('next_page', $secondDraftPage['entries']['#slots']['body']);
  }

  /**
   * Confirmed Wiki authors receive a native draft form without publish access.
   */
  public function testConfirmedContributorBuildsNativeDraftForm(): void {
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['workflows', 'content_moderation']);
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
    $workflowFile = dirname(DRUPAL_ROOT) . '/config/sync/workflows.workflow.wiki_editorial.yml';
    Workflow::create(Yaml::parseFile($workflowFile))->save();
    $wikiFormatFile = dirname(DRUPAL_ROOT) . '/config/sync/filter.format.wiki.yml';
    FilterFormat::create(Yaml::parseFile($wikiFormatFile))->save();
    $wikiEditorFile = dirname(DRUPAL_ROOT) . '/config/sync/editor.editor.wiki.yml';
    Editor::create(Yaml::parseFile($wikiEditorFile))->save();
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'wiki_entry',
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->setComponent('body', ['type' => 'text_textarea_with_summary'])
      ->setComponent('moderation_state', ['type' => 'moderation_state_default'])
      ->save();

    Role::create([
      'id' => 'email_confirmed',
      'label' => 'Confirmed Wiki author test',
      'permissions' => [
        'access content',
        'create wiki_entry content',
        'edit own wiki_entry content',
        'view own unpublished content',
        'use wiki_editorial transition create_new_draft',
        'use wiki_editorial transition draft_to_review',
        'use text format wiki',
      ],
    ])->save();
    $avatar = File::create([
      'uri' => 'public://wiki-form-author-avatar.png',
      'filename' => 'wiki-form-author-avatar.png',
      'status' => TRUE,
    ]);
    $avatar->save();
    $authorEntity = User::create([
      'uid' => 43,
      'name' => 'wiki-form-author',
      'mail' => 'wiki-form-author@example.invalid',
      'status' => TRUE,
      'roles' => ['email_confirmed'],
      'user_picture' => ['target_id' => $avatar->id()],
    ]);
    $authorEntity->save();
    $author = $authorEntity;
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $author->id());
    $this->container->get('current_user')->setAccount($author);
    Domain::create([
      'id' => 'conta_aculta_org',
      'hostname' => 'account.example.invalid',
      'name' => 'Account',
      'scheme' => 'https',
      'weight' => 0,
      'status' => TRUE,
    ])->save();
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain(Domain::load('wiki420_aculta_org'));

    self::assertTrue($this->container->get('aculta_portal.email_confirmation_policy')->isConfirmed($author));
    $participation = $this->container->get('aculta_user.published_participation_lookup')
      ->lookup((int) $author->id(), 'wiki_entry');
    self::assertTrue($participation->isReliable());
    self::assertFalse($participation->hasPublishedContribution());
    self::assertTrue($participation->requiresReview());
    self::assertTrue($this->container->get('access_manager')->checkNamedRoute(
      'node.add',
      ['node_type' => 'wiki_entry'],
      $author,
    ));

    $node = Node::create(['type' => 'wiki_entry', 'uid' => $author->id()]);
    $formObject = $this->container->get('entity_type.manager')->getFormObject('node', 'default');
    $formObject->setEntity($node);
    $formState = new FormState();
    $formState->setFormObject($formObject);
    $formBuilder = $this->container->get('form_builder');
    $form = $formBuilder->buildForm($formObject, $formState);
    self::assertSame('link', $form['wiki_workspace_return']['#type']);
    self::assertSame('aculta_studio.we_sativa', $form['wiki_workspace_return']['#url']->getRouteName());
    self::assertSame('conta_aculta_org', $form['wiki_workspace_return']['#url']->getOption('domain')->id());
    self::assertArrayHasKey('title', $form);
    self::assertArrayHasKey('body', $form);
    self::assertArrayHasKey('actions', $form);
    self::assertArrayHasKey('submit', $form['actions']);
    self::assertArrayHasKey('check_similar_titles', $form['actions']);
    self::assertSame('Salvar rascunho', (string) $form['actions']['submit']['#value']);
    self::assertSame('Enviar para revisão', (string) $form['actions']['submit_review']['#value']);
    self::assertNotEmpty($form['actions']['submit_review']['#submit'], var_export($form['actions']['submit_review']['#submit'], TRUE));
    self::assertSame('Explique o tema com linguagem clara. Inclua fontes verificáveis no campo Referências.', (string) $form['body']['#description']);
    self::assertArrayHasKey('wiki', $form['body']['widget'][0]['format']['format']['#options']);
    $wikiEditor = Editor::load('wiki');
    self::assertInstanceOf(Editor::class, $wikiEditor);
    self::assertSame('ckeditor5', $wikiEditor->getEditor());
    self::assertFalse($wikiEditor->getImageUploadSettings()['status']);
    self::assertArrayHasKey('moderation_state', $form);
    $moderationWidget = $form['moderation_state']['widget'][0]['state'];
    self::assertArrayHasKey('draft', $moderationWidget['#options']);
    self::assertArrayHasKey('review', $moderationWidget['#options']);
    self::assertArrayNotHasKey('published', $moderationWidget['#options']);
    self::assertSame('draft', $moderationWidget['#default_value']);

    $validTransitions = $this->container->get('content_moderation.state_transition_validation')
      ->getValidTransitions($node, $author);
    self::assertArrayHasKey('create_new_draft', $validTransitions);
    self::assertArrayHasKey('draft_to_review', $validTransitions);
    self::assertArrayNotHasKey('review_to_published', $validTransitions);
    self::assertFalse($author->hasPermission('use wiki_editorial transition review_to_published'));

    $callback = $this->container->get('callable_resolver')->getCallableFromDefinition(
      $form['actions']['check_similar_titles']['#submit'][0],
    );
    self::assertIsCallable($callback, 'The native form resolves the optional check through the autowired Hook service.');

    $checkNode = Node::create(['type' => 'wiki_entry', 'uid' => $author->id()]);
    $checkFormObject = $this->container->get('entity_type.manager')->getFormObject('node', 'default');
    $checkFormObject->setEntity($checkNode);
    $checkFormState = new FormState();
    $checkFormState->setFormObject($checkFormObject);
    $checkForm = $formBuilder->buildForm($checkFormObject, $checkFormState);
    $checkFormState->setValue($checkForm['title']['widget'][0]['value']['#parents'], 'Cannabis medicinal');
    $checkFormState->setTriggeringElement($checkForm['actions']['check_similar_titles']);
    $formBuilder->submitForm($checkFormObject, $checkFormState);
    self::assertSame([], $checkFormState->getErrors());
    self::assertNull($checkNode->id(), 'The title suggestion action does not save the new Node.');
    self::assertSame('Cannabis medicinal', $checkFormState->get('aculta_wiki.title_suggestions')['term']);

    $formState->setValue($form['title']['widget'][0]['value']['#parents'], 'Verbete salvo como rascunho');
    $formState->setValue($form['body']['widget'][0]['value']['#parents'], 'Texto inicial com fonte.');
    $formState->setValue($form['body']['widget'][0]['format']['format']['#parents'], 'wiki');
    $formState->setValue($moderationWidget['#parents'], 'draft');
    $statusParents = $form['status']['widget'][0]['value']['#parents'] ?? $form['status']['#parents'] ?? ['status'];
    $formState->setValue($statusParents, 1);
    $formState->setTriggeringElement($form['actions']['submit']);
    $formBuilder->submitForm($formObject, $formState);

    self::assertSame([], $formState->getErrors());
    $savedEntity = $formObject->getEntity();
    self::assertInstanceOf(Node::class, $savedEntity);
    self::assertNotEmpty($savedEntity->id());
    $savedNode = Node::load($savedEntity->id());
    self::assertInstanceOf(Node::class, $savedNode);
    self::assertSame('wiki420_aculta_org', $savedNode->get('field_domain_source')->target_id);
    $explicitDomainNode = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Domain explícito preservado',
      'field_domain_source' => ['target_id' => 'aculta_org'],
    ]);
    $explicitDomainNode->save();
    self::assertSame('aculta_org', $explicitDomainNode->get('field_domain_source')->target_id);
    self::assertSame('Verbete salvo como rascunho', $savedNode->label());
    self::assertSame((string) $author->id(), (string) $savedNode->getOwnerId());
    self::assertSame('draft', $savedNode->get('moderation_state')->value);
    self::assertFalse($savedNode->isPublished());

    $reviewNode = Node::create(['type' => 'wiki_entry', 'uid' => $author->id()]);
    $reviewFormObject = $this->container->get('entity_type.manager')->getFormObject('node', 'default');
    $reviewFormObject->setEntity($reviewNode);
    $reviewFormState = new FormState();
    $reviewFormState->setFormObject($reviewFormObject);
    $reviewForm = $formBuilder->buildForm($reviewFormObject, $reviewFormState);
    $reviewFormState->setValue($reviewForm['title']['widget'][0]['value']['#parents'], 'Verbete enviado para revisão');
    $reviewFormState->setValue($reviewForm['body']['widget'][0]['value']['#parents'], 'Texto com fontes para avaliação.');
    $reviewFormState->setValue($reviewForm['body']['widget'][0]['format']['format']['#parents'], 'wiki');
    $reviewFormState->setValue($reviewForm['moderation_state']['widget'][0]['state']['#parents'], 'draft');
    $reviewFormState->setTriggeringElement($reviewForm['actions']['submit_review']);
    $formBuilder->submitForm($reviewFormObject, $reviewFormState);
    self::assertSame([], $reviewFormState->getErrors());
    $savedReviewNode = Node::load($reviewFormObject->getEntity()->id());
    self::assertSame('review', $savedReviewNode->get('moderation_state')->value);
    self::assertFalse($savedReviewNode->isPublished());
    $afterReviewSubmission = $this->container->get('aculta_user.published_participation_lookup')
      ->lookup((int) $author->id(), 'wiki_entry');
    self::assertTrue($afterReviewSubmission->isReliable());
    self::assertFalse($afterReviewSubmission->hasPublishedContribution());
    self::assertTrue($afterReviewSubmission->requiresReview());

    $draftRevisionId = (int) $savedNode->getRevisionId();
    $editFormObject = $this->container->get('entity_type.manager')->getFormObject('node', 'edit');
    $editFormObject->setEntity($savedNode);
    $editFormState = new FormState();
    $editFormState->setFormObject($editFormObject);
    $editForm = $formBuilder->buildForm($editFormObject, $editFormState);
    $editModerationWidget = $editForm['moderation_state']['widget'][0]['state'];
    self::assertArrayHasKey('review', $editModerationWidget['#options']);
    self::assertArrayNotHasKey('published', $editModerationWidget['#options']);
    self::assertArrayHasKey('preview', $editForm['actions']);

    $previewNode = Node::load((int) $savedNode->id());
    self::assertInstanceOf(Node::class, $previewNode);
    $previewFormObject = $this->container->get('entity_type.manager')->getFormObject('node', 'edit');
    $previewFormObject->setEntity($previewNode);
    $previewFormState = new FormState();
    $previewFormState->setFormObject($previewFormObject);
    $previewForm = $formBuilder->buildForm($previewFormObject, $previewFormState);
    $previewFormState->setTriggeringElement($previewForm['actions']['preview']);
    $previewFormObject->submitForm($previewForm, $previewFormState);
    $previewFormObject->getEntity()->setTitle('Prévia sem salvar');
    $previewFormObject->getEntity()->set('body', ['value' => 'Texto de prévia com fonte.', 'format' => 'plain_text']);
    $previewFormObject->getEntity()->set('moderation_state', 'draft');
    $previewFormObject->getEntity()->setOwnerId((int) $author->id());
    $previewFormObject->preview($previewForm, $previewFormState);

    self::assertSame([], $previewFormState->getErrors());
    self::assertSame('entity.node.preview', $previewFormState->getRedirect()->getRouteName());
    self::assertSame((string) $previewNode->uuid(), $previewFormState->getRedirect()->getRouteParameters()['node_preview']);
    $previewState = $this->container->get('tempstore.private')->get('node_preview')->get($previewNode->uuid());
    self::assertInstanceOf(FormState::class, $previewState);
    $previewEntity = $previewState->getFormObject()->getEntity();
    self::assertSame('Prévia sem salvar', $previewEntity->label());
    self::assertSame((string) $author->id(), (string) $previewEntity->getOwnerId());
    $previewAccess = $this->container->get('access_check.node.preview')->access($author, $previewEntity);
    self::assertTrue($previewAccess->isAllowed(), sprintf(
      'Preview access denied for owner %s / author %s.',
      $previewEntity->getOwnerId(),
      $author->id(),
    ));
    $otherAuthor = new UserSession(['uid' => 44, 'roles' => ['authenticated', 'email_confirmed']]);
    self::assertFalse($this->container->get('access_check.node.preview')->access($otherAuthor, $previewEntity)->isAllowed());
    $unchangedNode = Node::load((int) $savedNode->id());
    self::assertSame('Verbete salvo como rascunho', $unchangedNode->label());
    self::assertSame($draftRevisionId, (int) $unchangedNode->getRevisionId());

    $editFormState->setValue($editForm['title']['widget'][0]['value']['#parents'], 'Verbete enviado para revisão');
    $editFormState->setValue($editForm['body']['widget'][0]['value']['#parents'], 'Texto inicial com fonte.');
    $editFormState->setValue($editForm['body']['widget'][0]['format']['format']['#parents'], 'wiki');
    $editFormState->setValue($editModerationWidget['#parents'], 'review');
    $editFormState->setTriggeringElement($editForm['actions']['submit']);
    $formBuilder->submitForm($editFormObject, $editFormState);

    self::assertSame([], $editFormState->getErrors());
    $reviewRevision = $editFormObject->getEntity();
    self::assertGreaterThan($draftRevisionId, (int) $reviewRevision->getRevisionId());
    self::assertSame('review', $reviewRevision->get('moderation_state')->value);
    self::assertFalse($reviewRevision->isPublished());

    Role::create([
      'id' => 'wiki_first_publication_editor_test',
      'label' => 'Wiki first publication editor test',
      'permissions' => [
        'edit any wiki_entry content',
        'view any unpublished content',
        'view wiki_entry revisions',
        'use wiki_editorial transition review_to_published',
        'use text format wiki',
      ],
    ])->save();
    $editor = User::create([
      'uid' => 45,
      'name' => 'wiki-first-publication-editor',
      'mail' => 'wiki-first-publication-editor@example.invalid',
      'status' => TRUE,
      'roles' => ['wiki_first_publication_editor_test'],
    ]);
    $editor->save();
    $this->container->get('current_user')->setAccount($editor);
    $pendingNode = Node::load((int) $savedReviewNode->id());
    $publishFormObject = $this->container->get('entity_type.manager')->getFormObject('node', 'edit');
    $publishFormObject->setEntity($pendingNode);
    $publishFormState = new FormState();
    $publishFormState->setFormObject($publishFormObject);
    $publishForm = $formBuilder->buildForm($publishFormObject, $publishFormState);
    $publishModerationWidget = $publishForm['moderation_state']['widget'][0]['state'];
    self::assertArrayHasKey('published', $publishModerationWidget['#options']);
    $publishFormState->setValue($publishForm['title']['widget'][0]['value']['#parents'], 'Verbete publicado por editor');
    $publishFormState->setValue($publishForm['body']['widget'][0]['value']['#parents'], 'Texto revisado pelo editor.');
    $publishFormState->setValue($publishForm['body']['widget'][0]['format']['format']['#parents'], 'wiki');
    $publishFormState->setValue($publishModerationWidget['#parents'], 'published');
    $publishFormState->setTriggeringElement($publishForm['actions']['submit']);
    $formBuilder->submitForm($publishFormObject, $publishFormState);
    self::assertSame([], $publishFormState->getErrors());
    $publishedByEditor = Node::load((int) $savedReviewNode->id());
    self::assertSame('published', $publishedByEditor->get('moderation_state')->value);
    self::assertTrue($publishedByEditor->isPublished());

    $this->container->get('current_user')->setAccount($author);
    $afterFirstPublication = $this->container->get('aculta_user.published_participation_lookup')
      ->lookup((int) $author->id(), 'wiki_entry');
    self::assertTrue($afterFirstPublication->isReliable());
    self::assertTrue($afterFirstPublication->hasPublishedContribution());
    self::assertFalse($afterFirstPublication->requiresReview());
    $stillPending = Node::load((int) $reviewRevision->id());
    $authorTransitions = $this->container->get('content_moderation.state_transition_validation')
      ->getValidTransitions($stillPending, $author);
    self::assertArrayNotHasKey('review_to_published', $authorTransitions);

    $subsequentEntry = Node::create(['type' => 'wiki_entry', 'uid' => $author->id()]);
    $subsequentFormObject = $this->container->get('entity_type.manager')->getFormObject('node', 'default');
    $subsequentFormObject->setEntity($subsequentEntry);
    $subsequentFormState = new FormState();
    $subsequentFormState->setFormObject($subsequentFormObject);
    $subsequentForm = $formBuilder->buildForm($subsequentFormObject, $subsequentFormState);
    $subsequentModerationWidget = $subsequentForm['moderation_state']['widget'][0]['state'];
    self::assertArrayHasKey('review', $subsequentModerationWidget['#options']);
    self::assertArrayNotHasKey('published', $subsequentModerationWidget['#options']);
    $subsequentTransitions = $this->container->get('content_moderation.state_transition_validation')
      ->getValidTransitions($subsequentEntry, $author);
    self::assertArrayHasKey('draft_to_review', $subsequentTransitions);
    self::assertArrayNotHasKey('review_to_published', $subsequentTransitions);
    $subsequentFormState->setValue($subsequentForm['title']['widget'][0]['value']['#parents'], 'Segundo verbete segue para revisão');
    $subsequentFormState->setValue($subsequentForm['body']['widget'][0]['value']['#parents'], 'Texto para revisão editorial.');
    $subsequentFormState->setValue($subsequentForm['body']['widget'][0]['format']['format']['#parents'], 'wiki');
    $subsequentFormState->setValue($subsequentModerationWidget['#parents'], 'draft');
    $subsequentFormState->setTriggeringElement($subsequentForm['actions']['submit_review']);
    $formBuilder->submitForm($subsequentFormObject, $subsequentFormState);
    self::assertSame([], $subsequentFormState->getErrors());
    $subsequentSavedNode = Node::load((int) $subsequentFormObject->getEntity()->id());
    self::assertSame('wiki420_aculta_org', $subsequentSavedNode->get('field_domain_source')->target_id);
    self::assertSame('review', $subsequentSavedNode->get('moderation_state')->value);
    self::assertFalse($subsequentSavedNode->isPublished());
  }

  /**
   * A real Node Form API submission stores the lead image privately on draft.
   */
  public function testNativeNodeFormSubmitsLeadImageWithoutPublishingDraftMedia(): void {
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['workflows', 'content_moderation']);
    $workflowFile = dirname(DRUPAL_ROOT) . '/config/sync/workflows.workflow.wiki_editorial.yml';
    Workflow::create(Yaml::parseFile($workflowFile))->save();
    ProfileType::create(['id' => 'email_confirmed', 'label' => 'Confirmed account profile'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'profile',
      'bundle' => 'email_confirmed',
      'field_name' => 'field_nickname',
      'label' => 'Nickname',
    ])->save();
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'wiki_entry',
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->setComponent('body', ['type' => 'text_textarea_with_summary'])
      ->setComponent('moderation_state', ['type' => 'moderation_state_default'])
      ->setComponent(WikiLeadImageUploader::FIELD, ['type' => 'entity_reference_autocomplete'])
      ->save();

    Role::create([
      'id' => 'email_confirmed',
      'label' => 'Confirmed Wiki image author test',
      'permissions' => [
        'access content',
        'create wiki_entry content',
        'edit own wiki_entry content',
        'view own unpublished content',
        'use wiki_editorial transition create_new_draft',
        'use wiki_editorial transition draft_to_review',
      ],
    ])->save();
    $author = User::create([
      'name' => 'wiki-image-form-author',
      'mail' => 'wiki-image-form-author@example.invalid',
      'status' => TRUE,
      'roles' => ['email_confirmed'],
    ]);
    $author->save();
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $author->id());
    $this->container->get('current_user')->setAccount($author);
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain(Domain::load('wiki420_aculta_org'));

    $file = $this->createTemporaryPrivateImage((int) $author->id());
    $node = Node::create(['type' => 'wiki_entry', 'uid' => $author->id()]);
    $formObject = $this->container->get('entity_type.manager')->getFormObject('node', 'default');
    $formObject->setEntity($node);
    $formState = new FormState();
    $formState->setFormObject($formObject);
    $formBuilder = $this->container->get('form_builder');
    $form = $formBuilder->buildForm($formObject, $formState);

    self::assertArrayHasKey(WikiLeadImageUploader::TAB, $form);
    self::assertSame('managed_file', $form[WikiLeadImageUploader::TAB][WikiLeadImageUploader::FILE]['#type']);
    $formState->setValue($form['title']['widget'][0]['value']['#parents'], 'Rascunho com imagem real');
    $formState->setValue($form['body']['widget'][0]['value']['#parents'], 'Texto inicial com fonte.');
    $formState->setValue($form['body']['widget'][0]['format']['#parents'], 'plain_text');
    $formState->setValue($form['moderation_state']['widget'][0]['state']['#parents'], 'draft');
    $formState->setValue($form[WikiLeadImageUploader::TAB][WikiLeadImageUploader::FILE]['#parents'], ['fids' => (string) $file->id()]);
    $formState->setValue($form[WikiLeadImageUploader::TAB][WikiLeadImageUploader::ALT]['#parents'], 'Pessoa caminhando por uma trilha');
    $formState->setTriggeringElement($form['actions']['submit']);
    $formBuilder->submitForm($formObject, $formState);

    self::assertSame([], $formState->getErrors());
    $savedNode = Node::load((int) $formObject->getEntity()->id());
    self::assertInstanceOf(Node::class, $savedNode);
    self::assertSame('draft', $savedNode->get('moderation_state')->value);
    self::assertFalse($savedNode->isPublished());
    $media = $savedNode->get(WikiLeadImageUploader::FIELD)->entity;
    self::assertInstanceOf(Media::class, $media);
    self::assertFalse($media->isPublished());
    self::assertSame('Pessoa caminhando por uma trilha', $media->get('field_media_image')->alt);
    self::assertFalse($media->access('view', new AnonymousUserSession()));
    self::assertStringStartsWith('private://wiki/imagens/', $file->getFileUri());
    self::assertFalse($file->access('download', new AnonymousUserSession()));
    $fileStorage = $this->container->get('entity_type.manager')->getStorage('file');
    $fileStorage->resetCache([(int) $file->id()]);
    self::assertFalse($fileStorage->load($file->id())->isTemporary());
  }

  /**
   * A stale WE SATIVA edit cannot overwrite a newer default revision.
   */
  public function testConcurrentWikiEditFormRejectsStaleRevision(): void {
    ProfileType::create(['id' => 'email_confirmed', 'label' => 'Confirmed account profile'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'profile',
      'bundle' => 'email_confirmed',
      'field_name' => 'field_nickname',
      'label' => 'Nickname',
    ])->save();
    Role::create([
      'id' => 'email_confirmed',
      'label' => 'Confirmed Wiki author test',
      'permissions' => ['access content', 'edit own wiki_entry content'],
    ])->save();
    $author = User::create([
      'name' => 'wiki-concurrent-author',
      'status' => TRUE,
      'roles' => ['email_confirmed'],
    ]);
    $author->save();
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $author->id());
    $this->container->get('current_user')->setAccount($author);
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain(Domain::load('wiki420_aculta_org'));

    $nodeType = NodeType::load('wiki_entry');
    self::assertInstanceOf(NodeType::class, $nodeType);
    $nodeType->setNewRevision(TRUE);
    $nodeType->save();
    $entry = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Versão aberta pela autora',
      'status' => TRUE,
      'uid' => $author->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $entry->setNewRevision(TRUE);
    $entry->save();
    $openedRevisionId = (int) $entry->getRevisionId();

    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'wiki_entry',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('body', ['type' => 'text_textarea_with_summary'])->save();
    $formObject = $this->container->get('entity_type.manager')->getFormObject('node', 'edit');
    $formObject->setEntity(Node::load($entry->id()));
    $formState = new FormState();
    $formState->setFormObject($formObject);
    $formBuilder = $this->container->get('form_builder');
    $form = $formBuilder->buildForm($formObject, $formState);
    self::assertSame($openedRevisionId, (int) $form[WikiLeadImageUploader::EXPECTED_REVISION]['#value']);

    $concurrent = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($entry->id());
    self::assertInstanceOf(Node::class, $concurrent);
    $concurrent->setNewRevision(TRUE);
    $concurrent->setTitle('Versão mais recente de outra sessão');
    $concurrent->save();
    $latestRevisionId = (int) $concurrent->getRevisionId();

    $formState->setValue($form['title']['widget'][0]['value']['#parents'], 'Alteração desatualizada');
    $formState->setValue([WikiLeadImageUploader::EXPECTED_REVISION], $openedRevisionId);
    $formState->setTriggeringElement($form['actions']['submit']);
    $formBuilder->submitForm($formObject, $formState);

    self::assertArrayHasKey('title', $formState->getErrors());
    $current = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($entry->id());
    self::assertInstanceOf(Node::class, $current);
    self::assertSame($latestRevisionId, (int) $current->getRevisionId());
    self::assertSame('Versão mais recente de outra sessão', $current->label());

    // Also exercise the narrow race after Form API validation but before the
    // save handler. The transactional row lock must reject this second stale
    // submission even though it passed the earlier validation phase.
    $raceBaseRevisionId = (int) $current->getRevisionId();
    $raceFormObject = $this->container->get('entity_type.manager')->getFormObject('node', 'edit');
    $raceFormObject->setEntity($current);
    $raceFormState = new FormState();
    $raceFormState->setFormObject($raceFormObject);
    $raceForm = $formBuilder->buildForm($raceFormObject, $raceFormState);
    $raceFormState->setValue($raceForm['title']['widget'][0]['value']['#parents'], 'Corrida desatualizada');
    $raceFormState->setValue([WikiLeadImageUploader::EXPECTED_REVISION], $raceBaseRevisionId);
    $uploader = $this->container->get('aculta_wiki.wiki_lead_image_uploader');
    $uploader->validateExpectedRevision($raceForm, $raceFormState);
    self::assertSame([], $raceFormState->getErrors());

    $lastWriter = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($entry->id());
    self::assertInstanceOf(Node::class, $lastWriter);
    $lastWriter->setNewRevision(TRUE);
    $lastWriter->setTitle('Versão salva entre validação e submit');
    $lastWriter->save();
    $lastRevisionId = (int) $lastWriter->getRevisionId();

    $raceFormObject->submitForm($raceForm, $raceFormState);
    $uploader->saveNode($raceForm, $raceFormState);
    self::assertArrayHasKey('title', $raceFormState->getErrors());
    self::assertTrue($raceFormState->isRebuilding());
    $current = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($entry->id());
    self::assertInstanceOf(Node::class, $current);
    self::assertSame($lastRevisionId, (int) $current->getRevisionId());
    self::assertSame('Versão salva entre validação e submit', $current->label());
  }

  /**
   * Real Mail Confirmer completion grants the configured Wiki create access.
   */
  public function testRegistrationConfirmationUnlocksWikiAuthoringPermission(): void {
    $this->installEntitySchema('aculta_mail_confirmer');
    $this->installConfig(['aculta_mail_confirmer']);
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
    Role::create([
      'id' => 'email_confirmed',
      'label' => 'Confirmed email test role',
      'permissions' => ['access content', 'create wiki_entry content'],
    ])->save();
    $author = User::create([
      'uid' => 2,
      'name' => 'wiki-confirmation-author',
      'mail' => 'wiki-confirmation-author@example.invalid',
      'status' => TRUE,
    ]);
    $author->save();

    $policy = $this->container->get('aculta_portal.email_confirmation_policy');
    $nodeAccess = $this->container->get('entity_type.manager')->getAccessControlHandler('node');
    self::assertFalse($policy->isConfirmed($author));
    self::assertFalse($nodeAccess->createAccess('wiki_entry', $author, [], TRUE)->isAllowed());

    $manager = $this->container->get('aculta_mail_confirmer');
    $confirmation = $manager->createConfirmation((string) $author->getEmail());
    $confirmation->setRealm('aculta_registration');
    $confirmation->setProperty('user', (int) $author->id());
    $confirmation->save();
    self::assertTrue($manager->confirmWithHash($confirmation, $manager->getHash($confirmation)));
    $confirmation->save();

    $confirmedAuthor = User::load($author->id());
    self::assertInstanceOf(User::class, $confirmedAuthor);
    self::assertTrue($policy->isConfirmed($confirmedAuthor));
    self::assertTrue($confirmedAuthor->hasRole('email_confirmed'));
    self::assertTrue($confirmedAuthor->hasPermission('create wiki_entry content'));
    // A separate request has a fresh handler cache after the confirmation.
    $nodeAccess->resetCache();
    $createAccess = $nodeAccess->createAccess('wiki_entry', $confirmedAuthor, [], TRUE);
    self::assertTrue($createAccess->isAllowed());
    self::assertContains('user.permissions', $createAccess->getCacheContexts());
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
    $firstHome = $controller->home();
    $firstRecentIds = array_map(
      static fn (array $row): int => (int) $row['title']['#url']->getRouteParameters()['node'],
      $firstHome['changes']['#items'],
    );
    self::assertSame([$firstNode], $firstRecentIds);
    self::assertContains('user.node_grants:view', $firstHome['changes']['#cache']['contexts']);

    $currentUser->setAccount($secondReader);
    $secondSearch = $controller->search(Request::create('/wiki/busca', 'GET', ['q' => 'Cannabis']));
    self::assertSame([$secondNode], array_map('intval', array_keys($secondSearch['results'])));
    $secondHome = $controller->home();
    $secondRecentIds = array_map(
      static fn (array $row): int => (int) $row['title']['#url']->getRouteParameters()['node'],
      $secondHome['changes']['#items'],
    );
    self::assertSame([$secondNode], $secondRecentIds);

    $currentUser->setAccount($firstReader);
    $emptyBuild = $controller->search(Request::create('/wiki/busca'));
    self::assertArrayNotHasKey('results', $emptyBuild);
    self::assertContains('user.node_grants:view', $emptyBuild['#cache']['contexts']);
    $negativeBuild = $controller->search(Request::create('/wiki/busca', 'GET', ['q' => 'termo-inexistente']));
    self::assertArrayNotHasKey('results', $negativeBuild);
    self::assertContains('user.node_grants:view', $negativeBuild['#cache']['contexts']);
    $malformedBuild = $controller->search(Request::create('/wiki/busca', 'GET', ['q' => ['termo']]));
    self::assertArrayNotHasKey('results', $malformedBuild);
    self::assertContains('user.node_grants:view', $malformedBuild['#cache']['contexts']);
  }

  /**
   * Title suggestions reveal only accessible published entries and never save.
   */
  public function testTitleSuggestionsAreOptionalAndRespectNodeAccess(): void {
    $reader = new UserSession(['uid' => 2, 'roles' => ['authenticated']]);
    $visibleId = $this->createSearchEntry('Cannabis medicinal e redução de danos', (int) $reader->id());
    $hiddenGrantId = $this->createSearchEntry('Cannabis medicinal em pesquisa restrita', 3);
    $unpublishedId = $this->createSearchEntry('Cannabis medicinal em rascunho', (int) $reader->id());
    $unpublished = Node::load($unpublishedId);
    $unpublished->setUnpublished();
    $unpublished->save();
    $foreignDomain = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Cannabis medicinal em outro domínio',
      'uid' => $reader->id(),
      'status' => TRUE,
      'field_domain_source' => ['target_id' => 'aculta_org'],
      'field_wiki_test_grant_uid' => $reader->id(),
    ]);
    $foreignDomain->save();
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    $this->container->get('node.view_all_nodes_memory_cache')->deleteAll();
    $this->container->get('current_user')->setAccount($reader);
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain(Domain::load('wiki420_aculta_org'));

    $newNode = Node::create(['type' => 'wiki_entry', 'uid' => $reader->id()]);
    $formObject = $this->entityForm($newNode, static function (): void {
      throw new \LogicException('The optional title check must never save the Node.');
    });
    $formState = new FormState();
    $formState->setFormObject($formObject);
    $form = [
      'title' => ['#type' => 'container', '#parents' => ['title'], '#value' => 'Cannabis medicinal'],
      'actions' => ['submit' => ['#type' => 'submit']],
    ];
    $suggestions = $this->container->get(WikiEntryTitleSuggestions::class);
    $suggestions->formAlter($form, $formState);
    self::assertArrayHasKey('check_similar_titles', $form['actions']);
    $callback = $this->container->get('callable_resolver')->getCallableFromDefinition(
      $form['actions']['check_similar_titles']['#submit'][0],
    );
    $callback($form, $formState);
    self::assertTrue($formState->isRebuilding());
    self::assertSame([], $formState->getErrors());

    $suggestions->formAlter($form, $formState);
    self::assertArrayHasKey('similar_titles', $form);
    $items = $form['similar_titles']['list']['#items'];
    self::assertCount(1, $items);
    self::assertSame('Cannabis medicinal e redução de danos', (string) $items[0]['#title']);
    self::assertSame($visibleId, (int) $items[0]['#url']->getRouteParameters()['node']);
    self::assertNotSame($hiddenGrantId, (int) $items[0]['#url']->getRouteParameters()['node']);
    self::assertNotSame($unpublishedId, (int) $items[0]['#url']->getRouteParameters()['node']);
    self::assertNotSame((int) $foreignDomain->id(), (int) $items[0]['#url']->getRouteParameters()['node']);
    self::assertNull($newNode->id());
  }

  /**
   * Proposal submission is private, revision-based, and Wiki-purpose bound.
   */
  public function testProposalSubmissionUsesPrivateRevisionedEntityAndAccess(): void {
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy']);
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['workflows', 'content_moderation']);
    $workflowFile = dirname(DRUPAL_ROOT) . '/config/sync/workflows.workflow.wiki_editorial.yml';
    Workflow::create(Yaml::parseFile($workflowFile))->save();
    Vocabulary::create(['vid' => 'wiki_category', 'name' => 'Wiki categories'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_wiki_references',
      'type' => 'link',
      'settings' => ['title' => 1, 'link_type' => 16],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'field_wiki_references',
      'label' => 'References',
    ])->save();
    foreach ([
      'field_wiki_categories' => ['target_type' => 'taxonomy_term', 'bundles' => ['wiki_category' => 'wiki_category']],
      'field_wiki_related' => ['target_type' => 'node', 'bundles' => ['wiki_entry' => 'wiki_entry']],
    ] as $fieldName => $settings) {
      FieldStorageConfig::create([
        'entity_type' => 'node',
        'field_name' => $fieldName,
        'type' => 'entity_reference',
        'settings' => ['target_type' => $settings['target_type']],
        'cardinality' => -1,
      ])->save();
      FieldConfig::create([
        'entity_type' => 'node',
        'bundle' => 'wiki_entry',
        'field_name' => $fieldName,
        'label' => $fieldName,
        'settings' => [
          'handler' => 'default:' . $settings['target_type'],
          'handler_settings' => ['target_bundles' => $settings['bundles']],
        ],
      ])->save();
    }
    Role::create([
      'id' => 'wiki_proposal_submitter_test',
      'label' => 'Wiki proposal submitter test',
      'permissions' => ['submit wiki proposals', 'view own wiki proposals'],
    ])->save();
    Role::create([
      'id' => 'wiki_proposal_reviewer_test',
      'label' => 'Wiki proposal reviewer test',
      'permissions' => [
        'view any wiki proposals',
        'moderate wiki proposals',
      ],
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
    $currentCategory = Term::create(['vid' => 'wiki_category', 'name' => 'Categoria atual']);
    $currentCategory->save();
    $proposedCategory = Term::create(['vid' => 'wiki_category', 'name' => 'Categoria proposta']);
    $proposedCategory->save();
    $currentRelated = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete relacionado atual',
      'uid' => 3,
      'status' => TRUE,
      'moderation_state' => 'published',
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_test_grant_uid' => 2,
      'body' => ['value' => 'Texto relacionado atual', 'format' => 'plain_text'],
    ]);
    $currentRelated->save();
    $proposedRelated = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete relacionado proposto',
      'uid' => 3,
      'status' => TRUE,
      'moderation_state' => 'published',
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_test_grant_uid' => 2,
      'body' => ['value' => 'Texto relacionado proposto', 'format' => 'plain_text'],
    ]);
    $proposedRelated->save();
    $target = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete público para proposta',
      'uid' => 3,
      'status' => TRUE,
      'moderation_state' => 'published',
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_test_grant_uid' => 2,
      'field_wiki_references' => [['uri' => 'https://example.invalid/current', 'title' => 'Fonte atual']],
      'field_wiki_categories' => [['target_id' => $currentCategory->id()]],
      'field_wiki_related' => [['target_id' => $currentRelated->id()]],
      'body' => ['value' => 'Conteúdo publicado', 'format' => 'plain_text'],
    ]);
    $target->save();
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    $this->container->get('node.view_all_nodes_memory_cache')->deleteAll();
    $wikiDomain = Domain::load('wiki420_aculta_org');
    $this->container->get(DomainNegotiatorInterface::class)->setActiveDomain($wikiDomain);
    $this->container->get('aculta_portal.email_confirmation_policy')->markConfirmed((int) $submitter->id());
    $this->container->get('current_user')->setAccount($submitter);

    $staleTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete alterado durante a proposta',
      'uid' => 3,
      'status' => TRUE,
      'moderation_state' => 'published',
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_test_grant_uid' => 2,
      'body' => ['value' => 'Versão inicial.', 'format' => 'plain_text'],
    ]);
    $staleTarget->save();
    $expectedTargetRevisionId = (int) $staleTarget->getRevisionId();
    $staleFormState = new FormState();
    $staleForm = WikiProposalSubmissionForm::create($this->container);
    $staleBuild = $staleForm->buildForm([], $staleFormState, $staleTarget);
    $staleFormState->setValues([
      'intent' => 'correction',
      'proposed_text' => 'Sugestão escrita para outra versão.',
      'proposed_fields_selected' => ['title' => 'title'],
      'field_values' => ['title' => 'Título proposto para a versão anterior'],
      'rationale' => 'A versão-base mudou durante o preenchimento.',
      'source_uris' => '',
      'submission_token' => $staleFormState->get('wiki_proposal_submission_token'),
    ]);
    $staleForm->validateForm($staleBuild, $staleFormState);
    self::assertFalse($staleFormState->hasAnyErrors());
    $staleForm->continueToReview($staleBuild, $staleFormState);
    $staleReview = $staleForm->buildForm([], $staleFormState, $staleTarget);
    $staleFormState->setRebuild(FALSE);
    $staleTarget->setNewRevision(TRUE);
    $staleTarget->setTitle('Versão concorrente publicada');
    $staleTarget->save();
    $proposalCountBeforeStaleSubmit = $this->container->get('entity_type.manager')
      ->getStorage('aculta_wiki_proposal')->getQuery()->accessCheck(FALSE)->count()->execute();
    self::assertSame($expectedTargetRevisionId, (int) $staleFormState->get('wiki_proposal_expected_revision'));
    $staleForm->submitForm($staleReview, $staleFormState);
    self::assertTrue($staleFormState->get('wiki_proposal_conflict'));
    self::assertSame($proposalCountBeforeStaleSubmit, (int) $this->container->get('entity_type.manager')
      ->getStorage('aculta_wiki_proposal')->getQuery()->accessCheck(FALSE)->count()->execute());
    $conflictBuild = $staleForm->buildForm([], $staleFormState, $staleTarget);
    self::assertArrayHasKey('conflict', $conflictBuild['review']['#slots']['body']);
    $staleFormState->clearErrors();
    $staleForm->backToEdit($staleReview, $staleFormState);
    $rebasedForm = $staleForm->buildForm([], $staleFormState, $staleTarget);
    self::assertArrayHasKey('base_changed', $rebasedForm);
    self::assertSame((int) $staleTarget->getRevisionId(), (int) $staleFormState->get('wiki_proposal_expected_revision'));
    $staleForm->validateForm($rebasedForm, $staleFormState);
    self::assertArrayHasKey('confirm_current_base', $staleFormState->getErrors());
    $staleFormState->clearErrors();
    $staleFormState->setValue('confirm_current_base', 1);
    $staleForm->validateForm($rebasedForm, $staleFormState);
    self::assertFalse($staleFormState->hasAnyErrors());

    $display = $this->createMock(EntityViewDisplayInterface::class);
    $linkHooks = $this->container->get(WikiProposalLinkHooks::class);
    self::assertInstanceOf(WikiProposalLinkHooks::class, $linkHooks);
    $proposalBuild = [];
    $linkHooks->entityView($proposalBuild, $target, $display, 'full');
    self::assertArrayHasKey('wiki_proposal_link', $proposalBuild);
    self::assertSame('aculta_wiki.contribution_intent', $proposalBuild['wiki_proposal_link']['link']['#url']->getRouteName());
    self::assertContains('user.permissions', $proposalBuild['#cache']['contexts']);
    $intentPage = WikiContributionIntentController::create($this->container)->page($target);
    self::assertSame('Como você quer contribuir?', $intentPage['header']['#props']['title']);
    $intentCards = $intentPage['choices']['#slots']['body']['#slots']['items'];
    self::assertCount(4, $intentCards);
    $intentIds = [];
    foreach ($intentCards as $card) {
      self::assertSame('aculta420:card', $card['#component']);
      $url = $card['#slots']['footer']['#url'];
      self::assertSame('aculta_wiki.proposal_submit', $url->getRouteName());
      $intentIds[] = $url->getOption('query')['intent'];
    }
    self::assertEqualsCanonicalizing(array_keys(WikiProposal::INTENTS), $intentIds);

    $unconfirmedBuild = [];
    $this->container->get('current_user')->setAccount($unconfirmed);
    $linkHooks->entityView($unconfirmedBuild, $target, $display, 'full');
    self::assertArrayNotHasKey('wiki_proposal_link', $unconfirmedBuild);
    try {
      WikiContributionIntentController::create($this->container)->page($target);
      self::fail('An unconfirmed account must not receive the intention selector.');
    }
    catch (AccessDeniedHttpException) {
      self::assertTrue(TRUE);
    }

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
    $this->assertProposalLockIsSharedAcrossPhpProcesses($target, (int) $submitter->id());

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
    $firstProposalId = (int) $firstPage->getItems()[0]->id();
    $decisionLink = $studioProposalListing['table']['#rows'][0]['proposal']['data']['#url'];
    self::assertSame('aculta_wiki.proposal_decide', $decisionLink->getRouteName());
    self::assertSame(['proposal' => (string) $firstProposalId], $decisionLink->getRouteParameters());
    self::assertTrue($decisionLink->access($reviewer));

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

    try {
      $decisionService->decide(
        $decidedProposal,
        (int) $decidedProposal->getRevisionId(),
        'declined',
        str_repeat('x', 5001),
      );
      self::fail('An editorial decision note must not exceed 5,000 characters.');
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

    $this->container->get('current_user')->setAccount($submitter);
    $currentTarget = $this->container->get('entity_type.manager')->getStorage('node')
      ->loadUnchanged((int) $target->id());
    $proposedImageFile = $this->createTemporaryPrivateImage((int) $submitter->id());
    $proposedImageFile->setPermanent();
    $proposedImageFile->save();
    $proposedImage = Media::create([
      'bundle' => 'image',
      'name' => 'Imagem editorial proposta',
      'uid' => $submitter->id(),
      'status' => TRUE,
      'field_media_image' => [
        'target_id' => $proposedImageFile->id(),
        'alt' => 'Imagem proposta para o verbete',
      ],
    ]);
    $proposedImage->save();
    $acceptedCandidate = $submission->submit(
      $currentTarget,
      'improve',
      'Atualização editorial aprovada',
      'A proposta atualiza a redação do título.',
      [],
      str_repeat('b', 64),
      [
        'title' => 'Título aprovado pela equipe',
        'field_wiki_references' => [
          ['uri' => 'https://example.invalid/new', 'title' => 'Fonte nova'],
          ['uri' => 'https://example.invalid/current', 'title' => 'Fonte repetida'],
        ],
        'field_wiki_categories' => [(int) $currentCategory->id(), (int) $proposedCategory->id()],
        'field_wiki_related' => [(int) $currentRelated->id(), (int) $proposedRelated->id()],
        'field_wiki_image' => (int) $proposedImage->id(),
      ],
    );
    Role::create([
      'id' => 'wiki_proposal_acceptor_test',
      'label' => 'Wiki proposal acceptor test',
      'permissions' => [
        'view any wiki proposals',
        'moderate wiki proposals',
        'edit any wiki_entry content',
        'bypass node access',
        'view any unpublished content',
        'view wiki_entry revisions',
        'use wiki_editorial transition create_new_draft',
        'use wiki_editorial transition draft_to_review',
        'use wiki_editorial transition review_to_published',
      ],
    ])->save();
    $acceptor = new UserSession(['uid' => 4, 'roles' => ['authenticated', 'wiki_proposal_acceptor_test']]);
    $this->container->get('current_user')->setAccount($acceptor);
    $decisionFormObject = WikiProposalDecisionForm::create($this->container);
    $decisionState = new FormState();
    $decisionState->addBuildInfo('args', [$acceptedCandidate]);
    $decisionBuild = $this->container->get('form_builder')->buildForm($decisionFormObject, $decisionState);
    self::assertSame('aculta420:context-header', $decisionBuild['proposal_header']['#component']);
    self::assertSame('aculta420:content-section', $decisionBuild['contribution']['#component']);
    self::assertSame(['list_modes', 'field_wiki_references'], $decisionBuild['editorial_decision']['list_modes']['field_wiki_references']['#parents']);
    self::assertSame(['list_modes', 'field_wiki_categories'], $decisionBuild['editorial_decision']['list_modes']['field_wiki_categories']['#parents']);
    self::assertSame(['list_modes', 'field_wiki_related'], $decisionBuild['editorial_decision']['list_modes']['field_wiki_related']['#parents']);
    self::assertSame('', $decisionBuild['editorial_decision']['list_modes']['field_wiki_references']['#default_value']);
    self::assertNotEmpty($decisionBuild['editorial_decision']['list_modes']['field_wiki_references']['#empty_option']);
    $decisionState->setValue($decisionBuild['expected_revision_id']['#parents'], (int) $acceptedCandidate->getRevisionId());
    $decisionState->setValue($decisionBuild['editorial_decision']['decision']['#parents'], 'accepted');
    $decisionState->setValue($decisionBuild['editorial_decision']['decision_note']['#parents'], 'Título conferido e publicado.');
    $decisionState->setValue($decisionBuild['editorial_decision']['approved_fields']['#parents'], [
      'title' => 'title',
      'field_wiki_references' => 'field_wiki_references',
      'field_wiki_categories' => 'field_wiki_categories',
      'field_wiki_related' => 'field_wiki_related',
      'field_wiki_image' => 'field_wiki_image',
    ]);
    $missingModeState = new FormState();
    $missingModeState->set('wiki_proposal_fields', array_keys($acceptedCandidate->getProposedFields()));
    $missingModeState->setValue('decision', 'accepted');
    $missingModeState->setValue('decision_note', 'Decisão sem modo de lista.');
    $missingModeState->setValue('approved_fields', ['field_wiki_categories' => 'field_wiki_categories']);
    $decisionFormObject->validateForm($decisionBuild, $missingModeState);
    self::assertArrayHasKey('list_modes[field_wiki_categories]', $missingModeState->getErrors());
    $missingModeState->clearErrors();
    $decisionState->setValue($decisionBuild['editorial_decision']['list_modes']['field_wiki_references']['#parents'], 'append');
    $decisionState->setValue($decisionBuild['editorial_decision']['list_modes']['field_wiki_categories']['#parents'], 'append');
    $decisionState->setValue($decisionBuild['editorial_decision']['list_modes']['field_wiki_related']['#parents'], 'replace');
    $decisionFormObject->validateForm($decisionBuild, $decisionState);
    self::assertSame([], $decisionState->getErrors());
    $this->container->get('aculta_portal.domain_purpose')->runInPurpose('wiki', function () use (&$decisionBuild, $decisionFormObject, $decisionState): void {
      $decisionFormObject->submitForm($decisionBuild, $decisionState);
    });
    $acceptedProposal = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
      ->load($acceptedCandidate->id());
    $publishedTarget = Node::load((int) $target->id());
    self::assertSame('accepted', $acceptedProposal->get('state')->value);
    self::assertSame('Título aprovado pela equipe', $publishedTarget->label());
    self::assertSame('published', $publishedTarget->get('moderation_state')->value);
    self::assertSame((int) $proposedImage->id(), (int) $publishedTarget->get('field_wiki_image')->target_id);
    self::assertTrue(Media::load((int) $proposedImage->id())->isPublished());
    self::assertSame(
      ['https://example.invalid/current', 'https://example.invalid/new'],
      array_column($publishedTarget->get('field_wiki_references')->getValue(), 'uri'),
    );
    self::assertSame(
      [(int) $currentCategory->id(), (int) $proposedCategory->id()],
      array_map('intval', array_column($publishedTarget->get('field_wiki_categories')->getValue(), 'target_id')),
    );
    self::assertSame(
      [(int) $currentRelated->id(), (int) $proposedRelated->id()],
      array_map('intval', array_column($publishedTarget->get('field_wiki_related')->getValue(), 'target_id')),
    );
    self::assertNotSame((int) $currentTarget->getRevisionId(), (int) $publishedTarget->getRevisionId());
    $this->container->get('current_user')->setAccount($submitter);
    $replacementCandidate = $submission->submit(
      $publishedTarget,
      'reference',
      'Substituir referências',
      'A fonte antiga foi substituída.',
      [],
      str_repeat('d', 64),
      [
        'field_wiki_references' => [['uri' => 'https://example.invalid/replacement', 'title' => 'Fonte substituta']],
        'field_wiki_categories' => [(int) $currentCategory->id()],
        'field_wiki_related' => [(int) $currentRelated->id(), (int) $proposedRelated->id()],
      ],
    );
    $this->container->get('current_user')->setAccount($acceptor);
    $replacementProposal = $decisionService->decide(
      $replacementCandidate,
      (int) $replacementCandidate->getRevisionId(),
      'accepted',
      'Referência substituída após conferência.',
      ['field_wiki_references', 'field_wiki_categories', 'field_wiki_related'],
      [
        'field_wiki_references' => 'replace',
        'field_wiki_categories' => 'replace',
        'field_wiki_related' => 'append',
      ],
    );
    $replacementTarget = $this->container->get('entity_type.manager')->getStorage('node')
      ->loadUnchanged((int) $target->id());
    self::assertSame('accepted', $replacementProposal->get('state')->value);
    self::assertSame(
      ['https://example.invalid/replacement'],
      array_column($replacementTarget->get('field_wiki_references')->getValue(), 'uri'),
    );
    self::assertSame(
      [(int) $currentCategory->id()],
      array_map('intval', array_column($replacementTarget->get('field_wiki_categories')->getValue(), 'target_id')),
    );
    self::assertSame(
      [(int) $currentRelated->id(), (int) $proposedRelated->id()],
      array_map('intval', array_column($replacementTarget->get('field_wiki_related')->getValue(), 'target_id')),
    );

    // A proposal whose target changed after submission cannot partially apply
    // its fields or record an editorial decision.
    $this->container->get('current_user')->setAccount($submitter);
    $staleBaseProposal = $submission->submit(
      $replacementTarget,
      'correction',
      'Corrigir título com base antiga',
      'O título deve ser revisto.',
      [],
      str_repeat('f', 64),
      ['title' => 'Título de uma revisão obsoleta'],
    );
    $staleProposalRevisionId = (int) $staleBaseProposal->getRevisionId();
    Role::create([
      'id' => 'wiki_proposal_moderator_without_node_edit_test',
      'label' => 'Wiki proposal moderator without node edit test',
      'permissions' => ['moderate wiki proposals'],
    ])->save();
    $moderatorWithoutNodeEdit = new UserSession([
      'uid' => 5,
      'roles' => ['authenticated', 'wiki_proposal_moderator_without_node_edit_test'],
    ]);
    $this->container->get('current_user')->setAccount($moderatorWithoutNodeEdit);
    $targetBeforeUnauthorizedDecision = $this->container->get('entity_type.manager')->getStorage('node')
      ->loadUnchanged((int) $target->id());
    $targetRevisionBeforeUnauthorizedDecision = (int) $targetBeforeUnauthorizedDecision->getRevisionId();
    try {
      $decisionService->decide(
        $staleBaseProposal,
        $staleProposalRevisionId,
        'accepted',
        'Tentativa sem permissão de editar o verbete.',
        ['title'],
      );
      self::fail('Moderating a proposal must not grant permission to edit or publish its target.');
    }
    catch (AccessDeniedHttpException) {
      $freshProposal = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
        ->loadUnchanged($staleBaseProposal->id());
      $freshTarget = $this->container->get('entity_type.manager')->getStorage('node')
        ->loadUnchanged((int) $target->id());
      self::assertSame('submitted', $freshProposal->get('state')->value);
      self::assertSame($staleProposalRevisionId, (int) $freshProposal->getRevisionId());
      self::assertSame($targetRevisionBeforeUnauthorizedDecision, (int) $freshTarget->getRevisionId());
      self::assertSame('Título aprovado pela equipe', $freshTarget->label());
    }

    Role::create([
      'id' => 'wiki_proposal_moderator_without_transition_test',
      'label' => 'Wiki proposal moderator without transition test',
      'permissions' => [
        'moderate wiki proposals',
        'edit any wiki_entry content',
        'bypass node access',
        'view any unpublished content',
        'view wiki_entry revisions',
      ],
    ])->save();
    $moderatorWithoutTransition = new UserSession([
      'uid' => 6,
      'roles' => ['authenticated', 'wiki_proposal_moderator_without_transition_test'],
    ]);
    $this->container->get('current_user')->setAccount($moderatorWithoutTransition);
    try {
      $decisionService->decide(
        $staleBaseProposal,
        $staleProposalRevisionId,
        'accepted',
        'Tentativa sem as transições de publicação.',
        ['title'],
      );
      self::fail('Editing the target must not bypass official Wiki publication transitions.');
    }
    catch (AccessDeniedHttpException) {
      $freshProposal = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
        ->loadUnchanged($staleBaseProposal->id());
      $freshTarget = $this->container->get('entity_type.manager')->getStorage('node')
        ->loadUnchanged((int) $target->id());
      self::assertSame('submitted', $freshProposal->get('state')->value);
      self::assertSame($staleProposalRevisionId, (int) $freshProposal->getRevisionId());
      self::assertSame($targetRevisionBeforeUnauthorizedDecision, (int) $freshTarget->getRevisionId());
      self::assertSame('Título aprovado pela equipe', $freshTarget->label());
    }

    $this->container->get('current_user')->setAccount($acceptor);
    $concurrentTarget = $this->container->get('entity_type.manager')->getStorage('node')
      ->loadUnchanged((int) $target->id());
    $concurrentTarget->setNewRevision(TRUE);
    $concurrentTarget->set('title', 'Título de edição concorrente');
    $concurrentTarget->save();
    $concurrentRevisionId = (int) $concurrentTarget->getRevisionId();

    try {
      $decisionService->decide(
        $staleBaseProposal,
        $staleProposalRevisionId,
        'accepted',
        'Tentativa com base obsoleta.',
        ['title'],
      );
      self::fail('A proposal must not apply to a Wiki entry changed after submission.');
    }
    catch (ConflictHttpException) {
      $freshProposal = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
        ->loadUnchanged($staleBaseProposal->id());
      $freshTarget = $this->container->get('entity_type.manager')->getStorage('node')
        ->loadUnchanged((int) $target->id());
      self::assertSame('submitted', $freshProposal->get('state')->value);
      self::assertSame($staleProposalRevisionId, (int) $freshProposal->getRevisionId());
      self::assertSame($concurrentRevisionId, (int) $freshTarget->getRevisionId());
      self::assertSame('Título de edição concorrente', $freshTarget->label());
    }

    // A second database connection holds the target row while the real
    // decision service starts. The service must wait for that lock before it
    // revalidates the base revision and applies the proposal.
    $freshTarget = $this->container->get('entity_type.manager')->getStorage('node')
      ->loadUnchanged((int) $target->id());
    self::assertInstanceOf(Node::class, $freshTarget);
    $this->container->get('current_user')->setAccount($submitter);
    $lockedProposal = $submission->submit(
      $freshTarget,
      'correction',
      'Atualização com lock de linha',
      'A decisão deve aguardar a sessão concorrente terminar.',
      [],
      str_repeat('e', 64),
      ['title' => 'Título aplicado após o lock'],
    );
    $this->container->get('current_user')->setAccount($acceptor);
    $beforeLockedDecisionRevisionId = (int) $freshTarget->getRevisionId();
    $decisionStarted = microtime(TRUE);
    $acceptedAfterLock = $this->decideAfterTargetRowLock(
      $freshTarget,
      $lockedProposal,
      $decisionService,
    );
    self::assertGreaterThanOrEqual(1.0, microtime(TRUE) - $decisionStarted);
    $afterLockedDecision = $this->container->get('entity_type.manager')->getStorage('node')
      ->loadUnchanged((int) $target->id());
    self::assertSame('accepted', $acceptedAfterLock->get('state')->value);
    self::assertSame('Título aplicado após o lock', $afterLockedDecision->label());
    self::assertGreaterThan($beforeLockedDecisionRevisionId, (int) $afterLockedDecision->getRevisionId());
    $this->container->get('current_user')->setAccount($reviewer);

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
    self::assertSame('aculta420:context-header', $historyBuild['header']['#component']);
    self::assertSame('aculta420:content-section', $historyBuild['history']['#component']);
    self::assertCount(20, $historyBuild['history']['#slots']['body']['table']['#rows']);
    self::assertNotEmpty($historyBuild['history']['#slots']['body']['pager']['#url']);
    $historyCursor = $historyBuild['history']['#slots']['body']['pager']['#url']->getOption('query')['revision_cursor'];
    $historyNextPage = $statusController->view(
      $historyProposal,
      Request::create('/wiki/propostas/' . $historyProposal->id(), 'GET', ['revision_cursor' => $historyCursor]),
    );
    self::assertCount(1, $historyNextPage['history']['#slots']['body']['table']['#rows']);
    self::assertSame([], $historyNextPage['history']['#slots']['body']['pager']);

    $statusBuild = $statusController->view(
      $declinedProposal,
      Request::create('/wiki/propostas/' . $declinedProposal->id()),
    );
    self::assertSame(
      'A evidência apresentada não sustenta esta alteração.',
      $statusBuild['decision_note']['#slots']['body']['text']['#plain_text'],
    );
    self::assertContains('user', $statusBuild['#cache']['contexts']);
    self::assertArrayNotHasKey('#slots', $statusBuild['header']);
    self::assertCount(2, $statusBuild['history']['#slots']['body']['table']['#rows']);
    self::assertSame('Recusada', $statusBuild['history']['#slots']['body']['table']['#rows'][0]['state']);
    self::assertSame(
      'A evidência apresentada não sustenta esta alteração.',
      $statusBuild['history']['#slots']['body']['table']['#rows'][0]['feedback'],
    );
    self::assertSame('Enviada', $statusBuild['history']['#slots']['body']['table']['#rows'][1]['state']);
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
    self::assertTrue($outcomeNotifier->notify($acceptedProposal));
    self::assertCount(2, $memoryQueue->items);
    self::assertSame('Aceita e publicada', $memoryQueue->items[1]->context['state']);
    self::assertSame(
      ['subject', 'state', 'decision_note', 'proposal_url'],
      array_keys($memoryQueue->items[1]->context),
      'The acceptance notice links privately without copying proposed content or personal details.',
    );

    $this->container->get('current_user')->setAccount($submitter);
    $formState = new FormState();
    $proposalForm = WikiProposalSubmissionForm::create($this->container);
    $proposalForm->buildForm([], $formState, $target);
    $formState->setValues([
      'intent' => 'correction',
      'proposed_text' => 'Texto sugerido pelo Form API',
      'proposed_fields_selected' => [
        'title' => 'title',
      ],
      'field_values' => [
        'title' => 'Novo título sugerido',
      ],
      'rationale' => 'Ajuste comprovado pela fonte.',
      'source_uris' => 'https://example.invalid/referencia',
      'submission_token' => $formState->get('wiki_proposal_submission_token'),
    ]);
    $build = [];
    $proposalForm->validateForm($build, $formState);
    self::assertFalse($formState->hasAnyErrors());
    $proposalForm->continueToReview($build, $formState);
    self::assertSame('review', $formState->get('wiki_proposal_step'));
    $reviewBuild = $proposalForm->buildForm([], $formState, $target);
    self::assertSame('aculta420:content-section', $reviewBuild['review']['#component']);
    self::assertStringContainsString('Novo título sugerido', (string) $reviewBuild['review']['#slots']['body']['fields']['#plain_text']);
    self::assertFalse($reviewBuild['intent']['#access']);
    $formState->setRebuild(FALSE);
    $proposalForm->backToEdit($reviewBuild, $formState);
    self::assertSame('edit', $formState->get('wiki_proposal_step'));
    self::assertSame('Texto sugerido pelo Form API', $formState->getValue('proposed_text'));
    $editBuild = $proposalForm->buildForm([], $formState, $target);
    $formState->setRebuild(FALSE);
    $proposalForm->validateForm($editBuild, $formState);
    self::assertFalse($formState->hasAnyErrors());
    $proposalForm->continueToReview($editBuild, $formState);
    $reviewBuild = $proposalForm->buildForm([], $formState, $target);
    $formState->setRebuild(FALSE);
    $proposalForm->validateForm($reviewBuild, $formState);
    self::assertFalse($formState->hasAnyErrors());
    $currentTarget = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($target->id());
    self::assertSame((int) $currentTarget->getRevisionId(), (int) $formState->get('wiki_proposal_expected_revision'));
    $proposalForm->submitForm($reviewBuild, $formState);
    self::assertSame([], $formState->getErrors());
    self::assertSame('aculta_wiki.proposal_status', $formState->getRedirect()->getRouteName());
    $redirectProposal = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
      ->load($formState->getRedirect()->getRouteParameters()['proposal']);
    self::assertInstanceOf(WikiProposal::class, $redirectProposal);
    self::assertSame('correction', $redirectProposal->get('intent')->value);
    self::assertSame(['value' => 'Novo título sugerido'], $redirectProposal->getProposedFields()['title']);
    $firstFormSubmissionId = (int) $redirectProposal->id();
    $proposalForm->submitForm($build, $formState);
    self::assertSame($firstFormSubmissionId, (int) $formState->getRedirect()->getRouteParameters()['proposal']);

    // Includes the proposal accepted through the independent row-lock test.
    self::assertSame(31, (int) $this->container->get('entity_type.manager')
      ->getStorage('aculta_wiki_proposal')->getQuery()->accessCheck(FALSE)->count()->execute());

    $this->container->get('current_user')->setAccount($submitter);
    $withdrawal = $this->container->get('aculta_wiki.proposal_withdrawal');
    $pendingProposal = $submission->submit($target, 'improve', 'Ajustar a descrição.', 'A frase atual está ambígua.');
    $statusController = WikiProposalStatusController::create($this->container);
    $pendingStatus = $statusController->view(
      $pendingProposal,
      Request::create('/wiki/propostas/' . $pendingProposal->id()),
    );
    self::assertSame('aculta420:context-header', $pendingStatus['header']['#component']);
    self::assertSame('Retirar proposta', (string) $pendingStatus['header']['#slots']['actions']['#title']);
    $withdrawalForm = WikiProposalWithdrawalForm::create($this->container);
    $withdrawalState = new FormState();
    $withdrawalBuild = $withdrawalForm->buildForm([], $withdrawalState, $pendingProposal);
    self::assertArrayHasKey('confirm', $withdrawalBuild);
    $withdrawalState->setValues([
      'confirm' => 1,
      'expected_revision_id' => $pendingProposal->getRevisionId(),
    ]);
    $withdrawalForm->validateForm($withdrawalBuild, $withdrawalState);
    self::assertFalse($withdrawalState->hasAnyErrors());
    $withdrawalForm->submitForm($withdrawalBuild, $withdrawalState);
    self::assertSame('aculta_wiki.proposal_status', $withdrawalState->getRedirect()->getRouteName());
    $withdrawnProposal = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
      ->load($pendingProposal->id());
    self::assertSame('withdrawn', $withdrawnProposal->get('state')->value);
    self::assertNotSame((int) $pendingProposal->getRevisionId(), (int) $withdrawnProposal->getRevisionId());
    self::assertSame('submitted', $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
      ->loadRevision($pendingProposal->getRevisionId())->get('state')->value);

    $withdrawnRevisionId = (int) $withdrawnProposal->getRevisionId();
    $this->container->get('current_user')->setAccount($reviewer);
    try {
      $decisionService->decide($withdrawnProposal, $withdrawnRevisionId, 'declined', 'A proposta foi retirada.');
      self::fail('A withdrawn proposal must not be reopened by an editorial decision.');
    }
    catch (ConflictHttpException) {
      $freshWithdrawnProposal = $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
        ->loadUnchanged($withdrawnProposal->id());
      self::assertSame('withdrawn', $freshWithdrawnProposal->get('state')->value);
      self::assertSame($withdrawnRevisionId, (int) $freshWithdrawnProposal->getRevisionId());
    }

    $this->container->get('current_user')->setAccount($submitter);
    $reopenedProposal = $submission->submit($target, 'reference', 'Incluir estudo recente.', 'Esta fonte amplia a cobertura.');
    $this->container->get('current_user')->setAccount($reviewer);
    $needsChangesProposal = $decisionService->decide(
      $reopenedProposal,
      (int) $reopenedProposal->getRevisionId(),
      'needs_changes',
      'Explique a relação entre a fonte e a afirmação.',
    );
    $this->container->get('current_user')->setAccount($submitter);
    $withdrawnAfterChanges = $withdrawal->withdraw($needsChangesProposal, (int) $needsChangesProposal->getRevisionId());
    self::assertSame('withdrawn', $withdrawnAfterChanges->get('state')->value);
    self::assertNull($withdrawnAfterChanges->get('decision_note')->value);
    self::assertSame('needs_changes', $this->container->get('entity_type.manager')->getStorage('aculta_wiki_proposal')
      ->loadRevision($needsChangesProposal->getRevisionId())->get('state')->value);

    $this->container->get('current_user')->setAccount($other);
    try {
      $withdrawal->withdraw($withdrawnProposal, (int) $withdrawnProposal->getRevisionId());
      self::fail('Another author must not withdraw a private proposal.');
    }
    catch (AccessDeniedHttpException) {
      self::assertSame('withdrawn', $withdrawnProposal->get('state')->value);
    }

    $this->container->get('current_user')->setAccount($other);
    try {
      $queue->proposals();
      self::fail('Accounts without the moderation permission must not read the queue.');
    }
    catch (AccessDeniedHttpException) {
      self::assertTrue(TRUE);
    }

    $this->container->get('current_user')->setAccount($submitter);
    $invalidFormState = new FormState();
    $proposalForm->buildForm([], $invalidFormState, $target);
    $invalidFormState->setValues([
      'intent' => 'correction',
      'proposed_text' => 'Propor uma imagem indisponível.',
      'proposed_fields_selected' => ['field_wiki_image' => 'field_wiki_image'],
      'field_values' => ['image' => 999999],
      'rationale' => 'A imagem não está disponível no acervo.',
      'source_uris' => '',
      'submission_token' => $invalidFormState->get('wiki_proposal_submission_token'),
    ]);
    $invalidForm = [];
    $proposalForm->submitForm($invalidForm, $invalidFormState);
    self::assertArrayHasKey('', $invalidFormState->getErrors());
    self::assertArrayNotHasKey('proposed_text', $invalidFormState->getErrors());

    $this->container->get('current_user')->setAccount($other);
    $this->expectException(AccessDeniedHttpException::class);
    $submission->submit($target, 'correction', 'Correção', 'Motivo');
  }

  /**
   * Verifies the production lock and proposal service across PHP processes.
   */
  private function assertProposalLockIsSharedAcrossPhpProcesses(
    NodeInterface $target,
    int $uid,
  ): void {
    if (!function_exists('proc_open')) {
      self::markTestSkipped('proc_open is required for the cross-process lock regression.');
    }

    $token = str_repeat('b', 64);
    $lockName = 'aculta_wiki.proposal_submission:' . hash('sha256', $uid . ':' . $token);
    $workerCode = <<<'PHP'
require 'web/autoload.php';
$loader = require 'vendor/autoload.php';
$loader->addPsr4('Drupal\\mysql\\', 'web/core/modules/mysql/src');
$options = unserialize(base64_decode(trim(fgets(STDIN))), ['allowed_classes' => FALSE]);
$lockName = trim(fgets(STDIN));
\Drupal\Core\Database\Database::addConnectionInfo('wiki_proposal_process_lock', 'default', $options);
$connection = \Drupal\Core\Database\Database::getConnection('default', 'wiki_proposal_process_lock');
$lock = new \Drupal\Core\Lock\DatabaseLockBackend($connection);
if (!$lock->acquire($lockName, 45.0)) {
  fwrite(STDOUT, "lock-not-acquired\n");
  exit(2);
}
fwrite(STDOUT, "ready:" . $connection->getPrefix() . "\n");
fflush(STDOUT);
fgets(STDIN);
$lock->release($lockName);
fwrite(STDOUT, "released\n");
PHP;
    $pipes = [];
    $process = proc_open(
      [PHP_BINARY, '-r', $workerCode],
      [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
      $pipes,
      dirname(DRUPAL_ROOT),
    );
    self::assertIsResource($process);
    stream_set_timeout($pipes[1], 15);
    stream_set_timeout($pipes[2], 15);
    $exitCode = NULL;
    try {
      $database = $this->container->get('database');
      fwrite($pipes[0], base64_encode(serialize($database->getConnectionOptions())) . "\n" . $lockName . "\n");
      fflush($pipes[0]);
      $ready = fgets($pipes[1]);
      if (!is_string($ready) || !str_starts_with($ready, 'ready:')) {
        self::fail('PHP lock worker failed: ' . substr((string) stream_get_contents($pipes[2]), 0, 500));
      }

      $database = $this->container->get('database');
      $productionLock = new DatabaseLockBackend($database);
      self::assertFalse(
        $productionLock->acquire($lockName, 30.0),
        'The production lock backend must see the worker lock. Main=' . get_class($productionLock)
          . ', main prefix=' . $database->getPrefix()
          . ', worker prefix=' . trim(substr($ready, strlen('ready:'))),
      );
      $beforeCount = (int) $this->container->get('entity_type.manager')
        ->getStorage('aculta_wiki_proposal')->getQuery()->accessCheck(FALSE)->count()->execute();
      $submission = new WikiProposalSubmission(
        $this->container->get('entity_type.manager'),
        $this->container->get('current_user'),
        $this->container->get('aculta_portal.domain_purpose'),
        $this->container->get('aculta_portal.email_confirmation_policy'),
        $this->container->get('flood'),
        $productionLock,
        $this->container->get('keyvalue.expirable'),
        $database,
      );
      try {
        $submission->submit(
          $target,
          'reference',
          'Submissão sob lock de outro processo',
          'O mesmo token não pode criar uma segunda proposta.',
          [],
          $token,
        );
        self::fail('The proposal service must reject a token locked by another process.');
      }
      catch (TooManyRequestsHttpException) {
        self::assertSame($beforeCount, (int) $this->container->get('entity_type.manager')
          ->getStorage('aculta_wiki_proposal')->getQuery()->accessCheck(FALSE)->count()->execute());
      }

      fwrite($pipes[0], "release\n");
      fflush($pipes[0]);
      self::assertSame("released\n", fgets($pipes[1]));
    }
    finally {
      if (is_resource($pipes[0])) {
        @fwrite($pipes[0], "release\n");
        fclose($pipes[0]);
      }
      if (is_resource($pipes[1])) {
        fclose($pipes[1]);
      }
      if (is_resource($pipes[2])) {
        fclose($pipes[2]);
      }
      $exitCode = proc_close($process);
    }
    self::assertSame(0, $exitCode);
  }

  /**
   * Decides a proposal after an independent process releases the target lock.
   *
   * @param \Drupal\node\NodeInterface $target
   *   Published Wiki target whose row is locked by the worker.
   * @param \Drupal\aculta_wiki\Entity\WikiProposal $proposal
   *   Proposal against the current target revision.
   * @param \Drupal\aculta_wiki\Service\WikiProposalDecision $decisionService
   *   Production decision service.
   *
   * @return \Drupal\aculta_wiki\Entity\WikiProposal
   *   The persisted accepted proposal revision.
   */
  private function decideAfterTargetRowLock(
    NodeInterface $target,
    WikiProposal $proposal,
    WikiProposalDecision $decisionService,
  ): WikiProposal {
    if (!function_exists('proc_open')) {
      self::markTestSkipped('proc_open is required for the row-lock regression.');
    }

    $workerCode = <<<'PHP'
require 'web/autoload.php';
$loader = require 'vendor/autoload.php';
$loader->addPsr4('Drupal\\mysql\\', 'web/core/modules/mysql/src');
$options = unserialize(base64_decode(trim(fgets(STDIN))), ['allowed_classes' => FALSE]);
$nodeId = (int) trim(fgets(STDIN));
\Drupal\Core\Database\Database::addConnectionInfo('wiki_decision_row_lock', 'default', $options);
$connection = \Drupal\Core\Database\Database::getConnection('default', 'wiki_decision_row_lock');
$transaction = $connection->startTransaction();
$connection->select('node', 'node')->fields('node', ['nid'])->condition('nid', $nodeId)->forUpdate()->execute()->fetchField();
fwrite(STDOUT, "locked\n");
fflush(STDOUT);
usleep(1500000);
$transaction->commitOrRelease();
fwrite(STDOUT, "released\n");
PHP;
    $pipes = [];
    $process = proc_open(
      [PHP_BINARY, '-r', $workerCode],
      [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
      $pipes,
      dirname(DRUPAL_ROOT),
    );
    self::assertIsResource($process);
    stream_set_timeout($pipes[1], 15);
    stream_set_timeout($pipes[2], 15);
    $exitCode = NULL;
    $accepted = NULL;
    try {
      $database = $this->container->get('database');
      fwrite($pipes[0], base64_encode(serialize($database->getConnectionOptions())) . "\n" . $target->id() . "\n");
      fflush($pipes[0]);
      self::assertSame("locked\n", fgets($pipes[1]));
      $accepted = $decisionService->decide(
        $proposal,
        (int) $proposal->getRevisionId(),
        'accepted',
        'Campos aplicados após serialização pelo lock.',
        ['title'],
      );
      self::assertSame("released\n", fgets($pipes[1]));
    }
    finally {
      if (is_resource($pipes[0])) {
        fclose($pipes[0]);
      }
      if (is_resource($pipes[1])) {
        fclose($pipes[1]);
      }
      if (is_resource($pipes[2])) {
        fclose($pipes[2]);
      }
      $exitCode = proc_close($process);
    }
    self::assertSame(0, $exitCode);
    self::assertInstanceOf(WikiProposal::class, $accepted);
    return $accepted;
  }

  /**
   * Pending Wiki Node revisions are queried under Wiki grants and exact VID.
   */
  public function testEditorialQueuePaginatesAuthorizedRevisionsPastDeniedCandidates(): void {
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
    for ($index = 0; $index < 25; $index++) {
      $authorized = Node::create([
        'type' => 'wiki_entry',
        'title' => 'Verbete autorizado para revisão ' . $index,
        'uid' => 5,
        'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
        'field_wiki_test_grant_uid' => 4,
        'body' => ['value' => 'Texto autorizável', 'format' => 'plain_text'],
        'moderation_state' => 'draft',
      ]);
      $authorized->save();
      $authorized->setNewRevision(TRUE);
      $authorized->set('moderation_state', 'review');
      $authorized->save();
    }
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
    $firstAuthorizedPage = $queue->entryRevisions();
    self::assertCount(25, $firstAuthorizedPage->getItems());
    self::assertNotNull($firstAuthorizedPage->getNextCursor());
    $firstPageIds = array_map(
      static fn (NodeInterface $item): string => (string) $item->getRevisionId(),
      $firstAuthorizedPage->getItems(),
    );
    $secondAuthorizedPage = $queue->entryRevisions($firstAuthorizedPage->getNextCursor());
    self::assertCount(1, $secondAuthorizedPage->getItems());
    self::assertNull($secondAuthorizedPage->getNextCursor());
    $secondPageIds = array_map(
      static fn (NodeInterface $item): string => (string) $item->getRevisionId(),
      $secondAuthorizedPage->getItems(),
    );
    $allAuthorizedIds = array_merge($firstPageIds, $secondPageIds);
    self::assertCount(26, array_unique($allAuthorizedIds));
    self::assertContains($reviewRevisionId, $allAuthorizedIds);
    foreach (array_merge($firstAuthorizedPage->getItems(), $secondAuthorizedPage->getItems()) as $revision) {
      $latestRevisionId = $this->container->get('entity_type.manager')
        ->getStorage('node')
        ->getLatestRevisionId($revision->id());
      self::assertSame((string) $latestRevisionId, (string) $revision->getRevisionId());
      self::assertSame('review', $revision->get('moderation_state')->value);
    }
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
