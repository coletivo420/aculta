<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_shipping_test\Plugin\Commerce\ShippingMethod;

use Drupal\aculta_commerce\Attribute\AcultaShippingMethod;
use Drupal\aculta_commerce\Entity\ShipmentInterface;
use Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\aculta_commerce\Shipping\ShippingRate;
use Drupal\aculta_commerce\Shipping\ShippingService;
use Drupal\commerce_price\Price;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test-only carrier provider for the ACULTA shipping engine.
 *
 * It behaves like an external carrier: it returns a rate with a fixed amount,
 * or throws when configured to fail. The engine must skip the failing provider
 * and never turn the failure into a zero-cost rate.
 */
#[AcultaShippingMethod(
  id: 'aculta_test_mock_carrier',
  label: new TranslatableMarkup('Mock carrier'),
  services: ['mock_express' => 'Mock express'],
  workflow: 'aculta_shipment_default',
)]
final class MockCarrier extends ShippingMethodBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'rate_amount' => [
        'number' => '7.77',
        'currency_code' => 'BRL',
      ],
      'fail' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment): array {
    if (!empty($this->configuration['fail'])) {
      throw new \RuntimeException('Simulated carrier failure.');
    }
    $parent = $this->getParentEntity();
    if (!$parent) {
      throw new \LogicException('The mock carrier has no parent entity.');
    }
    $amount = $this->configuration['rate_amount'] ?? NULL;
    if (!is_array($amount) || !isset($amount['number'], $amount['currency_code'])) {
      throw new \InvalidArgumentException('The mock carrier has no configured amount.');
    }

    return [
      new ShippingRate([
        'shipping_method_id' => (string) $parent->id(),
        'service' => new ShippingService('mock_express', 'Mock express'),
        'amount' => Price::fromArray($amount),
      ]),
    ];
  }

}
