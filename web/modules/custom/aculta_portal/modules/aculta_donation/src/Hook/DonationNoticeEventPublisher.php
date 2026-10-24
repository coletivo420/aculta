<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Hook;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Publishes Donation facts to the shared Messenger kernel. */
final class DonationNoticeEventPublisher implements EventSubscriberInterface {

  private const ORDER_TYPES = ['doacao', 'assinatura'];

  private const ITEM_TYPE = 'donation';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerNoticePublisherInterface $publisher,
    private readonly TimeInterface $time,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return ['commerce_order.place.post_transition' => ['orderPlaced', 0]];
  }

  /**
   * Publishes the order-created notice and suppresses the duplicate receipt. */
  public function orderPlaced(WorkflowTransitionEvent $transition): void {
    $order = $transition->getEntity();
    if (!$order instanceof OrderInterface || !$this->isDonationOrder($order)
      || !$order->uuid() || (int) $order->getCustomerId() < 1) {
      return;
    }
    $account = $this->entityTypeManager->getStorage('user')->load((int) $order->getCustomerId());
    if (!$account?->isActive()) {
      return;
    }

    try {
      $this->publisher->publish(new MessengerNoticeEvent(
        'donation.order_placed',
        'aculta_donation',
        'commerce_order',
        $order->uuid(),
        'placed',
        (int) $order->getCustomerId(),
        'account',
        new \DateTimeImmutable('@' . ($order->getPlacedTime() ?: $this->time->getRequestTime())),
        'donation.order_placed:' . $order->uuid() . ':' . $order->getCustomerId(),
        [
          'notice_type' => 'order_placed',
          'mail_policy' => 'user_preference',
          'order_uuid' => $order->uuid(),
        ],
      ));
      // Commerce's order receipt fires at placement, before payment approval.
      // Registered donors receive the distinct Messenger event instead.
      $order->setData('skip_order_receipt', TRUE);
    }
    catch (\Throwable $exception) {
      // Retain Commerce's receipt as a fallback if the shared event fails.
      $this->logger->error('Could not publish Donation order notice (@exception).', [
        '@exception' => $exception::class,
      ]);
    }
  }

  /**
 * Publishes a verified completed Donation payment on entity creation. */
  #[Hook('commerce_payment_insert')]
  public function paymentInsert(EntityInterface $entity): void {
    if ($entity instanceof PaymentInterface) {
      $this->publishApprovedPayment($entity);
    }
  }

  /**
 * Publishes only the transition into completed, never an ordinary update. */
  #[Hook('commerce_payment_update')]
  public function paymentUpdate(EntityInterface $entity): void {
    if (!$entity instanceof PaymentInterface) {
      return;
    }
    $original = $entity->getOriginal();
    if ($original instanceof PaymentInterface && $original->getState()->getId() !== 'completed') {
      $this->publishApprovedPayment($entity);
    }
  }

  /**
   * Publishes one site/email event per verified approved payment. */
  private function publishApprovedPayment(PaymentInterface $payment): void {
    if ($payment->getState()->getId() !== 'completed'
      || $payment->getRemoteState() !== 'approved'
      || ($payment instanceof SynchronizableInterface && $payment->isSyncing())) {
      return;
    }
    $order = $payment->getOrder();
    if (!$order instanceof OrderInterface || !$this->isDonationOrder($order)
      || !$order->uuid() || (int) $order->getCustomerId() < 1 || !$payment->uuid()) {
      return;
    }
    $account = $this->entityTypeManager->getStorage('user')->load((int) $order->getCustomerId());
    if (!$account?->isActive()) {
      return;
    }

    try {
      $this->publisher->publish(new MessengerNoticeEvent(
        'donation.payment_approved',
        'aculta_donation',
        'commerce_payment',
        $payment->uuid(),
        'approved',
        (int) $order->getCustomerId(),
        'account',
        new \DateTimeImmutable('@' . $this->time->getRequestTime()),
        'donation.payment_approved:' . $payment->uuid() . ':' . $order->getCustomerId(),
        [
          'notice_type' => 'payment_approved',
          'mail_policy' => 'user_preference',
          'order_uuid' => $order->uuid(),
        ],
      ));
    }
    catch (\Throwable $exception) {
      // A notice failure must not roll back a verified financial transition.
      $this->logger->error('Could not publish Donation payment notice (@exception).', [
        '@exception' => $exception::class,
      ]);
    }
  }

  /**
   * Ensures only ACULTA Donation orders are announced. */
  private function isDonationOrder(OrderInterface $order): bool {
    if (!in_array($order->bundle(), self::ORDER_TYPES, TRUE)) {
      return FALSE;
    }
    foreach ($order->getItems() as $item) {
      if ($item->bundle() === self::ITEM_TYPE) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
