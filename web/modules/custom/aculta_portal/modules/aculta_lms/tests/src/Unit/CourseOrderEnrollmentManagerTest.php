<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\CourseOrderEnrollmentManager;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\PaymentStorageInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
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
   * A manually created class membership is protected by a later active order.
   */
  public function testActivePurchaseForClassFindsTheStudentOrder(): void {
    $class = $this->createMock(GroupInterface::class);
    $class->method('bundle')->willReturn('lms_class');
    $class->method('id')->willReturn('77');
    $course = $this->createMock(CourseInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('44');
    $classRelationship = $this->createMock(GroupRelationshipInterface::class);
    $classRelationship->method('getEntity')->willReturn($class);
    $course->method('getRelationships')->with('lms_classes')->willReturn([$classRelationship]);

    $student = $this->createMock(UserInterface::class);
    $student->method('id')->willReturn('12');
    $order = $this->createOrder('completed', TRUE);
    $order->method('id')->willReturn('19');
    $order->method('getCustomerId')->willReturn('12');
    $orderItem = $this->createMock(OrderItemInterface::class);
    $orderItem->method('bundle')->willReturn('curso');
    $orderItem->method('getOrder')->willReturn($order);
    $variation = $this->createMock(ProductVariationInterface::class);
    $product = $this->createMock(ProductInterface::class);
    $product->method('bundle')->willReturn('produto_curso');
    $product->method('id')->willReturn('8');
    $variation->method('getProduct')->willReturn($product);
    $orderItem->method('getPurchasedEntity')->willReturn($variation);

    $orderItemQuery = $this->createMock(QueryInterface::class);
    $orderItemQuery->method('accessCheck')->willReturnSelf();
    $orderItemQuery->method('condition')->willReturnSelf();
    $orderItemQuery->method('execute')->willReturn([5]);
    $orderItemStorage = $this->createMock(EntityStorageInterface::class);
    $orderItemStorage->method('getQuery')->willReturn($orderItemQuery);
    $orderItemStorage->method('loadMultiple')->with([5])->willReturn([$orderItem]);

    $courseQuery = $this->createMock(QueryInterface::class);
    $courseQuery->method('accessCheck')->willReturnSelf();
    $courseQuery->method('condition')->willReturnSelf();
    $courseQuery->method('execute')->willReturn([44]);
    $courseStorage = $this->createMock(EntityStorageInterface::class);
    $courseStorage->method('getQuery')->willReturn($courseQuery);
    $courseStorage->method('load')->with(44)->willReturn($course);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['commerce_order_item', $orderItemStorage],
      ['group', $courseStorage],
    ]);
    $manager = new CourseOrderEnrollmentManager($entityTypeManager);

    self::assertTrue($manager->hasActivePurchaseForClass($class, $student));
  }

  /**
   * A refunded order transfers its membership to another active purchase.
   */
  public function testRefundedOrderKeepsMembershipForAnotherActivePurchase(): void {
    $student = $this->createMock(UserInterface::class);
    $student->method('id')->willReturn('77');
    $class = $this->createMock(GroupInterface::class);
    $class->method('bundle')->willReturn('lms_class');
    $class->method('id')->willReturn('7');
    $class->expects(self::never())->method('removeMember');

    $oldOrder = $this->createOrder('completed', FALSE);
    $oldOrder->method('bundle')->willReturn('curso');
    $oldOrder->method('id')->willReturn('11');
    $refundedPayment = $this->createPayment('refunded', new Price('0.00', 'BRL'));
    $paymentStorage = $this->createMock(PaymentStorageInterface::class);
    $paymentStorage->expects(self::once())
      ->method('loadMultipleByOrder')
      ->with($oldOrder)
      ->willReturn([$refundedPayment]);

    $membership = $this->createMock(GroupRelationshipInterface::class);
    $membership->method('getPluginId')->willReturn('group_membership');
    $membership->method('getGroup')->willReturn($class);
    $membership->method('getEntity')->willReturn($student);
    $membership->expects(self::once())->method('set')
      ->with('field_course_order', ['target_id' => '12'])
      ->willReturnSelf();
    $membership->expects(self::once())->method('save');

    $relationshipQuery = $this->createMock(QueryInterface::class);
    $relationshipQuery->method('accessCheck')->willReturnSelf();
    $relationshipQuery->method('condition')->willReturnSelf();
    $relationshipQuery->method('execute')->willReturn([21]);
    $relationshipStorage = $this->createMock(EntityStorageInterface::class);
    $relationshipStorage->method('getQuery')->willReturn($relationshipQuery);
    $relationshipStorage->method('loadMultiple')->with([21])->willReturn([$membership]);

    $activeOrder = $this->createOrder('completed', TRUE);
    $activeOrder->method('bundle')->willReturn('curso');
    $activeOrder->method('id')->willReturn('12');
    $activeOrder->method('getCustomerId')->willReturn('77');
    $item = $this->createMock(OrderItemInterface::class);
    $item->method('bundle')->willReturn('curso');
    $item->method('getOrder')->willReturn($activeOrder);
    $product = $this->createMock(ProductInterface::class);
    $product->method('bundle')->willReturn('produto_curso');
    $product->method('id')->willReturn('33');
    $variation = $this->createMock(ProductVariationInterface::class);
    $variation->method('getProduct')->willReturn($product);
    $item->method('getPurchasedEntity')->willReturn($variation);
    $itemQuery = $this->createMock(QueryInterface::class);
    $itemQuery->method('accessCheck')->willReturnSelf();
    $itemQuery->method('condition')->willReturnSelf();
    $itemQuery->method('execute')->willReturn([31]);
    $itemStorage = $this->createMock(EntityStorageInterface::class);
    $itemStorage->method('getQuery')->willReturn($itemQuery);
    $itemStorage->method('loadMultiple')->with([31])->willReturn([$item]);

    $course = $this->createMock(CourseInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('55');
    $classRelationship = $this->createMock(GroupRelationshipInterface::class);
    $classRelationship->method('getEntity')->willReturn($class);
    $course->method('getRelationships')->with('lms_classes')->willReturn([$classRelationship]);
    $courseQuery = $this->createMock(QueryInterface::class);
    $courseQuery->method('accessCheck')->willReturnSelf();
    $courseQuery->method('condition')->willReturnSelf();
    $courseQuery->method('execute')->willReturn([55]);
    $courseStorage = $this->createMock(EntityStorageInterface::class);
    $courseStorage->method('getQuery')->willReturn($courseQuery);
    $courseStorage->method('load')->with(55)->willReturn($course);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['commerce_payment', $paymentStorage],
      ['group_relationship', $relationshipStorage],
      ['commerce_order_item', $itemStorage],
      ['group', $courseStorage],
    ]);

    (new CourseOrderEnrollmentManager($entityTypeManager))->synchronize($oldOrder);
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
