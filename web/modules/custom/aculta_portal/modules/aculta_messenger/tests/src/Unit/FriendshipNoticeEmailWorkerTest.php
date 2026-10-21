<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Friendship\FriendshipNoticeSubscriber;
use Drupal\aculta_messenger\Plugin\QueueWorker\FriendshipNoticeEmailWorker;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\message\MessageInterface;
use Drupal\message_notify\MessageNotifyInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies safe failure paths for friendship notice e-mail delivery.
 */
#[Group('aculta_messenger')]
final class FriendshipNoticeEmailWorkerTest extends UnitTestCase {

  /**
   * Malformed queue data never loads entities or invokes the transport.
   */
  public function testMalformedQueueItemIsIgnored(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');
    $notifier = $this->createMock(MessageNotifyInterface::class);
    $notifier->expects(self::never())->method('send');
    $worker = $this->worker($entityTypeManager, $notifier);

    $worker->processItem(['message_id' => 'not-an-integer']);
  }

  /**
   * Missing recipient or account purpose fails without sending e-mail.
   */
  public function testUnavailableRecipientFailsThePersistedMessage(): void {
    $status = $this->createMock(FieldItemListInterface::class);
    $status->method('__get')->with('value')->willReturn('pending');
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn(FriendshipNoticeSubscriber::TEMPLATE);
    $message->method('getOwnerId')->willReturn(52);
    $message->method('get')->with('field_aculta_msg_delivery')->willReturn($status);
    $message->expects(self::once())
      ->method('set')
      ->with('field_aculta_msg_delivery', 'failed')
      ->willReturnSelf();
    $message->expects(self::once())->method('save');

    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('load')->with(8)->willReturn($message);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(52)->willReturn(NULL);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('conta_aculta_org')->willReturn(NULL);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['message', $messageStorage],
      ['user', $userStorage],
      ['domain', $domainStorage],
    ]);
    $notifier = $this->createMock(MessageNotifyInterface::class);
    $notifier->expects(self::never())->method('send');
    $worker = $this->worker($entityTypeManager, $notifier);

    $worker->processItem(['message_id' => 8]);
  }

  /**
   * Builds the queue worker with the configured domain resolver. */
  private function worker(EntityTypeManagerInterface $entityTypeManager, MessageNotifyInterface $notifier): FriendshipNoticeEmailWorker {
    $domainPurpose = new DomainPurposeManager(
      $entityTypeManager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    return new FriendshipNoticeEmailWorker(
      [],
      FriendshipNoticeSubscriber::EMAIL_QUEUE,
      [],
      $entityTypeManager,
      $notifier,
      $domainPurpose,
    );
  }

}
