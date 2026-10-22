<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Cleanup;

use Drupal\aculta_image\FileRemoverInterface;
use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_portal\Cleanup\FileCleanupStatus;
use Drupal\aculta_portal\Cleanup\FileReferenceInspectorInterface;
use Drupal\aculta_portal\Cleanup\FileReferenceStatus;
use Drupal\aculta_portal\Cleanup\UnusedFileCleaner;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests Portal coordination and owner delegation for unused files.
 *
 * @group aculta_portal
 */
final class UnusedFileCleanerTest extends UnitTestCase {

  /**
   * Tests that an unused image is removed by the image owner.
   */
  public function testDelegatesUnusedImageToImageOwner(): void {
    $file = $this->file('image/jpeg');
    $remover = $this->createMock(FileRemoverInterface::class);
    $remover->expects(self::once())->method('delete')->with($file, 'portal_unused_file_cleanup')->willReturn(TRUE);
    $inspector = $this->createMock(FileReferenceInspectorInterface::class);
    $inspector->expects(self::once())->method('inspect')->with($file)->willReturn(FileReferenceStatus::Clear);
    $cleaner = $this->cleaner([], $remover, $inspector);

    self::assertSame(FileCleanupStatus::Deleted, $cleaner->removeIfUnused($file));
  }

  /**
   * Tests that Core File Usage retains a referenced file.
   */
  public function testRetainsFileUsedByCore(): void {
    $file = $this->file('application/pdf');
    $remover = $this->createMock(FileRemoverInterface::class);
    $remover->expects(self::never())->method('delete');
    $inspector = $this->createMock(FileReferenceInspectorInterface::class);
    $inspector->expects(self::never())->method('inspect');
    $cleaner = $this->cleaner(['media' => ['document' => ['4' => '1']]], $remover, $inspector);

    self::assertSame(FileCleanupStatus::InUse, $cleaner->assess($file));
  }

  /**
   * Tests that incomplete reference inspection fails closed.
   */
  public function testRetainsFileWhenReferenceScanIsUnverifiable(): void {
    $file = $this->file('application/pdf');
    $remover = $this->createMock(FileRemoverInterface::class);
    $remover->expects(self::never())->method('delete');
    $inspector = $this->createMock(FileReferenceInspectorInterface::class);
    $inspector->method('inspect')->willReturn(FileReferenceStatus::Unverifiable);
    $cleaner = $this->cleaner([], $remover, $inspector);

    self::assertSame(FileCleanupStatus::Unverifiable, $cleaner->removeIfUnused($file));
  }

  /**
   * Tests that tracker failures fail closed.
   */
  public function testRetainsFileWhenUsageTrackerThrows(): void {
    $file = $this->file('application/pdf');
    $remover = $this->createMock(FileRemoverInterface::class);
    $remover->expects(self::never())->method('delete');
    $inspector = $this->createMock(FileReferenceInspectorInterface::class);
    $inspector->expects(self::never())->method('inspect');
    $fileUsage = $this->createMock(FileUsageInterface::class);
    $fileUsage->method('listUsage')->willThrowException(new \RuntimeException('tracker unavailable'));
    $cleaner = $this->cleanerWithUsageService($fileUsage, $remover, $inspector);

    self::assertSame(FileCleanupStatus::Unverifiable, $cleaner->removeIfUnused($file));
  }

  /**
   * Tests that a non-image file is removed through the File entity API.
   */
  public function testRemovesUnusedNonImageThroughEntityApi(): void {
    $file = $this->file('application/pdf');
    $file->expects(self::once())->method('delete');
    $remover = $this->createMock(FileRemoverInterface::class);
    $remover->expects(self::never())->method('delete');
    $inspector = $this->createMock(FileReferenceInspectorInterface::class);
    $inspector->method('inspect')->willReturn(FileReferenceStatus::Clear);
    $cleaner = $this->cleaner([], $remover, $inspector);

    self::assertSame(FileCleanupStatus::Deleted, $cleaner->removeIfUnused($file));
  }

  /**
   * Creates the cleaner with a mocked inspection and usage boundary.
   *
   * @param array $usage
   *   Core File Usage rows to return.
   * @param \Drupal\aculta_image\FileRemoverInterface $remover
   *   Image owner implementation.
   * @param \Drupal\aculta_portal\Cleanup\FileReferenceInspectorInterface $inspector
   *   Reference inspector.
   */
  private function cleaner(array $usage, FileRemoverInterface $remover, FileReferenceInspectorInterface $inspector): UnusedFileCleaner {
    $fileUsage = $this->createMock(FileUsageInterface::class);
    $fileUsage->method('listUsage')->willReturn($usage);
    return $this->cleanerWithUsageService($fileUsage, $remover, $inspector);
  }

  /**
   * Creates a cleaner with an explicit File Usage service double.
   */
  private function cleanerWithUsageService(FileUsageInterface $fileUsage, FileRemoverInterface $remover, FileReferenceInspectorInterface $inspector): UnusedFileCleaner {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->with('entity_usage')->willReturn(FALSE);
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $image = $this->createMock(ImageInterface::class);
    $image->method('isValid')->willReturn(FALSE);
    $factory = $this->createMock(ImageFactory::class);
    $factory->method('get')->willReturn($image);
    $imageInspector = new ImageInspector($factory);
    return new UnusedFileCleaner($fileUsage, $database, $inspector, $remover, $imageInspector, $logger);
  }

  /**
   * Creates a permanent File test double.
   */
  private function file(string $mime): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn('50');
    $file->method('uuid')->willReturn('file-uuid');
    $file->method('getMimeType')->willReturn($mime);
    $file->method('getFileUri')->willReturn('private://test/document.pdf');
    $file->method('isTemporary')->willReturn(FALSE);
    return $file;
  }

}
