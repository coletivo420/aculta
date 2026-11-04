<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Unit\Cleanup;

use Drupal\aculta_file_uploader\Cleanup\FileDeletionOwnerInterface;
use Drupal\aculta_file_uploader\Cleanup\FileReferenceInspectorInterface;
use Drupal\aculta_file_uploader\Cleanup\FileReferenceStatus;
use Drupal\aculta_file_uploader\Cleanup\UnusedFileCleaner;
use Drupal\aculta_file_uploader\ImageDimensionReader;
use Drupal\aculta_lms_resources\Cleanup\CourseDocumentCleanup;
use Drupal\aculta_portal\Cleanup\UnusedMediaCleaner;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\aculta_lms_resources\Cleanup\CourseDocumentCleanup
 * @group aculta_lms_resources
 */
final class CourseDocumentCleanupTest extends UnitTestCase {

  /**
   * A shared Media reference prevents both Media and File removal.
   *
   * @covers ::removeDetachedDocument
   */
  public function testReferencedMediaIsRetained(): void {
    [$cleanup, $media, , $inspector, $deletionOwner] = $this->cleanup(FileReferenceStatus::Referenced);
    $inspector->expects(self::once())->method('inspect')->with($media)->willReturn(FileReferenceStatus::Referenced);
    $media->expects(self::never())->method('delete');
    $deletionOwner->expects(self::never())->method('delete');

    $cleanup->removeDetachedDocument(42);
  }

  /**
   * Published or unrelated Media is never submitted to the shared cleaner.
   *
   * @covers ::removeDetachedDocument
   * @dataProvider protectedMediaProvider
   */
  public function testPublishedOrUnrelatedMediaIsRetained(bool $published, string $bundle): void {
    [$cleanup, $media, , $inspector, $deletionOwner] = $this->cleanup(
      FileReferenceStatus::Clear,
      mediaPublished: $published,
      bundle: $bundle,
    );
    $inspector->expects(self::never())->method('inspect');
    $media->expects(self::never())->method('delete');
    $deletionOwner->expects(self::never())->method('delete');

    $cleanup->removeDetachedDocument(42);
  }

  /**
   * Provides owner states that must fail closed.
   *
   * @return array<string, array{bool, string}>
   *   Publication and bundle combinations.
   */
  public static function protectedMediaProvider(): array {
    return [
      'published course document' => [TRUE, 'course_document'],
      'unrelated media bundle' => [FALSE, 'document'],
    ];
  }

  /**
   * Group may invoke relationship hooks after the target Media is deleted.
   *
   * @covers ::removeDetachedDocument
   */
  public function testAlreadyDeletedMediaIsIgnored(): void {
    [$cleanup, , , $inspector, $deletionOwner] = $this->cleanup(
      FileReferenceStatus::Clear,
      mediaPresent: FALSE,
    );
    $inspector->expects(self::never())->method('inspect');
    $deletionOwner->expects(self::never())->method('delete');

    $cleanup->removeDetachedDocument(42);
  }

  /**
   * A shared File is retained even after its now-orphan Media is removed.
   *
   * @covers ::removeDetachedDocument
   */
  public function testFileCleanerRechecksAndRetainsSharedFile(): void {
    [$cleanup, $media, , , $deletionOwner] = $this->cleanup(
      FileReferenceStatus::Clear,
      FileReferenceStatus::Referenced,
    );
    $media->expects(self::once())->method('delete');
    $deletionOwner->expects(self::never())->method('delete');

    $cleanup->removeDetachedDocument(42);
  }

  /**
   * The File owner is called only after Portal removes an orphan Media.
   *
   * @covers ::removeDetachedDocument
   */
  public function testOrphanMediaAndFileUseTheirOwners(): void {
    [$cleanup, $media, $file, , $deletionOwner] = $this->cleanup(
      FileReferenceStatus::Clear,
      FileReferenceStatus::Clear,
    );
    $media->expects(self::once())->method('delete');
    $deletionOwner->expects(self::once())
      ->method('delete')
      ->with($file, 'portal_unused_file_cleanup')
      ->willReturn(TRUE);

    $cleanup->removeDetachedDocument(42);
  }

  /**
   * Creates the real Portal cleaners with controlled reference inspection.
   *
   * @return array{0: \Drupal\aculta_lms_resources\Cleanup\CourseDocumentCleanup, 1: \Drupal\media\MediaInterface, 2: \Drupal\file\FileInterface, 3: \Drupal\aculta_file_uploader\Cleanup\FileReferenceInspectorInterface, 4: \Drupal\aculta_file_uploader\Cleanup\FileDeletionOwnerInterface}
   *   Cleanup service, Media, File, reference inspector, and file owner.
   */
  private function cleanup(
    FileReferenceStatus $mediaStatus,
    ?FileReferenceStatus $fileStatus = NULL,
    bool $mediaPublished = FALSE,
    string $bundle = 'course_document',
    bool $mediaPresent = TRUE,
  ): array {
    $file = $this->createMock(FileInterface::class);
    $file->method('isTemporary')->willReturn(FALSE);
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFileUri')->willReturn('private://course/test.pdf');
    $file->method('id')->willReturn('84');

    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('__get')->with('entity')->willReturn($file);
    $media = $this->createMock(MediaInterface::class);
    $media->method('id')->willReturn('42');
    $media->method('bundle')->willReturn($bundle);
    $media->method('isPublished')->willReturn($mediaPublished);
    $media->method('hasField')->with('field_media_course_document')->willReturn(TRUE);
    $media->method('get')->with('field_media_course_document')->willReturn($field);

    $inspector = $this->createMock(FileReferenceInspectorInterface::class);
    $inspector->method('inspect')->willReturnCallback(
      static function ($entity) use ($media, $mediaStatus, $fileStatus): FileReferenceStatus {
        return $entity === $media ? $mediaStatus : ($fileStatus ?? FileReferenceStatus::Clear);
      },
    );
    $logger = $this->createMock(LoggerChannelInterface::class);
    $unusedMediaCleaner = new UnusedMediaCleaner($inspector, $logger);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->with('entity_usage')->willReturn(FALSE);
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $usage = $this->createMock(FileUsageInterface::class);
    $usage->method('listUsage')->with($file)->willReturn([]);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn(FALSE);
    $dimensionReader = new ImageDimensionReader($fileSystem);
    $deletionOwner = $this->createMock(FileDeletionOwnerInterface::class);
    $unusedFileCleaner = new UnusedFileCleaner($usage, $database, $inspector, $deletionOwner, $dimensionReader, $logger);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(42)->willReturn($mediaPresent ? $media : NULL);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('media')->willReturn($storage);

    return [
      new CourseDocumentCleanup($entityTypeManager, $unusedMediaCleaner, $unusedFileCleaner),
      $media,
      $file,
      $inspector,
      $deletionOwner,
    ];
  }

}
