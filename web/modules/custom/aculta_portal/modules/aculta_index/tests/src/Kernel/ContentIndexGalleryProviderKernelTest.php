<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\media_gallery\Entity\MediaGallery;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies Gallery projections and selected image relations through Index.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexGalleryProviderKernelTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'file',
    'image',
    'media',
    'views',
    'media_library',
    'photoswipe',
    'media_gallery',
    'aculta_index',
    'aculta_index_gallery_test',
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
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('media_gallery');
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
    $this->installConfig(['media', 'image', 'media_gallery', 'system', 'user']);
    aculta_index_install();
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
  }

  /**
   * The real Gallery provider projects metadata and images through CRUD.
   */
  public function testGalleryProjectionAndImageRelationsFollowCrud(): void {
    $image = $this->createImageMedia();
    $gallery = MediaGallery::create([
      'title' => 'Kernel gallery',
      'status' => TRUE,
      'langcode' => 'en',
      'images' => [['target_id' => $image->id()]],
    ]);
    $gallery->save();

    $registry = $this->container->get('aculta_index.provider_registry');
    $this->assertSame('gallery', $registry->findProviderIdForBundle('media_gallery', 'media_gallery'));
    $this->assertInstanceOf(ContentIndexProviderInterface::class, $registry->get('gallery'));
    $this->assertSame('magazine', $registry->get('gallery')->getAccessPurpose($gallery));

    $generationId = $this->buildAndActivateGeneration();
    $this->assertSame('Kernel gallery', $this->entryLabel($generationId, (string) $gallery->id(), 'gallery'));
    $this->assertSame([(string) $image->id()], $this->galleryImageTargets($generationId, (string) $gallery->id()));

    $gallery->setTitle('Updated gallery');
    $gallery->set('images', []);
    $gallery->save();
    $this->processProviderWork('gallery');
    $this->assertSame('Updated gallery', $this->entryLabel($generationId, (string) $gallery->id(), 'gallery'));
    $this->assertSame([], $this->galleryImageTargets($generationId, (string) $gallery->id()));

    $gallery->delete();
    $this->processProviderWork('gallery');
    $this->assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'gallery')
      ->condition('entity_id', (string) $gallery->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Creates a real image Media entity for the Gallery base reference field.
   */
  private function createImageMedia(): Media {
    $file = File::create([
      'uri' => 'public://content-index-gallery-fixture.jpg',
      'filename' => 'content-index-gallery-fixture.jpg',
      'filemime' => 'image/jpeg',
      'status' => TRUE,
    ]);
    $file->save();
    $mediaType = MediaType::load('image');
    $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType)->getName();
    $image = Media::create([
      'bundle' => 'image',
      'name' => 'Gallery image fixture',
      'status' => TRUE,
      $sourceField => ['target_id' => $file->id()],
    ]);
    $image->save();
    return $image;
  }

  /**
   * Builds and activates a generation with both Gallery and image providers.
   */
  private function buildAndActivateGeneration(): string {
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    $this->assertTrue($complete, 'Gallery provider backfill should complete.');
    $this->processProviderWork('gallery');
    $this->processProviderWork('image');
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
    return $generationId;
  }

  /**
   * Processes queued work for one provider using the production worker.
   */
  private function processProviderWork(string $providerId): void {
    $database = $this->container->get('database');
    $workIds = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', $providerId)
      ->condition('status', 'queued')
      ->execute()
      ->fetchCol();
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    foreach ($workIds as $workId) {
      $worker->processItem(['work_id' => (int) $workId]);
    }
  }

  /**
   * Returns a projected Gallery label.
   */
  private function entryLabel(string $generationId, string $entityId, string $providerId): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', $providerId)
      ->condition('entity_id', $entityId)
      ->execute()
      ->fetchField();
  }

  /**
   * Returns image IDs linked through the allowlisted Gallery relation.
   *
   * @return string[]
   *   Related Media IDs.
   */
  private function galleryImageTargets(string $generationId, string $galleryId): array {
    return array_map('strval', $this->container->get('database')->select('aculta_index_relation', 'relation')
      ->fields('relation', ['target_entity_id'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'gallery')
      ->condition('source_entity_id', $galleryId)
      ->condition('relation_type', 'gallery_image')
      ->execute()
      ->fetchCol());
  }

}
