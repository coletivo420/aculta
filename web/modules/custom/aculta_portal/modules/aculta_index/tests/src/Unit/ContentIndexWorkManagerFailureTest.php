<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Queue\QueueFactory;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

/**
 * Verifies the work manager fails closed when activation reads fail.
 */
#[Group('aculta_index')]
final class ContentIndexWorkManagerFailureTest extends UnitTestCase {

  /**
   * Queue gate reads must not report ready when the query cannot execute.
   */
  public function testActivationGateFailsWhenQueueQueryCannotExecute(): void {
    $connection = $this->createMock(Connection::class);
    $query = $this->createMock(SelectInterface::class);
    $connection->expects(self::once())->method('select')->willReturn($query);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('groupBy')->willReturnSelf();
    $query->method('addExpression')->willReturn('count');
    $query->method('execute')->willReturn(NULL);

    $manager = $this->manager($connection);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Could not read Content Index queue state.');
    $manager->getActivationGateState(['magazine']);
  }

  /**
   * Scheduling must not proceed when the activation fence cannot be read.
   */
  public function testScheduleFailsWhenActivationFenceCannotExecute(): void {
    $connection = $this->createMock(Connection::class);
    $transaction = new class() extends Transaction {

      public function __construct() {}

      public function __destruct() {}

      public function rollBack(): void {}

    };
    $query = $this->createMock(SelectInterface::class);
    $connection->expects(self::once())->method('startTransaction')->willReturn($transaction);
    $connection->expects(self::once())->method('select')->willReturn($query);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn(NULL);

    $manager = $this->manager($connection);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Could not read the ACULTA Content Index activation fence.');
    $manager->schedule('magazine', 'node', '1', 'en');
  }

  /**
   * Builds a work manager backed by the supplied database connection.
   */
  private function manager(Connection $connection): ContentIndexWorkManager {
    return new ContentIndexWorkManager(
      $connection,
      $this->createMock(QueueFactory::class),
      $this->createMock(TimeInterface::class),
      new ContentIndexProviderRegistry(),
      new NullLogger(),
    );
  }

}
