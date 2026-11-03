<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Cleanup;

use Drupal\aculta_portal\Cleanup\ContentCleanupPreview;
use Drupal\aculta_file_uploader\Cleanup\FileCleanupAssessorInterface;
use Drupal\aculta_file_uploader\Cleanup\FileCleanupStatus;
use Drupal\aculta_portal\Cleanup\MediaCleanupAssessorInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the bounded, privacy-minimized content cleanup preview.
 */
#[Group('aculta_portal')]
final class ContentCleanupPreviewTest extends UnitTestCase {

  /**
   * Includes only explicit IDs and reports status without deleting entities.
   */
  public function testPreviewIsReadOnlyAndReportsMissingAndRetainedAssets(): void {
    $file = $this->createMock(FileInterface::class);
    $media = $this->createMock(MediaInterface::class);
    $fileStorage = $this->createMock(EntityStorageInterface::class);
    $fileStorage->expects(self::once())->method('loadMultiple')->with(['2', '9'])->willReturn(['2' => $file]);
    $mediaStorage = $this->createMock(EntityStorageInterface::class);
    $mediaStorage->expects(self::once())->method('loadMultiple')->with(['5'])->willReturn(['5' => $media]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::exactly(2))->method('getStorage')->willReturnCallback(
      fn (string $type): EntityStorageInterface => $type === 'file' ? $fileStorage : $mediaStorage,
    );
    $fileAssessor = $this->createMock(FileCleanupAssessorInterface::class);
    $fileAssessor->expects(self::once())->method('assess')->with($file)->willReturn(FileCleanupStatus::Available);
    $mediaAssessor = $this->createMock(MediaCleanupAssessorInterface::class);
    $mediaAssessor->expects(self::once())->method('assess')->with($media)->willReturn(FileCleanupStatus::InUse);
    $file->expects(self::never())->method('delete');
    $media->expects(self::never())->method('delete');

    $preview = new ContentCleanupPreview($entityTypeManager, $fileAssessor, $mediaAssessor);
    $result = $preview->preview([9, '2', 2], ['5']);

    self::assertTrue($result['dry_run']);
    self::assertFalse($result['executed']);
    self::assertSame([
      ['type' => 'file', 'id' => '2', 'status' => 'available', 'candidate' => TRUE],
      ['type' => 'file', 'id' => '9', 'status' => 'not_found', 'candidate' => FALSE],
      ['type' => 'media', 'id' => '5', 'status' => 'in_use', 'candidate' => FALSE],
    ], $result['items']);
    self::assertSame([
      'file' => ['requested' => 2, 'candidate' => 1, 'retained' => 1],
      'media' => ['requested' => 1, 'candidate' => 0, 'retained' => 1],
    ], $result['summary']);
  }

  /**
   * Rejects malformed IDs and batches above the fixed bound before loading.
   */
  public function testRejectsMalformedOrUnboundedInput(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');
    $preview = new ContentCleanupPreview(
      $entityTypeManager,
      $this->createMock(FileCleanupAssessorInterface::class),
      $this->createMock(MediaCleanupAssessorInterface::class),
    );

    try {
      $preview->preview(['../1'], []);
      self::fail('Malformed IDs must be rejected.');
    }
    catch (\InvalidArgumentException) {
      self::assertTrue(TRUE);
    }

    try {
      $preview->preview(range(1, 101), []);
      self::fail('Oversized batches must be rejected.');
    }
    catch (\InvalidArgumentException) {
      self::assertTrue(TRUE);
    }
  }

