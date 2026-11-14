<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\aculta_file_uploader\Cleanup\FileReferenceInspector;
use Drupal\aculta_file_uploader\Cleanup\FileReferenceStatus;
use Drupal\aculta_file_uploader\Cleanup\UnusedFileCleaner;
use Drupal\aculta_file_uploader\ImageDimensionReader;
use Drupal\aculta_image\FileRemover;
use Drupal\aculta_lms\Resources\Cleanup\CourseDocumentCleanup;
use Drupal\aculta_lms\Hook\CourseDocumentCleanupHooks;
use Drupal\aculta_portal\Cleanup\UnusedMediaCleaner;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\file\Entity\File;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves Group detachment cleans only truly orphan course documents.
 */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class CourseDocumentCleanupKernelTest extends KernelTestBase {

  use MediaTypeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'image',
    'media',
    'entity_usage',
    'field',
    'options',
    'flexible_permissions',
    'group',
    'aculta_lms_resources_gallery_access_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->set('aculta_portal.domain_site', new DomainSiteManager(
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
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    foreach (['user', 'file', 'media', 'group', 'group_relationship', 'group_config_wrapper'] as $entityType) {
      $this->installEntitySchema($entityType);
    }
    $this->installConfig(['system', 'user', 'media', 'group']);

    $mediaType = $this->createMediaType('file', [
      'id' => 'course_document',
      'label' => 'Course document',
    ]);
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => CourseDocumentCleanup::FILE_FIELD,
      'type' => 'file',
      'settings' => ['target_type' => 'file'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'media',
      'bundle' => CourseDocumentCleanup::DOCUMENT_BUNDLE,
      'field_name' => CourseDocumentCleanup::FILE_FIELD,
      'label' => 'Document file',
    ])->save();
    $mediaType->set('source_configuration', ['source_field' => CourseDocumentCleanup::FILE_FIELD]);
    $mediaType->save();
    $groupType = GroupType::create([
      'id' => 'lms_course',
      'label' => 'Course',
      'creator_membership' => FALSE,
    ]);
    $groupType->save();
    $this->container->get('group_relation_type.manager')->clearCachedDefinitions();
    $relationshipTypes = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    assert($relationshipTypes instanceof GroupRelationshipTypeStorageInterface);
    $relationshipTypes->createFromPlugin($groupType, 'aculta_document_viewer:course_document')->save();
    GroupRole::create([
      'id' => 'lms_course-member',
      'label' => 'Member',
      'group_type' => 'lms_course',
      'scope' => 'individual',
      'permissions' => [
        'view aculta_document_viewer:course_document entity',
        'view any unpublished aculta_document_viewer:course_document entity',
      ],
      'weight' => 0,
      'admin' => FALSE,
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Shared documents survive the first detach and are removed after the last.
   */
  public function testSharedDocumentIsRemovedOnlyAfterItsLastGroupReference(): void {
    $owner = $this->createUser();
    $filePath = $this->container->get('file_system')->realpath('public://');
    self::assertIsString($filePath);
    $physicalPath = $filePath . '/course-document-cleanup.pdf';
    self::assertNotFalse(file_put_contents($physicalPath, "%PDF-1.4\ncleanup fixture\n"));
    $file = File::create([
      'uri' => 'public://course-document-cleanup.pdf',
      'filename' => 'course-document-cleanup.pdf',
      'filemime' => 'application/pdf',
      'uid' => $owner->id(),
      'status' => File::STATUS_PERMANENT,
    ]);
    $file->save();
    $media = Media::create([
      'bundle' => CourseDocumentCleanup::DOCUMENT_BUNDLE,
      'name' => 'Shared course document',
      'status' => FALSE,
      CourseDocumentCleanup::FILE_FIELD => [['target_id' => $file->id()]],
    ]);
    $media->save();

    $firstCourse = $this->course($owner, 'First course');
    $secondCourse = $this->course($owner, 'Second course');
    $firstRelationship = $firstCourse->addRelationship($media, 'aculta_document_viewer:course_document');
    $firstRelationship->save();
    $secondCourse->addRelationship($media, 'aculta_document_viewer:course_document')->save();

    $inspector = new FileReferenceInspector(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('database'),
    );
    $relationshipStorage = $this->container->get('entity_type.manager')->getStorage('group_relationship');
    self::assertSame(2, $relationshipStorage->getQuery()->accessCheck(FALSE)->condition('entity_id.target_id', (string) $media->id())->count()->execute());
    self::assertSame(FileReferenceStatus::Referenced, $inspector->inspect($media));

    $cleanup = $this->cleanupHooks();
    $firstRelationship->delete();
    $cleanup->relationshipDeleted($firstRelationship);
    self::assertTrue(Media::load($media->id()) instanceof Media, 'A second Group relationship retains the shared Media.');
    self::assertNotNull(File::load($file->id()), 'A File remains while its Media is retained.');
    self::assertFileExists($physicalPath);

    foreach ($secondCourse->getRelationships() as $relationship) {
      if ($relationship->getPluginId() === 'aculta_document_viewer:course_document') {
        $relationship->delete();
        $cleanup->relationshipDeleted($relationship);
      }
    }
    self::assertFalse(Media::load($media->id()) instanceof Media, 'The final detach removes the unpublished orphan Media.');
    self::assertFalse(File::load($file->id()) instanceof File, 'The shared File cleaner removes the now-unused File.');
    self::assertFileDoesNotExist($physicalPath);
  }

  /**
   * Builds the production cleanup hook with Portal and File Uploader services.
   */
  private function cleanupHooks(): CourseDocumentCleanupHooks {
    $entityTypeManager = $this->container->get('entity_type.manager');
    $logger = $this->container->get('logger.factory')->get('aculta_portal_cleanup');
    $inspector = new FileReferenceInspector(
      $entityTypeManager,
      $this->container->get('entity_field.manager'),
      $this->container->get('database'),
      $logger,
    );
    $unusedMediaCleaner = new UnusedMediaCleaner($inspector, $logger);
    $fileSystem = $this->container->get('file_system');
    $imageReader = new ImageDimensionReader($fileSystem);
    $fileRemover = new FileRemover(
      $this->container->get('file.usage'),
      $entityTypeManager,
      $this->container->get('database'),
      $fileSystem,
      $this->container->get('logger.factory'),
    );
    $unusedFileCleaner = new UnusedFileCleaner(
      $this->container->get('file.usage'),
      $this->container->get('database'),
      $inspector,
      $fileRemover,
      $imageReader,
      $logger,
    );
    return new CourseDocumentCleanupHooks(new CourseDocumentCleanup(
      $entityTypeManager,
      $unusedMediaCleaner,
      $unusedFileCleaner,
    ));
  }

  /**
   * Creates a course Group fixture.
   */
  private function course($owner, string $label): Group {
    $course = Group::create([
      'type' => 'lms_course',
      'label' => $label,
      'uid' => $owner->id(),
    ]);
    $course->save();
    return $course;
  }

}
