<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\CourseOrderEnrollmentManager;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\PaymentStorageInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group as TestGroup;

/**
 * Tests order and payment state rules for course enrollment entitlements.
 */
#[TestGroup('aculta_lms')]
final class CourseOrderEnrollmentManagerTest extends UnitTestCase {

  /**
   * A partial refund leaves the paid balance and enrollment active.
   */
  public function testPartiallyRefundedPaymentKeepsEnrollmentActive(): void {
    $payment = $this->createPayment('partially_refunded', new Price('5.00', 'BRL'));

    self::assertTrue($this->manager([$payment])->hasActiveEntitlement($this->createOrder('completed', FALSE)));
  }

  /**
   * A fully refunded payment no longer grants enrollment.
   */
  public function testFullyRefundedPaymentDoesNotKeepEnrollmentActive(): void {
    $payment = $this->createPayment('refunded', new Price('0.00', 'BRL'));

    self::assertFalse($this->manager([$payment])->hasActiveEntitlement($this->createOrder('completed', FALSE)));
  }

  /**
   * A completed payment with a remaining balance grants enrollment.
   */
  public function testCompletedPaymentGrantsEnrollment(): void {
    $payment = $this->createPayment('completed', new Price('10.00', 'BRL'));

    self::assertTrue($this->manager([$payment])->hasActiveEntitlement($this->createOrder('completed', FALSE)));
  }

  /**
   * A completed free order is paid without loading payment records.
   */
  public function testCompletedFreeOrderGrantsEnrollment(): void {
    self::assertTrue($this->manager([], FALSE)->hasActiveEntitlement($this->createOrder('completed', TRUE)));
  }

  /**
   * Incomplete orders never grant enrollment, even if payment data exists.
   */
  public function testIncompleteOrderDoesNotGrantEnrollment(): void {
    $payment = $this->createPayment('completed', new Price('10.00', 'BRL'));
    $manager = $this->manager([$payment], FALSE);

    self::assertFalse($manager->hasActiveEntitlement($this->createOrder('draft', FALSE)));
  }

  /**
   * Creates the manager with a mocked Commerce payment storage.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface[] $payments
   *   Payments returned for the order.
   * @param bool $expect_lookup
   *   Whether the code is expected to inspect payment records.
   *
   * @return \Drupal\aculta_lms\CourseOrderEnrollmentManager
   *   The manager under test.
   */
  private function manager(array $payments, bool $expect_lookup = TRUE): CourseOrderEnrollmentManager {
    $paymentStorage = $this->createMock(PaymentStorageInterface::class);
    $paymentStorage->expects($expect_lookup ? self::once() : self::never())
      ->method('loadMultipleByOrder')
      ->with(self::anything())
      ->willReturn($payments);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($expect_lookup ? self::once() : self::never())
      ->method('getStorage')
      ->with('commerce_payment')
      ->willReturn($paymentStorage);

    return new CourseOrderEnrollmentManager($entityTypeManager);
  }

  /**
   * Creates an order double with the requested state and paid status.
   */
  private function createOrder(string $stateId, bool $isPaid): OrderInterface {
    $state = new class($stateId) {

      public function __construct(private string $id) {}

      /** Returns the state machine ID. */
      public function getId(): string {
        return $this->id;
      }

    };
    $order = $this->createMock(OrderInterface::class);
    $order->method('getState')->willReturn($state);
    $order->method('isPaid')->willReturn($isPaid);
    return $order;
  }

  /**
   * Creates a Commerce payment double.
   */
  private function createPayment(string $stateId, ?Price $balance): PaymentInterface {
    $state = new class($stateId) {

      public function __construct(private string $id) {}

      /** Returns the state machine ID. */
      public function getId(): string {
        return $this->id;
      }

    };
    $payment = $this->createMock(PaymentInterface::class);
    $payment->method('getState')->willReturn($state);
    $payment->method('getBalance')->willReturn($balance);
    return $payment;
  }

}
