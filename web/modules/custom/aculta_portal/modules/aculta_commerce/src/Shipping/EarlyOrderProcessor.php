<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Prepares shipments during order refresh.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Runs before
 * promotion and tax processors. It packs changed orders and applies a default
 * rate only when the shipment has no selected amount, so an explicit customer
 * choice is never silently replaced.
 */
class EarlyOrderProcessor implements OrderProcessorInterface {

  /**
   * Constructs a new EarlyOrderProcessor object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ShippingOrderManagerInterface $shippingOrderManager,
    protected ShipmentManagerInterface $shipmentManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order): void {
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }
    $shipments = $order->get('shipments')->referencedEntities();
    if (!$shipments) {
      return;
    }
    $should_refresh = !$order->isNew() && $order->getState()->getId() === 'draft';
    $repacked = FALSE;
    if ($should_refresh && $this->shouldRepack($order, $shipments)) {
      $shipping_profile = $this->shippingOrderManager->getProfile($order);
      if (!$shipping_profile) {
        $this->entityTypeManager->getStorage('aculta_shipment')->delete($shipments);
        $order->set('shipments', []);
        return;
      }
      $shipments = $this->shippingOrderManager->pack($order, $shipping_profile);
      $repacked = TRUE;
    }

    foreach ($shipments as $shipment) {
      if (!$should_refresh) {
        continue;
      }
      if ($repacked) {
        $shipment->clearRate();
      }
      if ($shipment->getAmount()) {
        continue;
      }
      $rates = $this->shipmentManager->calculateRates($shipment);
      if (empty($rates)) {
        $shipment->clearRate();
        continue;
      }
      $rate = $this->shipmentManager->selectDefaultRate($shipment, $rates);
      $this->shipmentManager->applyRate($shipment, $rate);
    }

    $order->set('shipments', $shipments);
  }

  /**
   * Determines whether the order items changed since the last refresh.
   */
  protected function shouldRepack(OrderInterface $order, array $shipments): bool {
    if ($order->getData('aculta_commerce_force_shipping_refresh', FALSE)) {
      $order->unsetData('aculta_commerce_force_shipping_refresh');
      return TRUE;
    }
    foreach ($shipments as $shipment) {
      if (!$shipment->getData('owned_by_packer')) {
        return FALSE;
      }
    }
    $original = $order->getOriginal();
    if (!$original instanceof OrderInterface) {
      return FALSE;
    }
    $item_ids = array_values(array_filter(array_map(static fn($item) => $item->id(), $order->getItems())));
    $original_item_ids = array_values(array_filter(array_map(static fn($item) => $item->id(), $original->getItems())));
    return count($item_ids) !== count($original_item_ids) || array_diff($item_ids, $original_item_ids);
  }

}