  /**
   * Inventories a bounded page without exposing asset metadata or deleting.
   */
  public function testInventoryUsesAccessIndependentKeysetPagination(): void {
    $first = $this->createMock(FileInterface::class);
    $second = $this->createMock(FileInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('getQuery')->willReturn($query = $this->createMock(QueryInterface::class));
    $query->expects(self::once())->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->expects(self::once())->method('condition')->with('fid', '1', '>')->willReturnSelf();
    $query->expects(self::once())->method('sort')->with('fid')->willReturnSelf();
    $query->expects(self::once())->method('range')->with(0, 3)->willReturnSelf();
    $query->expects(self::once())->method('execute')->willReturn(['2' => '2', '3' => '3', '4' => '4']);
    $storage->expects(self::once())->method('loadMultiple')->with(['2', '3'])->willReturn([
      '2' => $first,
      '3' => $second,
    ]);

    $definition = $this->createMock(EntityTypeInterface::class);
    $definition->expects(self::once())->method('getKey')->with('id')->willReturn('fid');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())->method('getDefinition')->with('file')->willReturn($definition);
    $entityTypeManager->expects(self::exactly(2))->method('getStorage')->with('file')->willReturn($storage);

    $fileAssessor = $this->createMock(FileCleanupAssessorInterface::class);
    $fileAssessor->expects(self::exactly(2))->method('assess')->willReturnOnConsecutiveCalls(
      FileCleanupStatus::Available,
      FileCleanupStatus::InUse,
    );
    $mediaAssessor = $this->createMock(MediaCleanupAssessorInterface::class);
    $mediaAssessor->expects(self::never())->method('assess');
    $first->expects(self::never())->method('delete');
    $second->expects(self::never())->method('delete');

    $report = (new ContentCleanupPreview($entityTypeManager, $fileAssessor, $mediaAssessor))
      ->inventory('file', 1, 2);

    self::assertTrue($report['dry_run']);
    self::assertFalse($report['executed']);
    self::assertTrue($report['has_more']);
    self::assertSame('3', $report['next_after_id']);
    self::assertSame([
      ['type' => 'file', 'id' => '2', 'status' => 'available', 'candidate' => TRUE],
      ['type' => 'file', 'id' => '3', 'status' => 'in_use', 'candidate' => FALSE],
    ], $report['items']);
  }

  /**
   * Inventories Media using its own ID key and returns an empty final page.
   */
  public function testMediaInventoryUsesMediaIdAndStopsAtFinalPage(): void {
    $media = $this->createMock(MediaInterface::class);
    $query = $this->createMock(QueryInterface::class);
    $query->expects(self::exactly(2))->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->expects(self::once())->method('condition')->with('mid', '5', '>')->willReturnSelf();
    $query->expects(self::exactly(2))->method('sort')->with('mid')->willReturnSelf();
    $query->expects(self::exactly(2))->method('range')->with(0, 3)->willReturnSelf();
    $query->expects(self::exactly(2))->method('execute')->willReturnOnConsecutiveCalls(['5'], []);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::exactly(2))->method('getQuery')->willReturn($query);
    $storage->expects(self::once())->method('loadMultiple')->with(['5'])->willReturn(['5' => $media]);

    $definition = $this->createMock(EntityTypeInterface::class);
    $definition->expects(self::exactly(2))->method('getKey')->with('id')->willReturn('mid');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::exactly(2))->method('getDefinition')->with('media')->willReturn($definition);
    $entityTypeManager->expects(self::exactly(3))->method('getStorage')->with('media')->willReturn($storage);
    $fileAssessor = $this->createMock(FileCleanupAssessorInterface::class);
    $fileAssessor->expects(self::never())->method('assess');
    $mediaAssessor = $this->createMock(MediaCleanupAssessorInterface::class);
    $mediaAssessor->expects(self::once())->method('assess')->with($media)->willReturn(FileCleanupStatus::OwnerRejected);

    $preview = new ContentCleanupPreview($entityTypeManager, $fileAssessor, $mediaAssessor);
    $firstPage = $preview->inventory('media', NULL, 2);
    $lastPage = $preview->inventory('media', 5, 2);

    self::assertFalse($firstPage['has_more']);
    self::assertNull($firstPage['next_after_id']);
    self::assertSame('owner_rejected', $firstPage['items'][0]['status']);
    self::assertFalse($lastPage['has_more']);
    self::assertSame([], $lastPage['items']);
  }

  /**
   * Rejects unsupported types, invalid cursors, and invalid page sizes.
   */
  public function testInventoryRejectsInvalidArgumentsBeforeQuerying(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getDefinition');
    $entityTypeManager->expects(self::never())->method('getStorage');
    $preview = new ContentCleanupPreview(
      $entityTypeManager,
      $this->createMock(FileCleanupAssessorInterface::class),
      $this->createMock(MediaCleanupAssessorInterface::class),
    );

    foreach ([
      ['node', NULL, 50],
      ['file', 0, 50],
      ['media', NULL, 101],
    ] as [$type, $cursor, $limit]) {
      try {
        $preview->inventory($type, $cursor, $limit);
        self::fail('Invalid inventory arguments must be rejected.');
      }
      catch (\InvalidArgumentException) {
        self::assertTrue(TRUE);
      }
    }
  }

}
