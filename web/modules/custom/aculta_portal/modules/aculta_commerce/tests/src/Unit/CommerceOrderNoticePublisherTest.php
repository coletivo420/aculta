<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\EventSubscriber\CommerceOrderNoticePublisher;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderTypeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests the Commerce producer only publishes eligible shop orders.
 */
#[CoversClass(CommerceOrderNoticePublisher::class)]
#[Group('aculta_commerce')]
final class CommerceOrderNoticePublisherTest extends UnitTestCase {

  /**
   * Publishes one registered shop order and suppresses the duplicate receipt.
   */
  public function testPublishesRegisteredStoreOrder(): void {
    $order = $this->createMock(OrderInterface::class);
    $order->method('bundle')->willReturn('default');
    $order->method('getCustomerId')->willReturn(27);
    $order->method('uuid')->willReturn('e56f3248-f7b3-4e55-9ea1-91b8e6a0df9f');
    $order->method('getPlacedTime')->willReturn(NULL);
    $order->method('getData')->with('skip_order_receipt', FALSE)->willReturn(FALSE);
    $order->expects(self::once())->method('setData')->with('skip_order_receipt', TRUE);

    $account = $this->createMock(UserInterface::class);
    $account->method('isActive')->willReturn(TRUE);
    $orderType = $this->createMock(OrderTypeInterface::class);
    $orderType->method('shouldSendReceipt')->willReturn(TRUE);
    $users = $this->createMock(EntityStorageInterface::class);
    $users->method('load')->with(27)->willReturn($account);
    $orderTypes = $this->createMock(EntityStorageInterface::class);
    $orderTypes->method('load')->with('default')->willReturn($orderType);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $users,
      'commerce_order_type' => $orderTypes,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())->method('publish')->with(self::callback(
      static fn(MessengerNoticeEvent $event): bool => $event->eventType() === 'store.order_placed'
        && $event->recipientUid() === 27
        && $event->metadata()['delivery_override'] === NULL,
    ));
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_792_800_000);
    $transition = $this->createMock(WorkflowTransitionEvent::class);
    $transition->method('getEntity')->willReturn($order);

    $settings = new CheckoutSettings($this->getConfigFactoryStub([
      CheckoutSettings::CONFIG => ['shop_order_type' => 'default'],
    ]));
    $subscriber = new CommerceOrderNoticePublisher(
      $settings,
      $entities,
      $publisher,
      $time,
      $this->createMock(LoggerInterface::class),
    );
    $subscriber->orderPlaced($transition);
  }

  /**
   * Guest orders keep Commerce's receipt because there is no site recipient.
   */
  public function testGuestOrderKeepsCommerceReceipt(): void {
    $order = $this->createMock(OrderInterface::class);
    $order->method('bundle')->willReturn('default');
    $order->method('getCustomerId')->willReturn(0);
    $order->expects(self::never())->method('setData');

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::never())->method('publish');
    $transition = $this->createMock(WorkflowTransitionEvent::class);
    $transition->method('getEntity')->willReturn($order);

    $settings = new CheckoutSettings($this->getConfigFactoryStub([
      CheckoutSettings::CONFIG => ['shop_order_type' => 'default'],
    ]));
    $subscriber = new CommerceOrderNoticePublisher(
      $settings,
      $entities,
      $publisher,
      $this->createMock(TimeInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    $subscriber->orderPlaced($transition);
  }

  /**
   * A failed shared publish leaves Commerce's receipt available as fallback.
   */
  public function testPublishFailureKeepsReceiptAndLogsExceptionClassOnly(): void {
    $order = $this->createMock(OrderInterface::class);
    $order->method('bundle')->willReturn('default');
    $order->method('getCustomerId')->willReturn(27);
    $order->method('uuid')->willReturn('e56f3248-f7b3-4e55-9ea1-91b8e6a0df9f');
    $order->method('getPlacedTime')->willReturn(NULL);
    $order->method('getData')->with('skip_order_receipt', FALSE)->willReturn(FALSE);
    $order->expects(self::never())->method('setData');

    $account = $this->createMock(UserInterface::class);
    $account->method('isActive')->willReturn(TRUE);
    $orderType = $this->createMock(OrderTypeInterface::class);
    $orderType->method('shouldSendReceipt')->willReturn(TRUE);
    $users = $this->createMock(EntityStorageInterface::class);
    $users->method('load')->with(27)->willReturn($account);
    $orderTypes = $this->createMock(EntityStorageInterface::class);
    $orderTypes->method('load')->with('default')->willReturn($orderType);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $users,
      'commerce_order_type' => $orderTypes,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())
      ->method('publish')
      ->willThrowException(new \RuntimeException('private customer data'));
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('error')
      ->with('Could not publish store order notice (@exception).', ['@exception' => \RuntimeException::class]);
    $transition = $this->createMock(WorkflowTransitionEvent::class);
    $transition->method('getEntity')->willReturn($order);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_792_800_000);

    $settings = new CheckoutSettings($this->getConfigFactoryStub([
      CheckoutSettings::CONFIG => ['shop_order_type' => 'default'],
    ]));
    $subscriber = new CommerceOrderNoticePublisher(
      $settings,
      $entities,
      $publisher,
      $time,
      $logger,
    );
    $subscriber->orderPlaced($transition);
  }

}
