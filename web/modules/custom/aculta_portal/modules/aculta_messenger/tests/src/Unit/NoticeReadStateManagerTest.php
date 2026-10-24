<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface;
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
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with(12)->willReturn($message);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::once())->method('getStorage')->with('message')->willReturn($storage);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $adapter = $this->createMock(WikiNoticeManagerInterface::class);
    $adapter->expects(self::once())->method('setRead')->with($message, $account, FALSE)->willReturn(TRUE);
    $container = $this->createMock(ContainerInterface::class);
    $container->expects(self::once())->method('has')->with('wiki')->willReturn(TRUE);
    $container->expects(self::once())->method('get')->with('wiki')->willReturn($adapter);

    $manager = new NoticeReadStateManager($entities, $container);

    self::assertTrue($manager->setRead(12, $account, FALSE));
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
