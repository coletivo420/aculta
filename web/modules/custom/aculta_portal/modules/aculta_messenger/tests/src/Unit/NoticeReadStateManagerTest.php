<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeManagerInterface;
use Drupal\aculta_messenger\Service\NoticeReadStateManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Container\ContainerInterface;

/**
 * Tests dispatch to read-state adapters and optional adapter behavior.
 */
#[CoversClass(NoticeReadStateManager::class)]
#[Group('aculta_messenger')]
final class NoticeReadStateManagerTest extends UnitTestCase {

  /**
   * Delegates state changes for a recognized notice to its owning adapter.
   */
  public function testDispatchesReadStateToMatchingAdapter(): void {
    $message = $this->createMock(MessageInterface::class);
    $bundle = 'aculta_msg_wiki_notice';
    $message->method('bundle')->willReturnCallback(static function () use (&$bundle): string {
      return $bundle;
    });
    $message->method('getOwnerId')->willReturn(8);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with(12)->willReturn($message);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::once())->method('getStorage')->with('message')->willReturn($storage);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn('8');
    $adapter = $this->createMock(WikiNoticeManagerInterface::class);
    $adapter->expects(self::once())->method('setRead')->with($message, $account, FALSE)->willReturn(TRUE);
    $container = $this->createMock(ContainerInterface::class);
    $container->expects(self::once())->method('has')->with('wiki')->willReturn(TRUE);
    $container->expects(self::once())->method('get')->with('wiki')->willReturn($adapter);

    $manager = new NoticeReadStateManager($entities, $container);

    self::assertTrue($manager->setRead(12, $account, FALSE));
  }

  /**
   * Friendship Messages dispatch to the core-owned friendship adapter.
   */
  public function testDispatchesFriendshipReadState(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn('aculta_msg_friendship');
    $message->method('getOwnerId')->willReturn(8);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with(44)->willReturn($message);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('message')->willReturn($storage);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn('8');
    $adapter = $this->createMock(FriendshipNoticeManagerInterface::class);
    $adapter->expects(self::once())->method('setRead')->with($message, $account, TRUE)->willReturn(TRUE);
    $container = $this->createMock(ContainerInterface::class);
    $container->expects(self::once())->method('has')->with('friendship')->willReturn(TRUE);
    $container->expects(self::once())->method('get')->with('friendship')->willReturn($adapter);

    self::assertTrue((new NoticeReadStateManager($entities, $container))->setRead(44, $account, TRUE));
  }

  /**
   * A source adapter that revokes access keeps the central action denied.
   */
  public function testRevokedSourceAccessIsPropagatedToCentralAction(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn('aculta_msg_wiki_notice');
    $message->method('getOwnerId')->willReturn(8);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with(12)->willReturn($message);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('message')->willReturn($storage);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn('8');
    $adapter = $this->createMock(WikiNoticeManagerInterface::class);
    $adapter->expects(self::once())->method('setRead')->with($message, $account, TRUE)->willReturn(FALSE);
    $container = $this->createMock(ContainerInterface::class);
    $container->method('has')->with('wiki')->willReturn(TRUE);
    $container->method('get')->with('wiki')->willReturn($adapter);

    self::assertFalse((new NoticeReadStateManager($entities, $container))->setRead(12, $account, TRUE));
  }

  /**
   * Rejects another member's notice before touching an optional source adapter.
   */
  public function testOtherOwnerNoticeIsRejectedBeforeAdapterResolution(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn('aculta_msg_wiki_notice');
    $message->method('getOwnerId')->willReturn(99);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with(12)->willReturn($message);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('message')->willReturn($storage);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn('8');
    $container = $this->createMock(ContainerInterface::class);
    $container->expects(self::never())->method('has');
    $container->expects(self::never())->method('get');

    self::assertFalse((new NoticeReadStateManager($entities, $container))->setRead(12, $account, TRUE));
  }

  /**
   * Invalid notice IDs are rejected before entity storage is queried.
   */
  public function testInvalidMessageIdsAreRejectedBeforeLoading(): void {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $container = $this->createMock(ContainerInterface::class);
    $container->expects(self::never())->method('has');
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $manager = new NoticeReadStateManager($entities, $container);

    self::assertFalse($manager->setRead(0, $account, TRUE));
    self::assertFalse($manager->setRead(-1, $account, TRUE));
  }

  /**
   * Optional or unsupported notice sources fail closed.
   */
  public function testDisabledAndUnsupportedAdaptersFailClosed(): void {
    $message = $this->createMock(MessageInterface::class);
    $bundle = 'aculta_msg_wiki_notice';
    $message->method('bundle')->willReturnCallback(static function () use (&$bundle): string {
      return $bundle;
    });
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(12)->willReturn($message);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('message')->willReturn($storage);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $container = $this->createMock(ContainerInterface::class);
    $container->expects(self::once())->method('has')->with('wiki')->willReturn(FALSE);
    $manager = new NoticeReadStateManager($entities, $container);

    self::assertFalse($manager->setRead(12, $account, TRUE));

    $bundle = 'unsupported_template';
    self::assertFalse($manager->setRead(12, $account, TRUE));
  }

  /**
   * Anonymous accounts cannot use the read-state dispatcher.
   */
  public function testAnonymousAccountIsRejectedBeforeLoadingMessage(): void {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $container = $this->createMock(ContainerInterface::class);
    $container->expects(self::never())->method('has');
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(FALSE);

    self::assertFalse((new NoticeReadStateManager($entities, $container))->setRead(12, $account, TRUE));
  }

}
