<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_donation\Unit;

use Drupal\aculta_donation\Hook\DonationNoticeEventPublisher;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\user\UserInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Covers the two distinct Donation facts published to Messenger.
 */
#[Group('aculta_donation')]
final class DonationNoticeEventPublisherTest extends TestCase {

  /**
   * A placed order is explicitly described as created, not paid. */
  public function testPlacedOrderPublishesPendingPaymentWordingContract(): void {
    $order = $this->donationOrder();
    $order->expects(self::once())->method('setData')->with('skip_order_receipt', TRUE);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())->method('publish')->with(self::callback(
      static function (MessengerNoticeEvent $event): bool {
        self::assertSame('donation.order_placed', $event->eventType());
        self::assertSame('commerce_order', $event->sourceEntityType());
        self::assertSame('donation.order_placed:order-uuid:42', $event->idempotencyKey());
        self::assertSame('order_placed', $event->metadata()['notice_type']);
        return TRUE;
      },
    ));
    $service = $this->publisher($publisher);
    $service->orderPlaced(new WorkflowTransitionEvent(
      $this->createMock(WorkflowTransition::class),
      $this->createMock(WorkflowInterface::class),
      $order,
      'state',
    ));
  }

  /**
   * An approved payment publishes one event per Payment, including recurring charges. */
  public function testApprovedPaymentPublishesPerChargeFact(): void {
    $order = $this->donationOrder();
    $state = $this->createMock(StateItemInterface::class);
    $state->method('getId')->willReturn('completed');
    $payment = $this->createMock(PaymentInterface::class);
    $payment->method('getState')->willReturn($state);
    $payment->method('getRemoteState')->willReturn('approved');
    $payment->method('getOrder')->willReturn($order);
    $payment->method('uuid')->willReturn('payment-uuid');
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())->method('publish')->with(self::callback(
      static function (MessengerNoticeEvent $event): bool {
        self::assertSame('donation.payment_approved', $event->eventType());
        self::assertSame('commerce_payment', $event->sourceEntityType());
        self::assertSame('payment-uuid', $event->sourceEntityUuid());
        self::assertSame('donation.payment_approved:payment-uuid:42', $event->idempotencyKey());
        self::assertSame('payment_approved', $event->metadata()['notice_type']);
        return TRUE;
      },
    ));

    $this->publisher($publisher)->paymentInsert($payment);
  }

  /**
   * Pending or unverified payments must not be represented as approved. */
  public function testPendingPaymentDoesNotPublishApprovalNotice(): void {
    $state = $this->createMock(StateItemInterface::class);
    $state->method('getId')->willReturn('authorization');
    $payment = $this->createMock(PaymentInterface::class);
    $payment->method('getState')->willReturn($state);
    $payment->method('getRemoteState')->willReturn('pending');
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::never())->method('publish');

    $this->publisher($publisher)->paymentInsert($payment);
  }

  /**
   * Creates a Donation order owned by an active registered user. */
  private function donationOrder(): OrderInterface {
    $item = $this->createMock(OrderItemInterface::class);
    $item->method('bundle')->willReturn('donation');
    $order = $this->createMock(OrderInterface::class);
    $order->method('bundle')->willReturn('doacao');
    $order->method('uuid')->willReturn('order-uuid');
    $order->method('getCustomerId')->willReturn(42);
    $order->method('getItems')->willReturn([$item]);
    $order->method('getPlacedTime')->willReturn(1);
    return $order;
  }

  /**
   * Creates the publisher bridge with an active account fixture. */
  private function publisher(MessengerNoticePublisherInterface $publisher): DonationNoticeEventPublisher {
    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(42)->willReturn($user);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('user')->willReturn($storage);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1761307200);
    return new DonationNoticeEventPublisher(
      $entityTypeManager,
      $publisher,
      $time,
      $this->createMock(LoggerChannelInterface::class),
    );
  }

}
