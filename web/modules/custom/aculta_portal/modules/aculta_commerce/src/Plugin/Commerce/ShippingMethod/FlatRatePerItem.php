<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod;

use Drupal\aculta_commerce\Attribute\AcultaShippingMethod;
use Drupal\aculta_commerce\Entity\ShipmentInterface;
use Drupal\aculta_commerce\Shipping\ShippingRate;
use Drupal\aculta_commerce\Shipping\ShippingService;
use Drupal\commerce_price\Price;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a flat rate per item shipping method.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
#[AcultaShippingMethod(
  id: 'aculta_flat_rate_per_item',
  label: new TranslatableMarkup('Tarifa fixa por item'),
  services: ['default' => 'Tarifa por item'],
  workflow: 'aculta_shipment_default',
)]
class FlatRatePerItem extends FlatRate {

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment): array {
    $parent = $this->getParentEntity();
    if (!$parent) {
      throw new \LogicException('The flat rate shipping method has no parent entity.');
    }
    $amount = $this->configuration['rate_amount'] ?? NULL;
    if (!is_array($amount) || !isset($amount['number'], $amount['currency_code'])) {
      throw new \InvalidArgumentException('The flat rate shipping method has no configured amount.');
    }
    $quantity = $shipment->getTotalQuantity();
    if ($quantity === '' || (float) $quantity <= 0) {
      throw new \InvalidArgumentException('The shipment has no quantity.');
    }

    return [
      new ShippingRate([
        'shipping_method_id' => (string) $parent->id(),
        'service' => new ShippingService('default', (string) ($this->configuration['rate_label'] ?: $this->getLabel())),
        'amount' => Price::fromArray($amount)->multiply($quantity),
        'description' => (string) $this->configuration['rate_description'],
      ]),
    ];
  }

}
