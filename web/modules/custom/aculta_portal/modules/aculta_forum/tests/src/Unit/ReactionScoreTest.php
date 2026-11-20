<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\ReactionScore;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the existing-reactions minus single-dislike rule.
 */
#[Group('aculta_forum')]
final class ReactionScoreTest extends UnitTestCase {

  /**
   * Every existing positive emoji counts, including disabled old IDs.
   */
  public function testAllPositiveReactionsContributeToOneTotal(): void {
    self::assertSame([
      'positive_count' => 8,
      'dislike_count' => 2,
      'total_count' => 6,
    ], ReactionScore::summarize([
      'broccoli' => '3',
      'heart' => 2,
      'old_disabled_reaction' => '3',
      'dislike' => '2',
    ]));
  }

  /**
   * Negative votes cannot display a negative received-like count.
   */
  public function testDislikesAreClampedWithoutErasingStoredCounts(): void {
    self::assertSame([
      'positive_count' => 1,
      'dislike_count' => 4,
      'total_count' => 0,
    ], ReactionScore::summarize(['thumbs_up' => 1, 'dislike' => 4]));
    self::assertSame([
      'positive_count' => 0,
      'dislike_count' => 0,
      'total_count' => 0,
    ], ReactionScore::summarize([]));
  }

  /**
   * Malformed data is excluded from the public counter.
   */
  public function testInvalidCountsDoNotInflateScore(): void {
    self::assertSame([
      'positive_count' => 2,
      'dislike_count' => 0,
      'total_count' => 2,
    ], ReactionScore::summarize(['broccoli' => '2', 'heart' => 'broken', 'dislike' => -10]));
  }

}
