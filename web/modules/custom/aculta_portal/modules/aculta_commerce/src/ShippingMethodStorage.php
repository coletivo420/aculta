<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce;

use Drupal\aculta_commerce\Entity\ShipmentInterface;
use Drupal\aculta_commerce\Entity\ShippingMethod;
use Drupal\commerce\CommerceContentEntityStorage;

/**
 * Defines the ACULTA shipping method storage.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Access is
 * intentionally not checked here: shipping methods are internal rate
 * providers, and the checkout runs as an anonymous or authenticated customer
 * without an administrative permission.
 */
class ShippingMethodStorage extends CommerceContentEntityStorage implements ShippingMethodStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function loadMultipleForShipment(ShipmentInterface $shipment): array {
    $order = $shipment->getOrder();
    if (!$order) {
      return [];
    }
    $query = $this->getQuery();
    $query->accessCheck(FALSE);
    $store_condition = $query->orConditionGroup()
      ->notExists('stores')
      ->condition('stores', [$order->getStoreId()], 'IN');
    $query
      ->condition($store_condition)
      ->condition('status', TRUE);
    $result = $query->execute();
    if (empty($result)) {
      return [];
    }

    $shipping_methods = $this->loadMultiple($result);
    foreach ($shipping_methods as $shipping_method_id => $shipping_method) {
      if (!$shipping_method->applies($shipment)) {
        unset($shipping_methods[$shipping_method_id]);
      }
    }
    uasort($shipping_methods, [ShippingMethod::class, 'sort']);

    return $shipping_methods;
  }

}
