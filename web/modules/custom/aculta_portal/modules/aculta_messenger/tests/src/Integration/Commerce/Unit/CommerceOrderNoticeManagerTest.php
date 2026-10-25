<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Commerce\Unit;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_messenger\Integration\Commerce\CommerceOrderNoticeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\message\MessageInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\domain\DomainInterface;
use Drupal\user\UserInterface;
use Drupal\domain\DomainNegotiatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests persistent Commerce notice creation and email preference routing. */
#[CoversClass(CommerceOrderNoticeManager::class)]
#[Group('aculta_messenger')]
final class CommerceOrderNoticeManagerTest extends UnitTestCase {

  /**
   * Persists the site notice and queues direct email as a separate channel. */
  public function testDirectModePersistsAndQueuesEmail(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('id')->willReturn('42');
    $message->expects(self::once())->method('save')->willReturn(1);
    [$entities, $queueFactory] = $this->dependencies($message);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'loja')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->with(['message_id' => 42])->willReturn(5);
    $queueFactory->method('get')->with('aculta_commerce_messenger_email')->willReturn($queue);
    $manager = $this->manager($entities, $preferences, $queueFactory);

    $result = $manager->record($this->event());

    self::assertSame($message, $result);
  }

  /**
   * Persists mandatory site notices without queuing email when opted out. */
  public function testOffModeKeepsSiteNoticeAndSkipsEmail(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('id')->willReturn('42');
    $message->expects(self::once())->method('save')->willReturn(1);
    [$entities, $queueFactory] = $this->dependencies($message);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'loja')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $queueFactory->expects(self::never())->method('get');

    self::assertSame($message, $this->manager($entities, $preferences, $queueFactory)->record($this->event()));
  }

  /**
   * Honors Commerce receipt opt-out and keeps the site notice.
   */
  public function testReceiptOptOutDoesNotQueueEmail(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->expects(self::once())->method('save')->willReturn(1);
    [$entities, $queueFactory] = $this->dependencies($message);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->expects(self::never())->method('emailMode');
    $queueFactory->expects(self::never())->method('get');
    $event = $this->event();
    $event = new MessengerNoticeEvent(
      $event->eventType(),
      $event->sourceModule(),
      $event->sourceEntityType(),
      $event->sourceEntityUuid(),
      $event->sourceRevisionOrVersion(),
      $event->recipientUid(),
      $event->purpose(),
      $event->occurredAt(),
      $event->idempotencyKey(),
      [
        'notice_type' => 'order_placed',
        'mail_policy' => 'user_preference',
        'delivery_override' => 'off',
      ],
    );

    self::assertSame($message, $this->manager($entities, $preferences, $queueFactory)->record($event));
  }

  /**
   * Drops queued digest items when the user later disables Loja email.
   */
  public function testDigestRevalidatesCurrentCategoryPreference(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($account);
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($query);
    $messageStorage->method('loadMultiple')->with([])->willReturn([]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $userStorage,
      'message' => $messageStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->expects(self::once())->method('emailMode')->with(27, 'loja')->willReturn(AvisoPreferencias::EMAIL_OFF);

    self::assertSame([], $this->manager($entities, $preferences, $this->createMock(QueueFactory::class))->digestRowsFor(27));
  }

  /**
   * Unread count batch-loads source orders and revalidates each unique order.
   */
  public function testUnreadCountBatchLoadsOrdersAndRevalidatesAccess(): void {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn(TRUE);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($account);

    $messageA = $this->noticeMessage(27, 'order-a');
    $messageB = $this->noticeMessage(27, 'order-b');
    $messageAReplay = $this->noticeMessage(27, 'order-a');
    $messageWrongEvent = $this->noticeMessage(27, 'order-c', 'other');
    $messageQuery = $this->createMock(QueryInterface::class);
    $messageQuery->method('accessCheck')->willReturnSelf();
    $messageQuery->method('condition')->willReturnSelf();
    $messageQuery->method('execute')->willReturn([101, 102, 103, 104]);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($messageQuery);
    $messageStorage->method('loadMultiple')->with([101, 102, 103, 104])->willReturn([
      101 => $messageA,
      102 => $messageB,
      103 => $messageAReplay,
      104 => $messageWrongEvent,
    ]);

    $allowedOrder = $this->order('order-a', 201, 27, TRUE);
    $inaccessibleOrder = $this->order('order-b', 202, 27, FALSE);
    $orderStorage = $this->createMock(EntityStorageInterface::class);
    $orderStorage->expects(self::once())
      ->method('loadByProperties')
      ->with(['uuid' => ['order-a', 'order-b']])
      ->willReturn([$allowedOrder, $inaccessibleOrder]);

    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('loja_aculta_org')->willReturn($domain);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $userStorage,
      'message' => $messageStorage,
      'commerce_order' => $orderStorage,
      'domain' => $domainStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $manager = $this->manager($entities, $this->createMock(AvisoPreferenciasInterface::class), $this->createMock(QueueFactory::class));

    try {
      self::assertSame(2, $manager->unreadCount(27));
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * Creates the manager using only mocked I/O dependencies. */
  private function manager(
    EntityTypeManagerInterface $entities,
    AvisoPreferenciasInterface $preferences,
    QueueFactory $queueFactory,
  ): CommerceOrderNoticeManager {
    $domainPurposes = new DomainPurposeManager(
      $entities,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    return new CommerceOrderNoticeManager(
      $entities,
      $domainPurposes,
      new CheckoutSettings($this->getConfigFactoryStub([CheckoutSettings::CONFIG => ['shop_order_type' => 'default']])),
      $preferences,
      $queueFactory,
      $this->lock(),
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Creates user, Message storage, query, and queue factory mocks. */
  private function dependencies(MessageInterface $message): array {
    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($user);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($query);
    $messageStorage->expects(self::once())->method('create')->with(self::callback(
      static fn(array $values): bool => $values['template'] === CommerceOrderNoticeManager::TEMPLATE
        && $values['uid'] === 27
        && $values['field_aculta_commerce_read'] === 0,
    ))->willReturn($message);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $userStorage,
      'message' => $messageStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    return [$entities, $this->createMock(QueueFactory::class)];
  }

  /**
   * Builds a valid Commerce notice event. */
  private function event(): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      'store.order_placed',
      'aculta_commerce',
      'commerce_order',
      'e56f3248-f7b3-4e55-9ea1-91b8e6a0df9f',
      'placed',
      27,
      'shop',
      new \DateTimeImmutable('2026-10-24T00:00:00Z'),
      'store:test:placed:27',
      ['notice_type' => 'order_placed', 'mail_policy' => 'user_preference'],
    );
  }

  /**
   * Creates a persisted-looking Commerce notice for count resolution.
   */
  private function noticeMessage(int $uid, string $orderUuid, string $eventType = 'order_placed'): MessageInterface {
    $fields = [];
    foreach ([
      'field_aculta_commerce_event_type' => $eventType,
      'field_aculta_commerce_uuid' => $orderUuid,
    ] as $fieldName => $value) {
      $field = $this->createMock(FieldItemListInterface::class);
      $field->method('__get')->with('value')->willReturn($value);
      $fields[$fieldName] = $field;
    }
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn(CommerceOrderNoticeManager::TEMPLATE);
    $message->method('getOwnerId')->willReturn($uid);
    $message->method('get')->willReturnCallback(static fn(string $field): FieldItemListInterface => $fields[$field]);
    return $message;
  }

  /**
   * Creates a Commerce order fixture with explicit owner and access.
   */
  private function order(string $uuid, int $id, int $customerId, bool $viewable): OrderInterface {
    $access = $this->createMock(AccessResultInterface::class);
    $access->method('isAllowed')->willReturn($viewable);
    $order = $this->createMock(OrderInterface::class);
    $order->method('uuid')->willReturn($uuid);
    $order->method('id')->willReturn((string) $id);
    $order->method('bundle')->willReturn('default');
    $order->method('getCustomerId')->willReturn($customerId);
    $order->method('access')->with('view', self::anything(), TRUE)->willReturn($access);
    return $order;
  }

  /**
   * Creates a lock backend that grants the idempotency lock. */
  private function lock(): LockBackendInterface {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return $lock;
  }

}
