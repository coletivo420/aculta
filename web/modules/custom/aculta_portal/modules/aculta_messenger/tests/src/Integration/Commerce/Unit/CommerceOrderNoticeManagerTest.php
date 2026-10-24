<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Commerce\Unit;

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
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\domain\DomainNegotiatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
    $preferences->method('resumoEmailLigado')->with(27)->willReturn(TRUE);
    $preferences->expects(self::once())->method('emailMode')->with(27, 'loja')->willReturn(AvisoPreferencias::EMAIL_OFF);

    self::assertSame([], $this->manager($entities, $preferences, $this->createMock(QueueFactory::class))->digestRowsFor(27));
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
   * Creates a lock backend that grants the idempotency lock. */
  private function lock(): LockBackendInterface {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return $lock;
  }

}
