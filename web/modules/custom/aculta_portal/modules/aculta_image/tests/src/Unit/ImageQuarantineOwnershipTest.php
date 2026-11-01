<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\FileRemoverInterface;
use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\Quarantine\ImageQuarantine;
use Drupal\aculta_portal\Cleanup\FileReferenceInspectorInterface;
use Drupal\aculta_portal\Cleanup\UnusedFileCleaner;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that quarantine delegates reference checks to the Portal coordinator.
 */
#[Group('aculta_image')]
final class ImageQuarantineOwnershipTest extends UnitTestCase {

  /**
   * A file with globally detected references cannot be moved to quarantine.
   */
  public function testReferencedFileIsNotMoved(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn('50');
    $file->method('uuid')->willReturn('file-uuid');
    $file->method('getMimeType')->willReturn('image/jpeg');
    $file->method('getFileUri')->willReturn('private://test/image.jpg');
    $file->method('isTemporary')->willReturn(FALSE);

    $fileUsage = $this->createMock(FileUsageInterface::class);
    $fileUsage->method('listUsage')->with($file)->willReturn(['node' => ['article' => ['1' => '1']]]);
    $schema = $this->createMock(Schema::class);
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $inspector = $this->createMock(FileReferenceInspectorInterface::class);
    $remover = $this->createMock(FileRemoverInterface::class);
    $remover->expects(self::never())->method('delete');
    $logger = $this->createMock(LoggerChannelInterface::class);
    $image = $this->createMock(ImageInterface::class);
    $factory = $this->createMock(ImageFactory::class);
    $factory->method('get')->willReturn($image);
    $cleaner = new UnusedFileCleaner(
      $fileUsage,
      $database,
      $inspector,
      $remover,
      new ImageInspector($factory),
      $logger,
    );

    $repository = $this->createMock(FileRepositoryInterface::class);
    $repository->expects(self::never())->method('move');
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->expects(self::never())->method('prepareDirectory');
    $quarantine = new ImageQuarantine(
      $database,
      $repository,
      $fileSystem,
      $cleaner,
      $this->createMock(TimeInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
    );

    self::assertFalse($quarantine->enviar($file, 'referenciada'));
  }

  /**
   * Quarantine fails closed when the shared coordinator is unavailable.
   */
  public function testUnavailableCoordinatorPreventsQuarantine(): void {
    $file = $this->createMock(FileInterface::class);
    $repository = $this->createMock(FileRepositoryInterface::class);
    $repository->expects(self::never())->method('move');
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->expects(self::never())->method('prepareDirectory');
    $quarantine = new ImageQuarantine(
      $this->createMock(Connection::class),
      $repository,
      $fileSystem,
      NULL,
      $this->createMock(TimeInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
    );

    self::assertFalse($quarantine->enviar($file, 'coordinator unavailable'));
  }

}
