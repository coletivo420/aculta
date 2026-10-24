<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\PersonalNoticeAdapterInterface;
use Drupal\aculta_messenger\Hook\PersonalNoticeAdapterHooks;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests aggregation of optional adapters into the shared personal notice UI.
 */
#[CoversClass(PersonalNoticeAdapterHooks::class)]
#[Group('aculta_messenger')]
final class PersonalNoticeAdapterHooksTest extends UnitTestCase {

  /**
   * Dispatches list contributions and unread counts to enabled adapters only.
   */
  public function testOptionalAdaptersExtendListAndCount(): void {
    $account = $this->createMock(AccountInterface::class);
    $lms = $this->createMock(PersonalNoticeAdapterInterface::class);
    $lms->expects(self::once())
      ->method('addPersonalNotices')
      ->with(self::anything(), $account, ['origem' => 'wiki'])
      ->willReturnCallback(static function (array &$items): void {
        $items[] = 'lms';
      });
    $lms->expects(self::once())->method('unreadCount')->with(42)->willReturn(2);
    $wiki = $this->createMock(PersonalNoticeAdapterInterface::class);
    $wiki->expects(self::once())
      ->method('addPersonalNotices')
      ->with(self::anything(), $account, ['origem' => 'wiki'])
      ->willReturnCallback(static function (array &$items): void {
        $items[] = 'wiki';
      });
    $wiki->expects(self::once())->method('unreadCount')->with(42)->willReturn(3);
    $hooks = new PersonalNoticeAdapterHooks(['lms' => $lms, 'wiki' => $wiki]);
    $items = ['forum'];
    $count = 4;

    $hooks->noticesAlter($items, $account, ['origem' => 'wiki']);
    $hooks->unreadCountAlter($count, 42);

    self::assertSame(['forum', 'lms', 'wiki'], $items);
    self::assertSame(9, $count);
  }

}
