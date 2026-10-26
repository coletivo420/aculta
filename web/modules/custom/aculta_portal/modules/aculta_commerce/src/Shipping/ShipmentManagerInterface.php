<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\aculta_commerce\Entity\ShipmentInterface;

/**
 * Interface for the ACULTA shipment manager.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
interface ShipmentManagerInterface {

  /**
   * Calculates the available rates for the given shipment.
   *
   * @return \Drupal\aculta_commerce\Shipping\ShippingRate[]
   *   The rates keyed by rate ID.
   */
  public function calculateRates(ShipmentInterface $shipment): array;

  /**
   * Applies the given rate to the shipment.
   *
   * @param \Drupal\aculta_commerce\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param \Drupal\aculta_commerce\Shipping\ShippingRate $rate
   *   The rate to apply.
   */
  public function applyRate(ShipmentInterface $shipment, ShippingRate $rate): void;

  /**
   * Selects the default rate for the shipment.
   *
   * @param \Drupal\aculta_commerce\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param \Drupal\aculta_commerce\Shipping\ShippingRate[] $rates
   *   The rates, never empty.
   */
  public function selectDefaultRate(ShipmentInterface $shipment, array $rates): ShippingRate;

}
