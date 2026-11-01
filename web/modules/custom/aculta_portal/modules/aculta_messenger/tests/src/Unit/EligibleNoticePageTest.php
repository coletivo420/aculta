<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\EligibleNoticePage;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests pagination over current access-eligible notices.
 */
#[CoversClass(EligibleNoticePage::class)]
#[Group('aculta_messenger')]
final class EligibleNoticePageTest extends UnitTestCase {

  /**
   * Access-revoked rows do not consume the page offset or lookahead.
   */
  public function testOffsetAndLookaheadCountOnlyResolvedRows(): void {
    $queryOffsets = [];
    $page = EligibleNoticePage::collect(
      static function (int $offset, int $size) use (&$queryOffsets): array {
        $queryOffsets[] = [$offset, $size];
        return range(1, 20);
      },
      static fn(array $ids): array => $ids,
      static fn(int $id): ?array => $id <= 3 ? NULL : ['title' => 'Notice ' . $id],
      2,
      2,
    );

    self::assertSame([[0, 20]], $queryOffsets);
    self::assertSame([6, 7], array_column($page['items'], 'entity'));
    self::assertSame(['Notice 6', 'Notice 7'], array_column(array_column($page['items'], 'resolved'), 'title'));
    self::assertTrue($page['has_next']);
  }

  /**
   * Pagination scans later batches when the current source rows are denied.
   */
  public function testContinuesScanningAfterAnInaccessibleBatch(): void {
    $queryOffsets = [];
    $page = EligibleNoticePage::collect(
      static function (int $offset, int $size) use (&$queryOffsets): array {
        $queryOffsets[] = [$offset, $size];
        return $offset === 0 ? range(1, 20) : range(21, 40);
      },
      static fn(array $ids): array => $ids,
      static fn(int $id): ?array => $id < 21 ? NULL : ['title' => 'Notice ' . $id],
      2,
      0,
    );

    self::assertSame([[0, 20], [20, 20]], $queryOffsets);
    self::assertSame([21, 22], array_column($page['items'], 'entity'));
    self::assertTrue($page['has_next']);
  }

}
