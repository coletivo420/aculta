<?php

namespace Drupal\aculta_donation;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for creating a new order with a donation item.
 *
 * Used in the checkout flow and the donateNow route method
 * in DonationController.
 */
class NewOrder {

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * Injected service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $user;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $currentRequest;

  /**
   * Injected service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * NewOrder constructor.
   *
   * @param \Drupal\commerce_store\CurrentStoreInterface $currentStore
   *   Injected service.
   * @param \Drupal\Core\Session\AccountProxyInterface $accountProxy
   *   Injected service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Injected service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Injected service.
   */
  public function __construct(CurrentStoreInterface $currentStore, AccountProxyInterface $accountProxy, RequestStack $requestStack, EntityTypeManagerInterface $entityTypeManager) {
    $this->currentStore = $currentStore;
    $this->user = $accountProxy;
    $this->currentRequest = $requestStack->getCurrentRequest();
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Creates a new Order object for the donation.
   *
   * @param string $giftType
   *   Optional gift type.
   * @param string $orderItemTypeId
   *   Optional order item type.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The new order object.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function get(string $giftType = 'single', string $orderItemTypeId = 'donation') {
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $itemType = $this->entityTypeManager->getStorage('commerce_order_item_type')->load($orderItemTypeId);
    $orderType = 'default';
    if ($itemType instanceof OrderItemType) {
      $type = $itemType->get('orderType');
      $orderType = $type ?? $orderType;
    }
    // Doação mensal vira assinatura: pedido próprio, com o workflow recorrente e o fluxo de autorização.
    if ($giftType === 'recurring') {
      $orderType = 'assinatura';
    }
    $order = Order::create(
      [
        'type' => $orderType,
        'state' => 'draft',
        'store_id' => $this->currentStore->getStore()->id(),
        'uid' => $this->user->id(),
        'cart' => TRUE,
      ]
    );

    // Starts at 0.
    $order = $this->addDonationItem($order, 0, $giftType, $orderItemTypeId);

    return $order;
  }

  /**
   * Adds a donation order item to the given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order object to which the item is being added.
   * @param int $price
   *   Optional initial price of the donation order item.
   * @param string $giftType
   *   Optional gift type.
   * @param string $orderItemTypeId
   *   Bundle ID for the order item.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   An order with donation item added.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function addDonationItem(OrderInterface $order, int $price = 0, string $giftType = 'single', string $orderItemTypeId = 'donation') {
    $price = new Price(
      $price,
      $this->currentStore->getStore()->getDefaultCurrencyCode()
    );
    // Change method of getting order item type.
    $itemType = $this->entityTypeManager->getStorage('commerce_order_item_type')->load($orderItemTypeId);
    if ($itemType instanceof OrderItemType) {
      $donation = OrderItem::create(
        [
          'type' => $orderItemTypeId,
          'title' => 'Donation',
        ]
      );
      /** @var \Drupal\commerce_order\Entity\OrderItem $donation */
      $donation->setUnitPrice($price);
      $donation->field_gift_type->setValue($giftType);
      $this->prepopulate($donation);
      $order->addItem($donation);
    }

    return $order;
  }

  /**
   * Prepopulate any matching OrderItem fields with query parameters.
   *
   * @param \Drupal\commerce_order\Entity\OrderItem $donation
   *   Our OrderItems are donations.
   */
  public function prepopulate(OrderItem $donation) {
    foreach ($this->currentRequest->query as $key => $value) {
      if ($donation->hasField('field_' . $key)) {
        $donation->set('field_' . $key, $value);
      }
    }
  }

}
