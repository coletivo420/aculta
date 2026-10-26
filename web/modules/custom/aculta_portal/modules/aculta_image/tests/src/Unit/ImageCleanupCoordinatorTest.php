<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\ImageCleanup;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies that queued images are not removed without Portal coordination.
 */
#[Group('aculta_image')]
final class ImageCleanupCoordinatorTest extends UnitTestCase {

  /**
   * Coordinator absence retains a claimed item for retry after its lease.
   */
  public function testMissingCoordinatorRetainsQueueItemAndDoesNotDelete(): void {
    $item = (object) ['data' => ['fid' => 42], 'item_id' => 9];
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())
      ->method('claimItem')
      ->willReturn($item);
    $queue->expects(self::never())->method('deleteItem');

    $queues = $this->createMock(QueueFactory::class);
    $queues->method('get')->with('aculta_image_replaced_files', TRUE)->willReturn($queue);

    $file = $this->createMock(FileInterface::class);
    $file->method('getMimeType')->willReturn('image/jpeg');
    $files = $this->createMock(EntityStorageInterface::class);
    $files->method('load')->with(42)->willReturn($file);
    $files->expects(self::never())->method('delete');
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('file')->willReturn($files);

    $cleanup = new ImageCleanup(
      $this->createMock(Connection::class),
      $entities,
      $queues,
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(TimeInterface::class),
      NULL,
    );

    self::assertSame(['processed' => 1, 'deleted' => 0], $cleanup->run(1));
  }

}
