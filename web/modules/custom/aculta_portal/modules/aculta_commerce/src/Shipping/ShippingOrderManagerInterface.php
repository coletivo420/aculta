<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Interface for the ACULTA shipping order manager.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
interface ShippingOrderManagerInterface {

  /**
   * Creates a shipping profile for the given order.
   */
  public function createProfile(OrderInterface $order, array $values = []): ProfileInterface;

  /**
   * Gets the shipping profile of the given order.
   */
  public function getProfile(OrderInterface $order): ?ProfileInterface;

  /**
   * Gets whether the order has shipments.
   */
  public function hasShipments(OrderInterface $order): bool;

  /**
   * Gets whether the order is shippable.
   */
  public function isShippable(OrderInterface $order): bool;

  /**
   * Packs the order and returns its shipments.
   *
   * @return \Drupal\aculta_commerce\Entity\ShipmentInterface[]
   *   The shipments.
   */
  public function pack(OrderInterface $order, ?ProfileInterface $profile = NULL): array;

}
