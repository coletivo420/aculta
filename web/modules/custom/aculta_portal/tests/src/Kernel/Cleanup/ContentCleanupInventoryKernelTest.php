<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Cleanup;

use Drupal\aculta_portal\Cleanup\ContentCleanupPreview;
use Drupal\aculta_file_uploader\Cleanup\FileCleanupAssessorInterface;
use Drupal\aculta_file_uploader\Cleanup\FileCleanupStatus;
use Drupal\aculta_portal\Cleanup\MediaCleanupAssessorInterface;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies cleanup inventory queries with real File and Media entity storage.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class ContentCleanupInventoryKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'field', 'image', 'media', 'filter', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'user', 'filter', 'field', 'image', 'media']);
  }

  /**
   * Uses real entity queries, ID keys, and keyset paging for both entity types.
   */
  public function testInventoriesFileAndMediaWithBoundedPages(): void {
    $files = [];
    for ($index = 1; $index <= 3; $index++) {
      $file = File::create([
        'uid' => 0,
        'filename' => 'inventory-' . $index . '.txt',
        'uri' => 'public://inventory-' . $index . '.txt',
        'status' => 1,
      ]);
      $file->save();
      $files[] = $file;
    }

    $fileAssessor = $this->createMock(FileCleanupAssessorInterface::class);
    $fileAssessor->expects(self::exactly(3))->method('assess')->willReturn(FileCleanupStatus::InUse);
    $mediaAssessor = $this->createMock(MediaCleanupAssessorInterface::class);
    $mediaAssessor->expects(self::never())->method('assess');

    $preview = new ContentCleanupPreview(
      $this->container->get('entity_type.manager'),
      $fileAssessor,
      $mediaAssessor,
    );

    $first = $preview->inventory('file', NULL, 2);
    $second = $preview->inventory('file', $first['next_after_id'], 2);
    $media = $preview->inventory('media', NULL, 2);

    self::assertTrue($first['dry_run']);
    self::assertFalse($first['executed']);
    self::assertTrue($first['has_more']);
    self::assertSame((string) $files[1]->id(), $first['next_after_id']);
    self::assertSame([(string) $files[0]->id(), (string) $files[1]->id()], array_column($first['items'], 'id'));
    self::assertFalse($second['has_more']);
    self::assertNull($second['next_after_id']);
    self::assertSame([(string) $files[2]->id()], array_column($second['items'], 'id'));
    self::assertSame([], $media['items']);
    self::assertSame(['requested' => 0, 'candidate' => 0, 'retained' => 0], $media['summary']['media']);
    self::assertSame(3, count($files));
  }

}
