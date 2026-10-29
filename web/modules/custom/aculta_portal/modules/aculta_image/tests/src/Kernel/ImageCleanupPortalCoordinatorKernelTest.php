<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Kernel;

use Drupal\aculta_portal\Cleanup\FileReferenceInspector;
use Drupal\aculta_portal\Cleanup\UnusedFileCleaner;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Verifies image cleanup against the real Portal reference coordinator.
 */
#[Group('aculta_image')]
#[RunTestsInSeparateProcesses]
final class ImageCleanupPortalCoordinatorKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'file', 'image', 'media', 'entity_usage', 'media_duplicates', 'aculta_image',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->setDefinition('aculta_portal.file_reference_inspector', new Definition(
      FileReferenceInspector::class,
      [new Reference('entity_type.manager'), new Reference('entity_field.manager'), new Reference('database')],
    ));
    $container->setDefinition('aculta_portal.cleanup.logger', (new Definition(
      LoggerChannelInterface::class,
      ['aculta_portal_cleanup'],
    ))->setFactory([new Reference('logger.factory'), 'get']));
    $container->setDefinition('aculta_portal.unused_file_cleaner', new Definition(
      UnusedFileCleaner::class,
      [
        new Reference('file.usage'),
        new Reference('database'),
        new Reference('aculta_portal.file_reference_inspector'),
        new Reference('aculta_image.file_remover'),
        new Reference('aculta_image.inspector'),
        new Reference('aculta_portal.cleanup.logger'),
      ],
    ));
  }

  /**
   * Tests an unreferenced image is removed through the Portal coordinator.
   */
  public function testUnusedImageIsDeletedByPortalCoordinatedCleanup(): void {
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('aculta_image', ['aculta_image_fingerprint', 'aculta_image_origin', 'aculta_image_quarantine']);
    $this->installConfig(['media']);

    MediaType::create([
      'id' => 'image',
      'label' => 'Image',
      'source' => 'image',
      'source_configuration' => ['source_field' => 'field_test_image'],
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_test_image',
      'entity_type' => 'media',
      'type' => 'image',
    ])->save();
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('media', 'field_test_image'),
      'bundle' => 'image',
      'label' => 'Test image',
    ])->save();

    $file = $this->createFixtureImage('unused');
    $referencedFile = $this->createFixtureImage('referenced');
    $structuredFile = $this->createFixtureImage('structured');
    $this->container->get('file.usage')->add($referencedFile, 'test', 'node', '123');
    Media::create([
      'bundle' => 'image',
      'name' => 'Fixture media reference',
      'field_test_image' => ['target_id' => $structuredFile->id(), 'alt' => 'fixture'],
    ])->save();

    $queue = $this->container->get('queue')->get('aculta_image_replaced_files', TRUE);
    $queue->createItem(['fid' => (int) $file->id()]);
    $queue->createItem(['fid' => (int) $referencedFile->id()]);
    $queue->createItem(['fid' => (int) $structuredFile->id()]);

    $result = $this->container->get('aculta_image.cleanup')->run(3);

    self::assertSame(['processed' => 3, 'deleted' => 1], $result);
    self::assertNull($this->container->get('entity_type.manager')->getStorage('file')->load($file->id()));
    self::assertFalse(file_exists($this->container->get('file_system')->realpath($file->getFileUri())));
    self::assertNotNull($this->container->get('entity_type.manager')->getStorage('file')->load($referencedFile->id()));
    self::assertNotNull($this->container->get('file_system')->realpath($referencedFile->getFileUri()));
    self::assertNotNull($this->container->get('entity_type.manager')->getStorage('file')->load($structuredFile->id()));
    self::assertNotNull($this->container->get('file_system')->realpath($structuredFile->getFileUri()));
  }

  /**
   * Creates a harmless image-mime fixture in the test public files directory.
   */
  private function createFixtureImage(string $suffix): File {
    $directory = 'public://aculta-image-coordinator-test';
    $this->container->get('file_system')->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    );
    $filename = 'coordinator-fixture-' . $suffix . '.jpg';
    $uri = $directory . '/' . $filename;
    $this->container->get('file_system')->saveData('not-an-image', $uri, FileExists::Replace);
    $file = File::create([
      'uri' => $uri,
      'filename' => $filename,
      'filemime' => 'image/jpeg',
      'status' => 1,
      'uid' => 1,
    ]);
    $file->save();

    return $file;
  }

}
