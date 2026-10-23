<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\Entity\Domain;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\media_gallery\Entity\MediaGallery;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

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
    'domain',
    'aculta_index',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->setDefinition('aculta_portal.domain_purpose', (new Definition(DomainPurposeManager::class, [
      new Reference('entity_type.manager'),
      new Reference('domain.negotiator'),
      new Reference('config.factory'),
      new Reference('request_stack'),
    ]))->setPublic(TRUE));

    // Compile the actual owner provider declarations without enabling their
    // modules or loading unrelated dependency chains into this Kernel.
    foreach ([
      'aculta_gallery' => 'aculta_gallery.content_index_provider',
      'aculta_image' => 'aculta_image.content_index_provider',
    ] as $owner => $serviceId) {
      $servicesFile = DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/' . $owner . '/' . $owner . '.services.yml';
      $service = Yaml::parseFile($servicesFile, Yaml::PARSE_CUSTOM_TAGS)['services'][$serviceId];
      $definition = (new Definition($service['class'], $service['arguments'] ?? []))->setPublic(TRUE);
      foreach ($service['tags'] ?? [] as $tag) {
        $definition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
      }
      $container->setDefinition($serviceId, $definition);
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
    $this->installEntitySchema('media_gallery');
    $this->installEntitySchema('domain');
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
    $this->installConfig(['media', 'image', 'media_gallery', 'domain', 'system', 'user']);
    aculta_index_install();
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    $this->createDomains();
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
   * Gallery Entity Access is checked in the canonical purpose and fails closed.
   */
  public function testGalleryAccessUsesMagazinePurposeAndFailsClosed(): void {
    // Keep the tested reader away from Core's UID 1 access bypass.
    User::create(['name' => 'gallery-access-root-fixture', 'status' => TRUE])->save();
    $role = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $role);
    $role->grantPermission('view published media gallery entities')->save();
    $reader = User::create([
      'name' => 'gallery-index-reader',
      'status' => TRUE,
      'roles' => ['authenticated'],
    ]);
    $reader->save();

    $published = MediaGallery::create([
      'title' => 'Published gallery',
      'status' => TRUE,
      'uid' => $reader->id(),
      'langcode' => 'en',
    ]);
    $published->save();
    $unpublished = MediaGallery::create([
      'title' => 'Unpublished gallery',
      'status' => FALSE,
      'uid' => $reader->id(),
      'langcode' => 'en',
    ]);
    $unpublished->save();

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);
    $this->assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());

    $filter = $this->container->get('aculta_index.access_filter');
    $candidates = [
      [
        'entity_type' => 'media_gallery',
        'entity_id' => (string) $published->id(),
        'langcode' => 'en',
      ],
      [
        'entity_type' => 'media_gallery',
        'entity_id' => (string) $unpublished->id(),
        'langcode' => 'en',
      ],
    ];
    $result = $filter->filter($candidates, $reader);
    $this->assertCount(1, $result->entities);
    $this->assertSame((string) $published->id(), (string) $result->entities[0]->id());
    $this->assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    $this->assertSame('aculta_org', $negotiator->getActiveDomain()?->id());

    $magazineDomain = Domain::load('revista420_aculta_org');
    self::assertInstanceOf(Domain::class, $magazineDomain);
    $magazineDomain->delete();
    $this->assertCount(0, $filter->filter([$candidates[0]], $reader)->entities);
    $this->assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    $this->assertSame('aculta_org', $negotiator->getActiveDomain()?->id());
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
   * Creates the active and canonical Magazine Domains for access checks.
   */
  private function createDomains(): void {
    foreach (['aculta_org' => 'main', 'revista420_aculta_org' => 'magazine'] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
        'weight' => 0,
      ])->save();
    }
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
