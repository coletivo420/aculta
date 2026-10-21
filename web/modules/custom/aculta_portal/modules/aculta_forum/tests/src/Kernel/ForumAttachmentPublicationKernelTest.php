<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Attachment\ForumAttachmentPublication;
use Drupal\aculta_forum\Attachment\ForumGalleryPublication;
use Drupal\aculta_forum\Attachment\ForumAttachmentCleanup;
use Drupal\aculta_forum\Hook\ForumAttachmentPublicationHooks;
use Drupal\aculta_image\FileRemover;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies Forum attachment publication follows public comment references.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumAttachmentPublicationKernelTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\\Core\\StreamWrapper\\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'media_library',
    'views',
    'photoswipe',
    'media_gallery',
    'node',
    'comment',
    'domain',
    'filter',
    'text',
    'options',
    'path',
    'path_alias',
    'entity_usage',
  ];

  /**
   * The publication service under test.
   *
   * @var \Drupal\aculta_forum\Attachment\ForumAttachmentPublication
   */
  private ForumAttachmentPublication $publication;

  /**
   * Forum comment author.
   *
   * @var \Drupal\user\Entity\User
   */
  private User $author;

  /**
   * Domain purpose resolver for the fixture.
   *
   * @var \Drupal\aculta_portal\Domain\DomainPurposeManager
   */
  private DomainPurposeManager $purpose;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('media_gallery');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installConfig([
      'system', 'user', 'field', 'file', 'media', 'node', 'comment', 'domain',
      'filter', 'text', 'options', 'path', 'path_alias', 'entity_usage',
      'media_library', 'photoswipe', 'media_gallery',
      'views',
    ]);

    Domain::create([
      'id' => 'forum_aculta_org',
      'hostname' => 'forum.example.test',
      'name' => 'Forum test domain',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
    ])->save();

    $anonymous_role = Role::load('anonymous') ?? Role::create([
      'id' => 'anonymous',
      'label' => 'Anonymous',
      'weight' => 0,
      'is_admin' => FALSE,
      'permissions' => [],
    ]);
    $anonymous_role->grantPermission('access content');
    $anonymous_role->grantPermission('access comments');
    $anonymous_role->save();

    $this->author = User::create(['name' => 'forum author', 'status' => 1]);
    $this->author->save();

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'field_name' => 'field_domain_source',
      'label' => 'Domain source',
    ])->save();
    $this->addDefaultCommentField('node', 'aculta_forum_topic', 'field_aculta_discussion', CommentingStatus::Open, 'aculta_discussion_post');

    MediaType::create([
      'id' => ForumAttachmentPublication::BUNDLE,
      'label' => 'Forum attachment',
      'source' => 'file',
      'source_configuration' => ['source_field' => 'field_media_forum_attachment'],
      'field_map' => ['name' => 'name'],
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_forum_attachment',
      'type' => 'file',
      'settings' => ['uri_scheme' => 'private'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'media',
      'bundle' => ForumAttachmentPublication::BUNDLE,
      'field_name' => 'field_media_forum_attachment',
      'label' => 'File',
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'media_gallery',
      'field_name' => 'field_gallery_purpose',
      'type' => 'string',
      'settings' => [
        'max_length' => 32,
        'is_ascii' => FALSE,
        'case_sensitive' => FALSE,
      ],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'media_gallery',
      'bundle' => 'media_gallery',
      'field_name' => 'field_gallery_purpose',
      'label' => 'Gallery purpose',
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->purpose = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('domain.negotiator'),
      $this->container->get('config.factory'),
      new RequestStack(),
    );
    $this->publication = new ForumAttachmentPublication(
      $this->container->get('entity_type.manager'),
      $this->purpose,
    );
  }

  /**
   * The published hook type matches the service implementation namespace.
   */
  public function testPublicationHookUsesAttachmentGalleryService(): void {
    $gallery_publication = new ForumGalleryPublication($this->container->get('entity_type.manager'), $this->publication);
    $hooks = new ForumAttachmentPublicationHooks($this->publication, $gallery_publication);

    $this->assertInstanceOf(ForumAttachmentPublicationHooks::class, $hooks);
  }

  /**
   * Published comments publish shared media; pending comments do not.
   */
  public function testPublicationFollowsAccessibleCommentReferences(): void {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Attachment test topic',
      'status' => 1,
      'uid' => $this->author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->save();
    $file = File::create([
      'uri' => 'private://forum/anexo.pdf',
      'filename' => 'anexo.pdf',
      'filemime' => 'application/pdf',
      'uid' => $this->author->id(),
    ]);
    $file->setPermanent();
    $file->save();
    $media = Media::create([
      'bundle' => ForumAttachmentPublication::BUNDLE,
      'name' => 'anexo.pdf',
      'uid' => $this->author->id(),
      'status' => 0,
      'field_media_forum_attachment' => ['target_id' => $file->id()],
    ]);
    $media->save();

    $first = $this->comment($topic, $media, CommentInterface::PUBLISHED);
    $this->registerEmbedUsage($media, $first);
    self::assertSame('forum', $this->purpose->getPurposeForDomainId('forum_aculta_org'));
    self::assertTrue($topic->access('view', new AnonymousUserSession(), TRUE)->isAllowed(), 'Anonymous can view the published host topic.');
    self::assertTrue($first->access('view', new AnonymousUserSession(), TRUE)->isAllowed(), 'Anonymous can view the published comment.');
    self::assertNotEmpty($this->container->get('entity_usage.usage')->listSources($media, FALSE));
    $this->publication->syncComment($first);
    self::assertTrue($this->isAttachmentPublished($media));

    $second = $this->comment($topic, $media, CommentInterface::PUBLISHED);
    $this->registerEmbedUsage($media, $second);
    $this->publication->syncComment($second);
    self::assertTrue($this->isAttachmentPublished($media));

    $first->setUnpublished();
    $first->save();
    $this->publication->syncComment($first);
    self::assertTrue($this->isAttachmentPublished($media), 'A second public comment still references the attachment.');

    $second->setUnpublished();
    $second->save();
    self::assertFalse($first->isPublished());
    self::assertFalse($second->isPublished());
    $this->publication->syncComment($second);
    self::assertFalse($this->isAttachmentPublished($media));

    $second->setPublished();
    $second->save();
    $this->publication->syncComment($second);
    self::assertTrue($this->isAttachmentPublished($media));

    $topic->setUnpublished();
    $topic->save();
    $this->publication->syncTopic($topic);
    self::assertFalse($this->isAttachmentPublished($media), 'An unpublished host topic removes public attachment access.');
  }

  /**
   * Removing an embed unpublishes its Media without Entity Usage tracking.
   */
  public function testRemovedCommentEmbedUnpublishesMediaWithoutUsageRecord(): void {
    $topic = $this->createTopic();
    $file = $this->createAttachmentFile('detached.pdf');
    $media = $this->createAttachmentMedia($file, 'detached.pdf', $this->container->get('datetime.time')->getRequestTime());
    $comment = $this->comment($topic, $media, CommentInterface::PUBLISHED);

    $this->publication->syncComment($comment);
    self::assertTrue($this->isAttachmentPublished($media));
    self::assertSame([], $this->container->get('entity_usage.usage')->listSources($media, FALSE));

    $comment->set('comment_body', ['value' => 'Imagem removida.', 'format' => 'plain_text']);
    $comment->save();
    $this->publication->syncComment($comment);

    self::assertFalse($this->isAttachmentPublished($media));
  }

  /**
   * Removes one-hour-old orphan uploads but preserves Media used by a draft.
   */
  public function testCleanupRespectsOneHourAndDraftReferences(): void {
    $old_timestamp = $this->container->get('datetime.time')->getRequestTime() - 3601;
    $orphan_file = $this->createAttachmentFile('orphan.pdf');
    $orphan = $this->createAttachmentMedia($orphan_file, 'orphan.pdf', $old_timestamp);

    $draft_file = $this->createAttachmentFile('draft.pdf');
    $draft_media = $this->createAttachmentMedia($draft_file, 'draft.pdf', $old_timestamp);
    $topic = $this->createTopic();
    $draft = $this->comment($topic, $draft_media, CommentInterface::NOT_PUBLISHED);
    $gallery_draft_file = $this->createAttachmentFile('gallery-draft.jpg');
    $gallery_draft_media = $this->createAttachmentMedia($gallery_draft_file, 'gallery-draft.jpg', $old_timestamp);
    $gallery_draft = $this->container->get('entity_type.manager')->getStorage('media_gallery')->create([
      'title' => 'Pending Forum gallery',
      'uid' => $this->author->id(),
      'status' => FALSE,
      'field_gallery_purpose' => 'forum',
      'images' => [['target_id' => $gallery_draft_media->id()]],
    ]);
    $gallery_draft->save();
    $gallery_comment = $this->commentWithGallery($topic, $gallery_draft->uuid(), CommentInterface::NOT_PUBLISHED);

    $cleanup = new ForumAttachmentCleanup(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_usage.usage'),
      $this->container->get('file.usage'),
      $this->container->get('database'),
      $this->container->get('datetime.time'),
      new FileRemover(
        $this->container->get('file.usage'),
        $this->container->get('entity_type.manager'),
        $this->container->get('database'),
        $this->container->get('file_system'),
        $this->container->get('logger.factory'),
      ),
    );

    self::assertSame(1, $cleanup->cleanup());
    self::assertNull($this->container->get('entity_type.manager')->getStorage('media')->load($orphan->id()));
    self::assertNull($this->container->get('entity_type.manager')->getStorage('file')->load($orphan_file->id()));
    self::assertInstanceOf(CommentInterface::class, $draft);
    self::assertNotNull($this->container->get('entity_type.manager')->getStorage('media')->load($draft_media->id()));
    self::assertNotNull($this->container->get('entity_type.manager')->getStorage('file')->load($draft_file->id()));
    self::assertInstanceOf(CommentInterface::class, $gallery_comment);
    self::assertNotNull($this->container->get('entity_type.manager')->getStorage('media')->load($gallery_draft_media->id()));
    self::assertNotNull($this->container->get('entity_type.manager')->getStorage('file')->load($gallery_draft_file->id()));
  }

  /**
   * Private attachments in published Forum galleries follow the comment.
   */
  public function testGalleryFieldReferencePublishesAttachmentWithoutUsageRecord(): void {
    $topic = $this->createTopic();
    $file = $this->createAttachmentFile('gallery-image.jpg');
    $media = $this->createAttachmentMedia($file, 'gallery-image.jpg', $this->container->get('datetime.time')->getRequestTime());
    $gallery = $this->container->get('entity_type.manager')->getStorage('media_gallery')->create([
      'title' => 'Forum gallery test',
      'uid' => $this->author->id(),
      'status' => TRUE,
      'field_gallery_purpose' => 'forum',
      'images' => [['target_id' => $media->id()]],
    ]);
    self::assertInstanceOf(MediaGalleryInterface::class, $gallery);
    $gallery->save();
    $comment = $this->commentWithGallery($topic, $gallery->uuid(), CommentInterface::PUBLISHED);

    self::assertSame([], $this->container->get('entity_usage.usage')->listSources($media, FALSE));
    $this->publication->syncMediaEntity($media);
    self::assertTrue($this->isAttachmentPublished($media));

    $comment->setUnpublished();
    $comment->save();
    $this->publication->syncMediaEntity($media);
    self::assertFalse($this->isAttachmentPublished($media));
  }

  /**
   * Removing a Forum Gallery image unpublishes its detached Media.
   */
  public function testRemovedGalleryImageUnpublishesMedia(): void {
    $topic = $this->createTopic();
    $file = $this->createAttachmentFile('removed-gallery-image.jpg');
    $media = $this->createAttachmentMedia($file, 'removed-gallery-image.jpg', $this->container->get('datetime.time')->getRequestTime());
    $gallery = $this->container->get('entity_type.manager')->getStorage('media_gallery')->create([
      'title' => 'Forum gallery removal test',
      'uid' => $this->author->id(),
      'status' => FALSE,
      'field_gallery_purpose' => 'forum',
      'images' => [['target_id' => $media->id()]],
    ]);
    $gallery->save();
    $comment = $this->commentWithGallery($topic, $gallery->uuid(), CommentInterface::PUBLISHED);
    $gallery_publication = new ForumGalleryPublication($this->container->get('entity_type.manager'), $this->publication);
    $gallery_publication->syncComment($comment);
    self::assertTrue($this->isAttachmentPublished($media));

    $gallery->set('images', []);
    $gallery->save();
    $gallery_publication->syncGalleryEntity($gallery);

    self::assertTrue($gallery->isPublished(), 'The gallery remains published while its comment remains public.');
    self::assertFalse($this->isAttachmentPublished($media));
  }

  /**
   * Creates a Forum comment containing an attachment embed.
   */
  private function comment(NodeInterface $topic, Media $media, int $status): CommentInterface {
    $body = sprintf(
      '<drupal-media data-entity-type="media" data-entity-uuid="%s"></drupal-media>',
      $media->uuid(),
    );
    $comment = Comment::create([
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $this->author->id(),
      'subject' => 'Attachment comment',
      'comment_body' => ['value' => $body, 'format' => 'plain_text'],
      'status' => $status,
    ]);
    $comment->save();
    return $comment;
  }

  /**
   * Creates a Forum comment embedding an existing Gallery entity.
   */
  private function commentWithGallery(NodeInterface $topic, string $gallery_uuid, int $status): CommentInterface {
    $comment = Comment::create([
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $this->author->id(),
      'subject' => 'Forum gallery comment',
      'comment_body' => [
        'value' => sprintf('<drupal-entity data-entity-type="media_gallery" data-entity-uuid="%s"></drupal-entity>', $gallery_uuid),
        'format' => 'plain_text',
      ],
      'status' => $status,
    ]);
    $comment->save();
    return $comment;
  }

  /**
   * Creates an attachment File entity for lifecycle coverage.
   */
  private function createAttachmentFile(string $filename): File {
    $file = File::create([
      'uri' => 'private://forum/' . $filename,
      'filename' => $filename,
      'filemime' => 'application/pdf',
      'uid' => $this->author->id(),
    ]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Creates an unpublished attachment with a controlled creation timestamp.
   */
  private function createAttachmentMedia(File $file, string $name, int $created): Media {
    $media = Media::create([
      'bundle' => ForumAttachmentPublication::BUNDLE,
      'name' => $name,
      'uid' => $this->author->id(),
      'status' => 0,
      'created' => $created,
      'field_media_forum_attachment' => ['target_id' => $file->id()],
    ]);
    $media->save();
    return $media;
  }

  /**
   * Creates a published Forum topic fixture.
   */
  private function createTopic(): NodeInterface {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Cleanup test topic',
      'status' => 1,
      'uid' => $this->author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->save();
    return $topic;
  }

  /**
   * Registers the Media embed relationship tracked by Entity Usage.
   */
  private function registerEmbedUsage(Media $media, CommentInterface $comment): void {
    $this->container->get('entity_usage.usage')->registerUsage(
      (int) $media->id(),
      'media',
      (int) $comment->id(),
      'comment',
      $comment->language()->getId(),
      (int) $comment->getRevisionId(),
      'media_embed',
      'comment_body',
    );
  }

  /**
   * Loads the persisted publication state after reconciliation.
   */
  private function isAttachmentPublished(Media $media): bool {
    $stored = $this->container->get('entity_type.manager')
      ->getStorage('media')
      ->load($media->id());
    return $stored instanceof Media && $stored->isPublished();
  }

}
