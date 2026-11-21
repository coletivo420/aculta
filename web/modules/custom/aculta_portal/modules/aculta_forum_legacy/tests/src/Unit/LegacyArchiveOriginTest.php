<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_legacy\Unit;

use Drupal\aculta_forum_legacy\Migration\LegacyArchiveOrigin;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves migrated archive keys are stable, distinct and idempotent.
 */
#[CoversClass(LegacyArchiveOrigin::class)]
#[Group('aculta_forum_legacy')]
final class LegacyArchiveOriginTest extends UnitTestCase {

  /**
   * The same source row always yields the same key (re-runs do not duplicate).
   */
  public function testKeyIsStableForSameSourceRow(): void {
    self::assertSame(LegacyArchiveOrigin::topicKey(42), LegacyArchiveOrigin::topicKey(42));
    self::assertSame(LegacyArchiveOrigin::commentKey(7), LegacyArchiveOrigin::commentKey(7));
  }

  /**
   * A topic node and a comment with the same numeric id never collide.
   */
  public function testTopicAndCommentKeysDoNotCollide(): void {
    self::assertNotSame(LegacyArchiveOrigin::topicKey(5), LegacyArchiveOrigin::commentKey(5));
    self::assertSame('node:5', LegacyArchiveOrigin::topicKey(5));
  }

}
