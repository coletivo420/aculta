<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce;

use Drupal\aculta_commerce\Entity\ShipmentInterface;

/**
 * Defines the ACULTA shipping method storage interface.
 */
interface ShippingMethodStorageInterface {

  /**
   * Loads the shipping methods available for the given shipment.
   *
   * @param \Drupal\aculta_commerce\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \Drupal\aculta_commerce\Entity\ShippingMethodInterface[]
   *   The eligible shipping methods, sorted by weight and label.
   */
  public function loadMultipleForShipment(ShipmentInterface $shipment): array;

}
