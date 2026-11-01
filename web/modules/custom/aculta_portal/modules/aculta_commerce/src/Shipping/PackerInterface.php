<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Interface for shipment packers.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
interface PackerInterface {

  /**
   * Gets whether the packer applies to the given order and profile.
   */
  public function applies(OrderInterface $order, ProfileInterface $shipping_profile): bool;

  /**
   * Packs the order into proposed shipments.
   *
   * @return \Drupal\aculta_commerce\Shipping\ProposedShipment[]
   *   The proposed shipments.
   */
  public function pack(OrderInterface $order, ProfileInterface $shipping_profile): array;

}
