<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Event\ActivityDeadlineAssignedEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests stable and changing versions of persisted deadline states.
 */
#[CoversClass(ActivityDeadlineAssignedEvent::class)]
#[Group('aculta_lms')]
final class ActivityDeadlineAssignedEventTest extends UnitTestCase {

  /**
   * Reverting to an earlier date after a save yields a new event version.
   */
  public function testVersionChangesForEveryPersistedUpdate(): void {
    $first = ActivityDeadlineAssignedEvent::versionFor('relation-uuid', '2026-10-22T12:00:00', 100);
    $sameState = ActivityDeadlineAssignedEvent::versionFor('relation-uuid', '2026-10-22T12:00:00', 100);
    $changedDate = ActivityDeadlineAssignedEvent::versionFor('relation-uuid', '2026-10-23T12:00:00', 101);
    $revertedDate = ActivityDeadlineAssignedEvent::versionFor('relation-uuid', '2026-10-22T12:00:00', 102);

    self::assertSame($first, $sameState);
    self::assertNotSame($first, $changedDate);
    self::assertNotSame($first, $revertedDate);
    self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $revertedDate);
  }

}
