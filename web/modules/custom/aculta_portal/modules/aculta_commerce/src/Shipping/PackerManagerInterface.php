<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Interface for the ACULTA packer manager.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
interface PackerManagerInterface {

  /**
   * Adds a packer.
   */
  public function addPacker(PackerInterface $packer): void;

  /**
   * Gets the registered packers.
   *
   * @return \Drupal\aculta_commerce\Shipping\PackerInterface[]
   *   The packers.
   */
  public function getPackers(): array;

  /**
   * Packs the order into proposed shipments.
   *
   * @return \Drupal\aculta_commerce\Shipping\ProposedShipment[]
   *   The proposed shipments.
   */
  public function pack(OrderInterface $order, ProfileInterface $shipping_profile): array;

  /**
   * Packs the order and populates existing or new shipments.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\profile\Entity\ProfileInterface $shipping_profile
   *   The shipping profile.
   * @param \Drupal\aculta_commerce\Entity\ShipmentInterface[] $shipments
   *   The existing shipments.
   *
   * @return array
   *   A two-element array: populated shipments, removed shipments.
   */
  public function packToShipments(OrderInterface $order, ProfileInterface $shipping_profile, array $shipments): array;

}
