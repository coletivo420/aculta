<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Cleanup;

use Drupal\aculta_portal\Cleanup\FileCleanupStatus;
use Drupal\aculta_portal\Cleanup\FileReferenceInspectorInterface;
use Drupal\aculta_portal\Cleanup\FileReferenceStatus;
use Drupal\aculta_portal\Cleanup\UnusedMediaCleaner;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\media\MediaInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests safe Portal deletion of orphan Media candidates.
 *
 * @group aculta_portal
 */
final class UnusedMediaCleanerTest extends UnitTestCase {

  /**
   * Tests an unpublished, unreferenced candidate is deleted through Entity API.
   */
  public function testDeletesUnpublishedOrphanCandidate(): void {
    $media = $this->media(FALSE);
    $media->expects(self::once())->method('delete');
    $inspector = $this->createMock(FileReferenceInspectorInterface::class);
    $inspector->expects(self::once())->method('inspect')->with($media)->willReturn(FileReferenceStatus::Clear);
    $cleaner = new UnusedMediaCleaner($inspector, $this->createMock(LoggerChannelInterface::class));

    self::assertSame(FileCleanupStatus::Deleted, $cleaner->removeIfUnused($media));
  }

  /**
   * Tests published candidates are retained without scanning or deletion.
   */
  public function testRetainsPublishedCandidate(): void {
    $media = $this->media(TRUE);
    $media->expects(self::never())->method('delete');
    $inspector = $this->createMock(FileReferenceInspectorInterface::class);
    $inspector->expects(self::never())->method('inspect');
    $cleaner = new UnusedMediaCleaner($inspector, $this->createMock(LoggerChannelInterface::class));

    self::assertSame(FileCleanupStatus::OwnerRejected, $cleaner->removeIfUnused($media));
  }

  /**
   * Tests references block removal.
   */
  public function testRetainsReferencedCandidate(): void {
    $media = $this->media(FALSE);
    $media->expects(self::never())->method('delete');
    $inspector = $this->createMock(FileReferenceInspectorInterface::class);
    $inspector->method('inspect')->willReturn(FileReferenceStatus::Referenced);
    $cleaner = new UnusedMediaCleaner($inspector, $this->createMock(LoggerChannelInterface::class));

    self::assertSame(FileCleanupStatus::InUse, $cleaner->removeIfUnused($media));
  }

  /**
   * Creates a Media test double with controlled publication state.
   */
  private function media(bool $published): MediaInterface {
    $media = $this->createMock(MediaInterface::class);
    $media->method('isPublished')->willReturn($published);
    $media->method('id')->willReturn('17');
    return $media;
  }

}
