<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_messenger\Unit;

use Drupal\aculta_lms_messenger\LearningNoticeManagerInterface;
use Drupal\aculta_lms_messenger\Plugin\QueueWorker\ActivityDeadlineReminderWorker;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests queued deadline reminders use current, opaque source data.
 */
#[CoversClass(ActivityDeadlineReminderWorker::class)]
#[Group('aculta_lms_messenger')]
final class ActivityDeadlineReminderWorkerTest extends UnitTestCase {

  /**
   * Sends only a UUID and opaque event version to the notice manager.
   */
  public function testValidQueueItemPublishesReminder(): void {
    $notices = $this->createMock(LearningNoticeManagerInterface::class);
    $notices->expects(self::once())
      ->method('publishActivityDeadlineReminderForLearners')
      ->with('deadline-uuid', str_repeat('a', 32));
    $worker = new ActivityDeadlineReminderWorker([], 'test', [], $notices);

    $worker->processItem([
      'deadline_uuid' => 'deadline-uuid',
      'event_version' => str_repeat('a', 32),
    ]);
  }

  /**
   * Ignores malformed queue items without calling the notice manager.
   */
  public function testMalformedQueueItemIsIgnored(): void {
    $notices = $this->createMock(LearningNoticeManagerInterface::class);
    $notices->expects(self::never())->method('publishActivityDeadlineReminderForLearners');
    $worker = new ActivityDeadlineReminderWorker([], 'test', [], $notices);

    $worker->processItem(['deadline_uuid' => 'deadline-uuid', 'event_version' => 'invalid']);
  }

}
