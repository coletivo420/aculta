<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;
use Drupal\aculta_events\Service\OrganizationContributionEventQueue;
use Drupal\Tests\UnitTestCase;

/**
 * Tests durable recovery of Events outcome queue submissions. */
final class OrganizationContributionEventQueueTest extends UnitTestCase {

  /**
   * A failed Queue API write remains recoverable from the minimal outbox. */
  public function testFailedQueueWriteIsRecovered(): void {
    $values = [];
    $store = $this->createMock(KeyValueStoreInterface::class);
    $store->method('set')->willReturnCallback(static function (string $key, mixed $value) use (&$values): void {
      $values[$key] = $value;
    });
    $store->method('getAllKeys')->willReturnCallback(static function () use (&$values): array {
      return array_keys($values);
    });
    $store->method('getMultiple')->willReturnCallback(static function (array $keys) use (&$values): array {
      return array_intersect_key($values, array_flip($keys));
    });
    $store->expects(self::once())->method('delete')->with(hash('sha256', implode(':', [
      'events_location',
      'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
      '42',
      '9',
      'accepted',
    ])))->willReturnCallback(static function (string $key) use (&$values): void {
      unset($values[$key]);
    });
    $keyValueFactory = $this->createMock(KeyValueFactoryInterface::class);
    $keyValueFactory->method('get')->with('aculta_events_contribution_outbox')->willReturn($store);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::exactly(2))
      ->method('createItem')
      ->willReturnOnConsecutiveCalls(FALSE, 1);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->with(OrganizationContributionEventQueue::QUEUE_ID, TRUE)->willReturn($queue);
    $service = new OrganizationContributionEventQueue($queueFactory, $keyValueFactory);
    $event = new OrganizationContributionOutcomeRecordedEvent(
      'events_location',
      'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
      42,
      9,
      'accepted',
    );

    try {
      $service->enqueue($event);
      self::fail('The first Queue API write should fail.');
    }
    catch (\RuntimeException) {
      self::assertCount(1, $values);
      self::assertSame($event->toQueuePayload(), reset($values));
    }

    self::assertSame(1, $service->recoverUndelivered());
  }

}
