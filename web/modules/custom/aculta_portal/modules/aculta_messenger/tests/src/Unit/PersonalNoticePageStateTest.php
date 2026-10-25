<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\PersonalNoticePageState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests page slicing after the source has applied access/read filters.
 */
#[CoversClass(PersonalNoticePageState::class)]
#[Group('aculta_messenger')]
final class PersonalNoticePageStateTest extends UnitTestCase {

  /**
   * The page is bounded and exposes one-row lookahead.
   */
  public function testSliceUsesOffsetAndLookahead(): void {
    $items = ['notice-1', 'notice-2', 'notice-3'];
    $firstPage = new PersonalNoticePageState('alerta', 0);
    self::assertSame(['notice-1', 'notice-2'], $firstPage->slice($items, 2));
    self::assertTrue($firstPage->hasNext());

    $lastPage = new PersonalNoticePageState('alerta', 2);
    self::assertSame(['notice-3'], $lastPage->slice($items, 2));
    self::assertFalse($lastPage->hasNext());
  }

  /**
   * Empty and out-of-range pages do not claim a following page.
   */
  public function testSliceHandlesEmptyAndPastEndOffsets(): void {
    $state = new PersonalNoticePageState('alerta', 100);
    self::assertSame([], $state->slice(['notice-1'], 10));
    self::assertFalse($state->hasNext());
  }

  /**
   * Zero-sized pages are rejected instead of creating a non-advancing pager.
   */
  public function testSliceRejectsNonPositiveLimit(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new PersonalNoticePageState('alerta', 0))->slice(['notice-1'], 0);
  }

}
