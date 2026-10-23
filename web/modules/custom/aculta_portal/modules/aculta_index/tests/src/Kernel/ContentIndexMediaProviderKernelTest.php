<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies production Media providers through Index backfill and CRUD.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexMediaProviderKernelTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'aculta_index',
    'aculta_index_media_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));

    // Load exact owner declarations without enabling the Portal dependency
    // chain in this focused Kernel harness.
    $ownerServices = [
      [
        'file' => '/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/aculta_pdf_viewer_group.services.yml',
        'id' => 'aculta_pdf_viewer_group.course_document_content_index_provider',
      ],
      [
        'file' => '/modules/custom/aculta_portal/modules/aculta_forum/aculta_forum.services.yml',
        'id' => 'aculta_forum.attachment_content_index_provider',
      ],
    ];
    foreach ($ownerServices as $ownerService) {
      $serviceMap = Yaml::parseFile(DRUPAL_ROOT . $ownerService['file'], Yaml::PARSE_CUSTOM_TAGS)['services'];
      $service = $serviceMap[$ownerService['id']];
      $definition = (new Definition($service['class'], $service['arguments']))->setPublic(TRUE);
      foreach ($service['tags'] as $tag) {
        $definition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
      }
      $container->setDefinition($ownerService['id'], $definition);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    $this->installConfig(['media', 'image', 'system', 'user']);
    aculta_index_install();
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    $this->createMediaType('file', ['id' => 'document', 'label' => 'Document']);
    $this->createMediaType('file', ['id' => 'course_document', 'label' => 'Course document']);
    $this->createMediaType('file', ['id' => 'forum_attachment', 'label' => 'Forum attachment']);
  }

  /**
   * Backfills an image Media, updates its label and removes it via the worker.
   */
  public function testImageMediaProjectionFollowsEntityCrud(): void {
    $media = $this->createImageMedia('First image name');
    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame('image', $registry->findProviderIdForBundle('media', 'image'));
    self::assertNull($registry->get('image')->getAccessPurpose($media));

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'Image provider backfill should complete.');
    $this->processImageWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
    self::assertSame('First image name', $this->entryLabel($generationId, (string) $media->id()));

    $media->setName('Updated image name');
    $media->save();
    $this->processImageWork();
    self::assertSame('Updated image name', $this->entryLabel($generationId, (string) $media->id()));

    $media->delete();
    $this->processImageWork();
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'image')
      ->condition('entity_type', 'media')
      ->condition('entity_id', (string) $media->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Document owner provider projects only metadata and follows entity CRUD.
   */
  public function testDocumentMediaProjectionFollowsEntityCrud(): void {
    $media = $this->createDocumentMedia('First document name');
    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame('document', $registry->findProviderIdForBundle('media', 'document'));
    self::assertNull($registry->get('document')->getAccessPurpose($media));

    $generationId = $this->buildAndActivateGeneration();
    self::assertSame('First document name', $this->entryLabel($generationId, (string) $media->id(), 'document'));

    $media->setName('Updated document name');
    $media->save();
    $this->processProviderWork('document');
    self::assertSame('Updated document name', $this->entryLabel($generationId, (string) $media->id(), 'document'));

    $media->delete();
    $this->processProviderWork('document');
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'document')
      ->condition('entity_type', 'media')
      ->condition('entity_id', (string) $media->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * The PDF Group owner's real YAML registers the private course document.
   */
  public function testCourseDocumentProviderUsesOwnerYamlAndFollowsCrud(): void {
    $media = $this->createDocumentMedia('Course reading', 'course_document');
    self::assertFalse($media->isPublished());
    $mediaType = MediaType::load('course_document');
    $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType)->getName();
    self::assertSame('private', parse_url($media->get($sourceField)->entity->getFileUri(), PHP_URL_SCHEME));

    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame('course_document', $registry->findProviderIdForBundle('media', 'course_document'));
    self::assertSame('courses', $registry->get('course_document')->getAccessPurpose($media));

    $generationId = $this->buildAndActivateGeneration();
    self::assertSame('Course reading', $this->entryLabel($generationId, (string) $media->id(), 'course_document'));

    $media->setName('Updated course reading');
    $media->save();
    $this->processProviderWork('course_document');
    self::assertSame('Updated course reading', $this->entryLabel($generationId, (string) $media->id(), 'course_document'));

    $media->delete();
    $this->processProviderWork('course_document');
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'course_document')
      ->condition('entity_type', 'media')
      ->condition('entity_id', (string) $media->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * The Forum owner's real YAML registers private Forum attachment Media.
   */
  public function testForumAttachmentProviderUsesOwnerYamlAndFollowsCrud(): void {
    $media = $this->createDocumentMedia('Forum attachment', 'forum_attachment');
    self::assertFalse($media->isPublished());
    $mediaType = MediaType::load('forum_attachment');
    $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType)->getName();
    self::assertSame('private', parse_url($media->get($sourceField)->entity->getFileUri(), PHP_URL_SCHEME));

    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame('forum_attachment', $registry->findProviderIdForBundle('media', 'forum_attachment'));
    self::assertSame('forum', $registry->get('forum_attachment')->getAccessPurpose($media));

    $generationId = $this->buildAndActivateGeneration();
    self::assertSame('Forum attachment', $this->entryLabel($generationId, (string) $media->id(), 'forum_attachment'));

    $media->setName('Updated Forum attachment');
    $media->save();
    $this->processProviderWork('forum_attachment');
    self::assertSame('Updated Forum attachment', $this->entryLabel($generationId, (string) $media->id(), 'forum_attachment'));

    $media->delete();
    $this->processProviderWork('forum_attachment');
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum_attachment')
      ->condition('entity_type', 'media')
      ->condition('entity_id', (string) $media->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Creates an image Media with a source File that must stay out of the Index.
   */
  private function createImageMedia(string $name): Media {
    $file = File::create([
      'uri' => 'public://content-index-image-fixture.jpg',
      'filename' => 'content-index-image-fixture.jpg',
      'filemime' => 'image/jpeg',
      'status' => TRUE,
    ]);
    $file->save();

    $mediaType = MediaType::load('image');
    $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType)->getName();
    $media = Media::create([
      'bundle' => 'image',
      'name' => $name,
      'status' => TRUE,
      $sourceField => ['target_id' => $file->id()],
    ]);
    $media->save();
    return $media;
  }

  /**
   * Creates a document Media with a source File.
   */
  private function createDocumentMedia(string $name, string $bundle = 'document'): Media {
    $fileName = 'content-index-' . $bundle . '-fixture.pdf';
    $file = File::create([
      'uri' => (in_array($bundle, ['course_document', 'forum_attachment'], TRUE) ? 'private://' . $bundle . '/' : 'public://') . $fileName,
      'filename' => $fileName,
      'filemime' => 'application/pdf',
      'status' => TRUE,
    ]);
    $file->save();

    $mediaType = MediaType::load($bundle);
    $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType)->getName();
    $media = Media::create([
      'bundle' => $bundle,
      'name' => $name,
      'status' => !in_array($bundle, ['course_document', 'forum_attachment'], TRUE),
      $sourceField => ['target_id' => $file->id()],
    ]);
    $media->save();
    return $media;
  }

  /**
   * Builds, drains and activates a complete generation for the test providers.
   */
  private function buildAndActivateGeneration(): string {
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'Media provider backfill should complete.');
    $this->processProviderWork('image');
    $this->processProviderWork('document');
    $this->processProviderWork('course_document');
    $this->processProviderWork('forum_attachment');
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
    return $generationId;
  }

  /**
   * Processes queued work belonging to the image provider.
   */
  private function processImageWork(): void {
    $this->processProviderWork('image');
  }

  /**
   * Processes queued work belonging to one Media provider.
   */
  private function processProviderWork(string $providerId): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', $providerId)
      ->condition('status', 'queued')
      ->execute()
      ->fetchCol();
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    foreach ($ids as $id) {
      $worker->processItem(['work_id' => (int) $id]);
    }
  }

  /**
   * Returns one image projection's label.
   */
  private function entryLabel(string $generationId, string $entityId, string $providerId = 'image'): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', $providerId)
      ->condition('entity_type', 'media')
      ->condition('entity_id', $entityId)
      ->execute()
      ->fetchField();
  }

}
