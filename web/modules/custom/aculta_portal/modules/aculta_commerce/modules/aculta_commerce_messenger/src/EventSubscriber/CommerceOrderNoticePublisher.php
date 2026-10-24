<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_messenger\EventSubscriber;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderTypeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Publishes registered-customer shop order placement to Messenger. */
final class CommerceOrderNoticePublisher implements EventSubscriberInterface {

  public function __construct(
    private readonly CheckoutSettings $checkoutSettings,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerNoticePublisherInterface $publisher,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    // Run before Commerce's order receipt subscriber (priority -100).
    return ['commerce_order.place.post_transition' => ['orderPlaced', 0]];
  }

  /**
   * Publishes a personal notice and suppresses Commerce's duplicate receipt. */
  public function orderPlaced(WorkflowTransitionEvent $transition): void {
    $order = $transition->getEntity();
    if (!$order instanceof OrderInterface
      || $order->bundle() !== $this->checkoutSettings->shopOrderType()
      || (int) $order->getCustomerId() < 1
      || !$order->uuid()) {
      // Guest receipts stay in Commerce because no account can receive a site
      // notice or control the email preference.
      return;
    }
    $account = $this->entityTypeManager->getStorage('user')->load((int) $order->getCustomerId());
    if (!$account?->isActive()) {
      return;
    }
    $orderType = $this->entityTypeManager->getStorage('commerce_order_type')->load($order->bundle());
    $emailOverride = $order->getData('skip_order_receipt', FALSE)
      || ($orderType instanceof OrderTypeInterface && !$orderType->shouldSendReceipt());
    try {
      $this->publisher->publish(new MessengerNoticeEvent(
        'store.order_placed',
        'aculta_commerce',
        'commerce_order',
        $order->uuid(),
        'placed',
        (int) $order->getCustomerId(),
        'shop',
        new \DateTimeImmutable(
          '@' . ($order->getPlacedTime() ?: $this->time->getRequestTime()),
        ),
        'store.order_placed:' . $order->uuid() . ':' . $order->getCustomerId(),
        [
          'notice_type' => 'order_placed',
          'mail_policy' => 'user_preference',
          'delivery_override' => $emailOverride ? 'off' : NULL,
        ],
      ));
      // The shared event now owns the notice and the selected email channel.
      $order->setData('skip_order_receipt', TRUE);
    }
    catch (\Throwable $exception) {
      // Keep Commerce's receipt as a fallback if the shared consumer failed.
      $this->logger->error(
        'Could not publish store order notice (@exception).',
        ['@exception' => $exception::class],
      );
    }
  }

}
