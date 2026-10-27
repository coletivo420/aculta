<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Avatar;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\aculta_image\FileRemoverInterface;
use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_portal\Cleanup\FileReferenceInspectorInterface;
use Drupal\aculta_portal\Cleanup\FileReferenceStatus;
use Drupal\aculta_portal\Cleanup\UnusedFileCleaner;
use Drupal\aculta_user\Avatar\AvatarAssetCleanup;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies avatar cleanup delegates global reference checks to Portal.
 */
#[Group('aculta_user')]
final class AvatarAssetCleanupCoordinatorTest extends UnitTestCase {

  /**
   * Temporary uploads use Portal inspection before physical deletion.
   */
  public function testTemporaryAvatarRequiresClearGlobalReferences(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn('42');
    $file->method('getFileUri')->willReturn('temporary://pictures/avatar.png');
    $file->method('getMimeType')->willReturn('image/png');
    $file->method('isTemporary')->willReturn(TRUE);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturnOnConsecutiveCalls([42], [], []);
    $fileStorage = $this->createMock(EntityStorageInterface::class);
    $fileStorage->method('getQuery')->willReturn($query);
    $fileStorage->method('loadMultiple')->willReturnOnConsecutiveCalls([$file], [], []);
    $cropStorage = $this->createMock(EntityStorageInterface::class);
    $cropStorage->method('loadMultiple')->willReturn([]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['file', $fileStorage],
      ['crop', $cropStorage],
    ]);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->with('entity_usage')->willReturn(FALSE);
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $references = $this->createMock(FileReferenceInspectorInterface::class);
    $references->expects(self::once())->method('inspect')->with($file)->willReturn(FileReferenceStatus::Clear);
    $remover = $this->createMock(FileRemoverInterface::class);
    $remover->expects(self::once())->method('delete')->with($file, 'portal_unused_file_cleanup')->willReturn(TRUE);
    $fileUsage = $this->createMock(FileUsageInterface::class);
    $fileUsage->method('listUsage')->with($file)->willReturn([]);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $cleaner = new UnusedFileCleaner(
      $fileUsage,
      $database,
      $references,
      $remover,
      new ImageInspector($this->createMock(ImageFactory::class)),
      $logger,
    );

    $cleanup = new AvatarAssetCleanup($entities, $this->time(5000), $cleaner);

    self::assertSame([
      'temporarios' => 1,
      'acervo_orfao' => 0,
      'pessoais_orfas' => 0,
      'recortes_orfaos' => 0,
    ], $cleanup->run());
  }

  /**
   * Creates a request clock mock.
   */
  private function time(int $timestamp): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($timestamp);
    return $time;
  }

}
