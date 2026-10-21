<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_messenger\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Queue\QueueFactoryInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\aculta_lms_messenger\Hook\ActivityDeadlineReminderCronHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests only deadlines in the final day are sent to the queue.
 */
#[CoversClass(ActivityDeadlineReminderCronHooks::class)]
#[Group('aculta_lms_messenger')]
final class ActivityDeadlineReminderCronHooksTest extends UnitTestCase {

  /**
   * Adds the relation UUID and state version to the reminder queue.
   */
  public function testQueuesUpcomingClassDeadline(): void {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['value' => '2026-10-21T23:00:00']]);
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('get')->with('field_activity_due_at')->willReturn($field);
    $relationship->method('uuid')->willReturn('deadline-uuid');
    $relationship->method('getChangedTime')->willReturn(1_792_589_100);

    $storage = $this->createMock(EntityStorageInterface::class);
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([31]);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with([31])->willReturn([31 => $relationship]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('group_relationship')->willReturn($storage);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())
      ->method('createItem')
      ->with([
        'deadline_uuid' => 'deadline-uuid',
        'event_version' => substr(hash('sha256', 'deadline-uuid|2026-10-21T23:00:00|1792589100'), 0, 32),
      ]);
    $queueFactory = $this->createMock(QueueFactoryInterface::class);
    $queueFactory->method('get')->willReturn($queue);
    $time = $this->createConfiguredMock(TimeInterface::class, ['getRequestTime' => 1_792_589_200]);
    $hooks = new ActivityDeadlineReminderCronHooks(
      $entityTypeManager,
      $queueFactory,
      $time,
      $this->createMock(LoggerInterface::class),
    );

    $hooks->cron();
  }

}
