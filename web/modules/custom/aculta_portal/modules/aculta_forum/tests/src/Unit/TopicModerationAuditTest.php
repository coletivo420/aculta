<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\TopicModerationAudit;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Administrative audit of split and merge actions (no public exposure).
 */
#[CoversClass(TopicModerationAudit::class)]
#[Group('aculta_forum')]
final class TopicModerationAuditTest extends TestCase {

  /**
   * Records keep action, destination, moderator, time and count per topic.
   */
  public function testRecordsAreKeptPerTopicInOrder(): void {
    $audit = new TopicModerationAudit(new KeyValueMemoryFactory());

    $audit->record(10, 'split', 20, 7, 1700000000, 2);
    $audit->record(10, 'merge', 30, 8, 1700000100, 5);
    $audit->record(40, 'merge', 10, 7, 1700000200, 1);

    $entries = $audit->entries(10);
    self::assertCount(2, $entries);
    self::assertSame(['action' => 'split', 'destination' => 20, 'uid' => 7, 'time' => 1700000000, 'count' => 2], $entries[0]);
    self::assertSame('merge', $entries[1]['action']);
    self::assertSame(5, $entries[1]['count']);
    self::assertCount(1, $audit->entries(40));
  }

  /**
   * A topic without records returns an empty list.
   */
  public function testTopicWithoutRecordsIsEmpty(): void {
    $audit = new TopicModerationAudit(new KeyValueMemoryFactory());
    self::assertSame([], $audit->entries(99));
  }

}
