<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Learning\ClassStatusHistory;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests scoped, anonymous historical class aggregates.
 */
#[CoversClass(ClassStatusHistory::class)]
#[Group('aculta_lms')]
final class ClassStatusHistoryTest extends UnitTestCase {

  /**
   * Period input is restricted to the documented choices.
   */
  public function testPeriodsAreAllowlistedAndDefaultToSevenDays(): void {
    self::assertSame(7, ClassStatusHistory::normalizePeriod(NULL));
    self::assertSame(7, ClassStatusHistory::normalizePeriod('invalid'));
    self::assertSame(7, ClassStatusHistory::normalizePeriod('8'));
    self::assertSame(7, ClassStatusHistory::normalizePeriod('7'));
    self::assertSame(14, ClassStatusHistory::normalizePeriod('14'));
    self::assertSame(30, ClassStatusHistory::normalizePeriod(30));
  }

  /**
   * The query never includes dimensions outside the selected class.
   */
  public function testSeriesQueriesOnlyTheSelectedCourseClassAndSituationDimensions(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('currentDay')->willReturn(200);
    $storage->expects(self::once())
      ->method('dailyDimensionTotalsForDimensions')
      ->with(
        ClassStatusHistory::METRIC,
        187,
        200,
        [
          '17/28|students',
          '17/28|not_started',
          '17/28|in_progress',
          '17/28|pending_evaluation',
          '17/28|passed',
          '17/28|failed',
        ],
      )
      ->willReturn([
        187 => ['17/28|students' => 5, '17/28|passed' => 2],
        200 => ['17/28|students' => 6, '17/28|in_progress' => 3],
      ]);

    $series = (new ClassStatusHistory($storage))->series(17, 28, 14);

    self::assertNotNull($series);
    self::assertSame(14, $series['days']);
    self::assertSame(187, $series['from']);
    self::assertSame(200, $series['to']);
    self::assertCount(14, $series['points']);
    self::assertSame(5, $series['points'][0]['counts']['students']);
    self::assertSame(2, $series['points'][0]['counts']['passed']);
    self::assertSame(0, $series['points'][0]['counts']['failed']);
    self::assertSame(0, $series['points'][12]['counts']['students']);
    self::assertSame(6, $series['points'][13]['counts']['students']);
    self::assertSame(3, $series['points'][13]['counts']['in_progress']);
    self::assertTrue($series['has_data']);
    self::assertArrayNotHasKey('uid', $series);
    self::assertArrayNotHasKey('students', $series['points'][0]);
  }

  /**
   * Statistics remains an optional integration.
   */
  public function testHistoryIsOptionalWhenStatisticsIsNotEnabled(): void {
    self::assertNull((new ClassStatusHistory(NULL))->series(17, 28, 7));
  }

}
