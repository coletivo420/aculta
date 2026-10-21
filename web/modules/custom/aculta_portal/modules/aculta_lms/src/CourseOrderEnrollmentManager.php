<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms_classes\ClassHelper;
use Drupal\user\UserInterface;

/**
 * Grants and revokes Group class memberships from Commerce course orders.
 */
final class CourseOrderEnrollmentManager implements CourseOrderEnrollmentManagerInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Synchronizes one course order after Commerce saves it.
   */
  public function synchronize(OrderInterface $order): void {
    if ($order->bundle() !== 'curso') {
      return;
    }

    if ($this->hasActiveEntitlement($order)) {
      $valid_classes = $this->grantOrderItems($order);
      $this->reconcileOrderMemberships($order, $valid_classes);
      return;
    }

    $this->reconcileOrderMemberships($order, []);
  }

  /**
   * Determines whether a completed or partially refunded order grants access.
   */
  public function hasActiveEntitlement(OrderInterface $order): bool {
    if ($order->getState()->getId() !== 'completed') {
      return FALSE;
    }
    if ($order->isPaid()) {
      return TRUE;
    }

    $payments = $this->entityTypeManager->getStorage('commerce_payment')->loadMultipleByOrder($order);
    foreach ($payments as $payment) {
      if ($payment instanceof PaymentInterface
        && in_array($payment->getState()->getId(), ['completed', 'partially_refunded'], TRUE)
        && $payment->getBalance() !== NULL
        && !$payment->getBalance()->isZero()) {
        // A partial refund leaves the order's remaining paid balance active.
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasActivePurchaseForClass(GroupInterface $class, UserInterface $student): bool {
    return $this->findActiveOrderForClass($class, (int) $student->id()) !== NULL;
  }

  /**
   * Grants memberships and returns classes currently entitled by this order.
   *
   * @return array<string, true>
   *   Class IDs with a valid course product and a current class relationship.
   */
  private function grantOrderItems(OrderInterface $order): array {
    $uid = (int) $order->getCustomerId();
    if ($uid < 1) {
      return [];
    }
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface) {
      return [];
    }

    $class_ids = [];
    foreach ($order->getItems() as $item) {
      if ($item->bundle() !== 'curso') {
        continue;
      }
      $course = $this->courseForItem($item);
      $class = $item->get('field_course_class')->entity;
      if (!$course instanceof CourseInterface || !$class instanceof GroupInterface || !$this->classBelongsToCourse($class, $course)) {
        continue;
      }

      $class_ids[(string) $class->id()] = TRUE;
      $membership = $class->getMember($account);
      if ($membership !== FALSE) {
        // An existing manual enrollment remains manual and is never claimed by
        // the order, so a later refund cannot revoke it.
        continue;
      }
      $class->addMember($account, [
        'field_course_order' => ['target_id' => $order->id()],
      ]);
    }

    return $class_ids;
  }

  /**
   * Keeps only memberships backed by this order or another active purchase.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order whose existing memberships are reconciled.
   * @param array<string, true> $valid_class_ids
   *   Class IDs currently entitled by this order. Empty when it is inactive.
   */
  private function reconcileOrderMemberships(OrderInterface $order, array $valid_class_ids): void {
    $storage = $this->entityTypeManager->getStorage('group_relationship');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('plugin_id', 'group_membership')
      ->condition('field_course_order.target_id', $order->id())
      ->execute();
    foreach ($storage->loadMultiple($ids) as $membership) {
      if (!$membership instanceof GroupRelationshipInterface) {
        continue;
      }
      $class = $membership->getGroup();
      $account = $membership->getEntity();
      if (!$class instanceof GroupInterface || !$account instanceof UserInterface) {
        continue;
      }
      if ((string) $account->id() === (string) $order->getCustomerId()
        && isset($valid_class_ids[(string) $class->id()])) {
        continue;
      }
      $alternative = $this->findActiveOrderForClass($class, (int) $account->id(), (int) $order->id());
      if ($alternative !== NULL) {
        $membership->set('field_course_order', ['target_id' => $alternative->id()]);
        $membership->save();
        continue;
      }
      $class->removeMember($account);
    }
  }

  /**
   * Finds an active order for the class, optionally excluding one order.
   */
  private function findActiveOrderForClass(GroupInterface $class, int $uid, ?int $excludedOrderId = NULL): ?OrderInterface {
    $itemIds = $this->entityTypeManager->getStorage('commerce_order_item')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'curso')
      ->condition('field_course_class.target_id', $class->id())
      ->execute();
    foreach ($this->entityTypeManager->getStorage('commerce_order_item')->loadMultiple($itemIds) as $item) {
      if (!$item instanceof OrderItemInterface) {
        continue;
      }
      $candidate = $item->getOrder();
      if (!$candidate instanceof OrderInterface) {
        continue;
      }
      if (($excludedOrderId !== NULL && (int) $candidate->id() === $excludedOrderId)
        || (int) $candidate->getCustomerId() !== $uid) {
        continue;
      }
      $course = $this->courseForItem($item);
      if ($course instanceof CourseInterface
        && $this->classBelongsToCourse($class, $course)
        && $this->hasActiveEntitlement($candidate)) {
        return $candidate;
      }
    }
    return NULL;
  }

  /**
   * Resolves the course associated with a purchased course product.
   */
  private function courseForItem(OrderItemInterface $item): ?CourseInterface {
    $variation = $item->getPurchasedEntity();
    $product = $variation instanceof ProductVariationInterface ? $variation->getProduct() : NULL;
    if ($product === NULL || $product->bundle() !== 'produto_curso') {
      return NULL;
    }
    $ids = $this->entityTypeManager->getStorage('group')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'lms_course')
      ->condition('field_course_product.target_id', $product->id())
      ->execute();
    if (count($ids) !== 1) {
      return NULL;
    }
    $course = $this->entityTypeManager->getStorage('group')->load(reset($ids));
    return $course instanceof CourseInterface ? $course : NULL;
  }

  /**
   * Checks whether a class is linked to the specified course.
   */
  private function classBelongsToCourse(GroupInterface $class, CourseInterface $course): bool {
    if ($class->bundle() !== 'lms_class') {
      return FALSE;
    }
    foreach (ClassHelper::getClasses($course) as $courseClass) {
      if ((string) $courseClass->id() === (string) $class->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
