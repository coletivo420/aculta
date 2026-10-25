<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Hook\PrivateMessageUnreadIndicatorHooks;
use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Aligns Private Message inbox decoration with the Messenger unread rule.
 */
#[CoversClass(PrivateMessageUnreadIndicatorHooks::class)]
#[Group('aculta_messenger')]
final class PrivateMessageUnreadIndicatorHooksTest extends UnitTestCase {

  /**
   * Removes contrib's inclusive-boundary marker when the thread is read.
   */
  public function testReadInboxThreadHasNoUnreadMarker(): void {
    $sameSecondIncoming = $this->createMock(PrivateMessageInterface::class);
    $sameSecondIncoming->method('getCreatedTime')->willReturn(1200);
    $sameSecondIncoming->method('getOwnerId')->willReturn(84);
    $thread = $this->thread(updated: 1200, lastAccess: 1200, messages: [$sameSecondIncoming]);
    $build = ['#prefix' => '<div class="private-message-thread private-message-thread-inbox unread-thread" data-thread="7">'];

    $this->hooks()->entityViewAlter($build, $thread, $this->display('inbox'));

    self::assertSame(
      '<div class="private-message-thread private-message-thread-inbox" data-thread="7">',
      $build['#prefix'],
    );
  }

  /**
   * Skips the canonical count when contrib did not mark a row unread.
   */
  public function testInboxRowWithoutUnreadMarkerDoesNotRescanThread(): void {
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->expects(self::never())->method('get');
    $build = ['#prefix' => '<div class="private-message-thread private-message-thread-inbox">'];

    $this->hooks()->entityViewAlter($build, $thread, $this->display('inbox'));

    self::assertSame('<div class="private-message-thread private-message-thread-inbox">', $build['#prefix']);
  }

  /**
   * Preserves contrib's marker when another member sent a newer message.
   */
  public function testUnreadInboxThreadKeepsUnreadMarker(): void {
    $message = $this->createMock(PrivateMessageInterface::class);
    $message->method('getCreatedTime')->willReturn(1200);
    $message->method('getOwnerId')->willReturn(84);
    $thread = $this->thread(updated: 1200, lastAccess: 1000, messages: [$message]);
    $build = ['#prefix' => '<div class="private-message-thread private-message-thread-inbox unread-thread">'];

    $this->hooks()->entityViewAlter($build, $thread, $this->display('inbox'));

    self::assertStringContainsString('unread-thread', $build['#prefix']);
  }

  /**
   * Leaves other view modes untouched and does not query unread state.
   */
  public function testNonInboxThreadViewIsUntouched(): void {
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->expects(self::never())->method('get');
    $build = ['#prefix' => '<div class="private-message-thread unread-thread">'];

    $this->hooks()->entityViewAlter($build, $thread, $this->display('full'));

    self::assertSame('<div class="private-message-thread unread-thread">', $build['#prefix']);
  }

  /**
   * Creates the hook with the production counter's boundary rule. */
  private function hooks(): PrivateMessageUnreadIndicatorHooks {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(42);
    $service = $this->createMock(PrivateMessageServiceInterface::class);
    return new PrivateMessageUnreadIndicatorHooks(new UnreadThreadCounter($account, $service));
  }

  /**
   * Creates a thread fixture for the counter. */
  private function thread(int $updated, int $lastAccess, array $messages): PrivateMessageThreadInterface {
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $field = new \stdClass();
    $field->value = (string) $updated;
    $thread->method('get')->with('updated')->willReturn($field);
    $thread->method('getLastAccessTimestamp')->willReturn($lastAccess);
    $thread->method('access')->willReturn(AccessResult::allowed());
    $thread->method('getMessages')->willReturn($messages);
    return $thread;
  }

  /**
   * Creates a view display stub for one mode. */
  private function display(string $mode): EntityViewDisplayInterface {
    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->method('getMode')->willReturn($mode);
    return $display;
  }

}
