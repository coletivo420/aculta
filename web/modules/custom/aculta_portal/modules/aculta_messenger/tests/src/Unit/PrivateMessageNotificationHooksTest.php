<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Hook\PrivateMessageNotificationHooks;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that new private-message notices opt into centralized Mailer delivery. */
#[CoversClass(PrivateMessageNotificationHooks::class)]
#[Group('aculta_messenger')]
final class PrivateMessageNotificationHooksTest extends UnitTestCase {

  /**
   * New queue payloads are explicitly distinguished from legacy retries.
   */
  public function testNewMessagesAreMarkedForMailerDelivery(): void {
    $privateMessage = $this->createMock(PrivateMessageInterface::class);
    $privateMessage->method('id')->willReturn('42');
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $captured = NULL;
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())
      ->method('createItem')
      ->willReturnCallback(static function (array $data) use (&$captured): int {
        $captured = $data;
        return 1;
      });
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::once())
      ->method('get')
      ->with('aculta_msg_private_notification')
      ->willReturn($queue);
    $logger = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger->expects(self::never())->method('get');

    (new PrivateMessageNotificationHooks($queueFactory, $logger))->newMessage($privateMessage, $thread);

    self::assertSame([
      'private_message_id' => 42,
      'mailer_managed' => TRUE,
    ], $captured);
  }

}
