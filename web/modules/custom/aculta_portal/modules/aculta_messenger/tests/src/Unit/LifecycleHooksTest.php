<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Hook\LifecycleHooks;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects Messenger integration boundaries and queued data on uninstall.
 *
 * @group aculta_messenger
 */
#[Group('aculta_messenger')]
final class LifecycleHooksTest extends UnitTestCase {

  /**
   * The optional notifier cannot coexist with Messenger.
   */
  public function testOptionalNotifierCannotBeEnabledAlongsideMessenger(): void {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('aculta_messenger')->willReturn(TRUE);
    $hooks = $this->hooks($moduleHandler);

    $this->expectException(\RuntimeException::class);
    $hooks->preventPrivateBodyNotifierConflict('private_message_notify', FALSE);
  }

  /**
   * Uninstall is blocked while private notification jobs remain queued.
   */
  public function testUninstallIsBlockedWhilePrivateNotificationQueueHasItems(): void {
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->once())->method('numberOfItems')->willReturn(1);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects($this->once())
      ->method('get')
      ->with('aculta_msg_private_notification')
      ->willReturn($queue);

    $entityDefinitionManager = $this->createMock(EntityDefinitionUpdateManagerInterface::class);
    $entityDefinitionManager->expects($this->never())->method('getEntityType');
    $hooks = new LifecycleHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $entityDefinitionManager,
      $this->createMock(ModuleHandlerInterface::class),
      $queueFactory,
    );

    $this->expectException(\RuntimeException::class);
    $hooks->protectNotificationData('aculta_messenger', FALSE);
  }

  /**
   * Uninstall is blocked while friendship email jobs remain queued.
   */
  public function testUninstallIsBlockedWhileFriendshipEmailQueueHasItems(): void {
    $emptyQueue = $this->createMock(QueueInterface::class);
    $emptyQueue->method('numberOfItems')->willReturn(0);
    $friendshipQueue = $this->createMock(QueueInterface::class);
    $friendshipQueue->method('numberOfItems')->willReturn(1);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->willReturnCallback(static fn(string $name): QueueInterface =>
      $name === 'aculta_msg_friendship' ? $friendshipQueue : $emptyQueue,
    );
    $definitionManager = $this->createMock(EntityDefinitionUpdateManagerInterface::class);
    $definitionManager->expects(self::never())->method('getEntityType');
    $hooks = new LifecycleHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $definitionManager,
      $this->createMock(ModuleHandlerInterface::class),
      $queueFactory,
    );

    $this->expectExceptionMessage('friendship notice emails remain queued');
    $hooks->protectNotificationData('aculta_messenger', FALSE);
  }

  /**
   * Uninstall is blocked while notice entities remain.
   */
  public function testUninstallIsBlockedWhilePersistentNoticesRemain(): void {
    $emptyQueue = $this->createMock(QueueInterface::class);
    $emptyQueue->method('numberOfItems')->willReturn(0);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->willReturn($emptyQueue);

    $messageQuery = $this->queryReturning(0);
    $nodeQuery = $this->queryReturning(1);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($messageQuery);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($nodeQuery);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->willReturnMap([
      ['message', $messageStorage],
      ['node', $nodeStorage],
    ]);

    $definition = $this->createMock(EntityTypeInterface::class);
    $definitionManager = $this->createMock(EntityDefinitionUpdateManagerInterface::class);
    $definitionManager->method('getEntityType')->willReturnMap([
      ['message', $definition],
      ['node', $definition],
    ]);
    $hooks = new LifecycleHooks(
      $entityTypes,
      $definitionManager,
      $this->createMock(ModuleHandlerInterface::class),
      $queueFactory,
    );

    $this->expectExceptionMessage('persistent notices remain');
    $hooks->protectNotificationData('aculta_messenger', FALSE);
  }

  /**
   * Uninstall is blocked while read flags remain.
   */
  public function testUninstallIsBlockedWhileReadFlaggingsRemain(): void {
    $emptyQueue = $this->createMock(QueueInterface::class);
    $emptyQueue->method('numberOfItems')->willReturn(0);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->willReturn($emptyQueue);

    $storages = [];
    foreach (['message' => 0, 'node' => 0, 'flagging' => 1] as $entityType => $count) {
      $storage = $this->createMock(EntityStorageInterface::class);
      $storage->method('getQuery')->willReturn($this->queryReturning($count));
      $storages[$entityType] = $storage;
    }
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->willReturnCallback(static fn (string $entityType): EntityStorageInterface => $storages[$entityType]);

    $definition = $this->createMock(EntityTypeInterface::class);
    $definitionManager = $this->createMock(EntityDefinitionUpdateManagerInterface::class);
    $definitionManager->method('getEntityType')->willReturn($definition);
    $hooks = new LifecycleHooks(
      $entityTypes,
      $definitionManager,
      $this->createMock(ModuleHandlerInterface::class),
      $queueFactory,
    );

    $this->expectExceptionMessage('read states remain');
    $hooks->protectNotificationData('aculta_messenger', FALSE);
  }

  /**
   * Creates an entity query mock that returns the requested count.
   */
  private function queryReturning(int $count): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($count);
    return $query;
  }

  /**
   * Creates lifecycle hooks with optional module handler behavior.
   */
  private function hooks(?ModuleHandlerInterface $moduleHandler = NULL): LifecycleHooks {
    return new LifecycleHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityDefinitionUpdateManagerInterface::class),
      $moduleHandler ?? $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(QueueFactory::class),
    );
  }

}
