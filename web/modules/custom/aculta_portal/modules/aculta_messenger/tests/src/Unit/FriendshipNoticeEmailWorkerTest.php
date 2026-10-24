<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeSubscriber;
use Drupal\aculta_messenger\Plugin\QueueWorker\FriendshipNoticeEmailWorker;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\DomainInterface;
use Drupal\message\MessageInterface;
use Drupal\message_notify\MessageNotifyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
   * A concurrent duplicate retries later without loading stale state.
   */
  public function testConcurrentDuplicateDoesNotLoadOrSend(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');
    $notifier = $this->createMock(MessageNotifyInterface::class);
    $notifier->expects(self::never())->method('send');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())
      ->method('acquire')
      ->with('aculta_messenger:friendship_notice_email:8', 60.0)
      ->willReturn(FALSE);
    $lock->expects(self::never())->method('release');
    $worker = $this->worker($entityTypeManager, $notifier, $lock);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Friendship notice email is already being processed.');
    $worker->processItem(['message_id' => 8]);
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
   * Successful delivery is recorded and a duplicate queue item is ignored.
   */
  public function testSuccessfulDeliveryIsRecordedAndNotSentTwice(): void {
    $state = ['status' => 'pending', 'attempts' => 0];
    $previous_container = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;
    $notifier = $this->createMock(MessageNotifyInterface::class);
    $notifier->expects(self::once())
      ->method('send')
      ->with(self::isInstanceOf(MessageInterface::class), ['save on success' => FALSE, 'save on fail' => FALSE])
      ->willReturn(TRUE);
    [$worker, $message] = $this->deliveryWorker($state, $notifier);
    $message->expects(self::once())
      ->method('setArguments')
      ->with(['@account_url' => 'https://account.example.invalid/minha-conta'])
      ->willReturnSelf();

    try {
      $worker->processItem(['message_id' => 8]);
      $worker->processItem(['message_id' => 8]);
    }
    finally {
      $this->restoreContainer($previous_container);
    }

    self::assertSame('sent', $state['status']);
    self::assertSame(1, $state['attempts']);
  }

  /**
   * Transport failures are retried at most three times.
   */
  public function testTransportFailureRetriesThreeTimesAndThenStops(): void {
    $state = ['status' => 'pending', 'attempts' => 0];
    $previous_container = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;
    $notifier = $this->createMock(MessageNotifyInterface::class);
    $notifier->expects(self::exactly(3))->method('send')->willReturn(FALSE);
    [$worker] = $this->deliveryWorker($state, $notifier);

    try {
      try {
        $worker->processItem(['message_id' => 8]);
        self::fail('The first failed delivery must be retried by the queue.');
      }
      catch (\RuntimeException $exception) {
        self::assertSame('Friendship notice email delivery failed.', $exception->getMessage());
      }
      try {
        $worker->processItem(['message_id' => 8]);
        self::fail('The second failed delivery must be retried by the queue.');
      }
      catch (\RuntimeException $exception) {
        self::assertSame('Friendship notice email delivery failed.', $exception->getMessage());
      }
      $worker->processItem(['message_id' => 8]);
      $worker->processItem(['message_id' => 8]);
    }
    finally {
      $this->restoreContainer($previous_container);
    }

    self::assertSame('failed', $state['status']);
    self::assertSame(3, $state['attempts']);
  }

  /**
   * Builds the queue worker with the configured domain resolver. */
  private function worker(EntityTypeManagerInterface $entityTypeManager, MessageNotifyInterface $notifier, ?LockBackendInterface $lock = NULL): FriendshipNoticeEmailWorker {
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
      $lock ?? $this->createConfiguredMock(LockBackendInterface::class, ['acquire' => TRUE]),
    );
  }

  /**
   * Creates a worker fixture with a current account, Domain, and Message.
   *
   * @param array{status: string, attempts: int} $state
   *   Mutable persisted field values used to simulate repeated queue loads.
   * @param \Drupal\message_notify\MessageNotifyInterface|null $notifier
   *   Optional mocked delivery service.
   *
   * @return array{0: FriendshipNoticeEmailWorker, 1: MessageInterface}
   *   The worker and persisted message mock.
   */
  private function deliveryWorker(array &$state, ?MessageNotifyInterface $notifier = NULL): array {
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn(FriendshipNoticeSubscriber::TEMPLATE);
    $message->method('getOwnerId')->willReturn(52);
    $status_items = $this->createMock(FieldItemListInterface::class);
    $status_items->method('__get')->with('value')->willReturnCallback(static function () use (&$state): string {
      return $state['status'];
    });
    $attempt_items = $this->createMock(FieldItemListInterface::class);
    $attempt_items->method('__get')->with('value')->willReturnCallback(static function () use (&$state): int {
      return $state['attempts'];
    });
    $message->method('get')->willReturnMap([
      ['field_aculta_msg_delivery', $status_items],
      ['field_aculta_messenger_attempts', $attempt_items],
    ]);
    $message->method('set')->willReturnCallback(static function (string $field, mixed $value) use (&$state, $message): MessageInterface {
      if ($field === 'field_aculta_msg_delivery') {
        $state['status'] = $value;
      }
      elseif ($field === 'field_aculta_messenger_attempts') {
        $state['attempts'] = $value;
      }
      return $message;
    });
    $message->method('setArguments')->willReturnSelf();
    $message->method('save')->willReturn(1);

    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('recipient@example.invalid');
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('load')->with(8)->willReturn($message);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(52)->willReturn($recipient);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('conta_aculta_org')->willReturn($domain);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['message', $messageStorage],
      ['user', $userStorage],
      ['domain', $domainStorage],
    ]);

    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generateFromRoute')->willReturnCallback(static function (string $route): string {
      self::assertSame('aculta_portal.dashboard', $route);
      return 'https://account.example.invalid/minha-conta';
    });
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    \Drupal::setContainer($container);

    return [
      $this->worker($entityTypeManager, $notifier ?? $this->createMock(MessageNotifyInterface::class)),
      $message,
    ];
  }

  /**
   * Restores the Unit test's global Drupal container after URL generation.
   */
  private function restoreContainer(?ContainerInterface $container): void {
    if ($container === NULL) {
      \Drupal::unsetContainer();
      return;
    }
    \Drupal::setContainer($container);
  }

}
