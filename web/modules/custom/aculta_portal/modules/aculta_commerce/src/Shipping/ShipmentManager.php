<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\aculta_commerce\Entity\ShipmentInterface;
use Drupal\aculta_commerce\ShippingMethodStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Calculates, sorts and applies shipping rates.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Provider
 * exceptions are logged and skipped; they never become a zero-cost rate.
 */
class ShipmentManager implements ShipmentManagerInterface {

  /**
   * Constructs a new ShipmentManager object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment): array {
    $all_rates = [];
    /** @var \Drupal\aculta_commerce\ShippingMethodStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('aculta_shipping_method');
    if (!$storage instanceof ShippingMethodStorageInterface) {
      throw new \LogicException('The aculta_shipping_method storage does not implement the ACULTA shipping method storage interface.');
    }
    foreach ($storage->loadMultipleForShipment($shipment) as $shipping_method) {
      $shipping_method_plugin = $shipping_method->getPlugin();
      try {
        $rates = $shipping_method_plugin->calculateRates($shipment);
      }
      catch (\Throwable $exception) {
        // Log only the method ID and exception class: provider messages may
        // contain address data or credentials.
        $this->logger->error('Shipping rate calculation failed for method @id (@class).', [
          '@id' => $shipping_method->id(),
          '@class' => get_class($exception),
        ]);
        continue;
      }
      foreach ($this->sortRates($rates) as $rate) {
        $all_rates[$rate->getId()] = $rate;
      }
    }

    return $all_rates;
  }

  /**
   * {@inheritdoc}
   */
  public function applyRate(ShipmentInterface $shipment, ShippingRate $rate): void {
    $shipping_method = $this->entityTypeManager
      ->getStorage('aculta_shipping_method')
      ->load($rate->getShippingMethodId());
    if (!$shipping_method) {
      throw new \InvalidArgumentException(sprintf('Unknown shipping method %s.', $rate->getShippingMethodId()));
    }
    if (empty($shipment->getPackageType())) {
      $shipment->setPackageType($shipping_method->getPlugin()->getDefaultPackageType());
    }
    $shipping_method->getPlugin()->selectRate($shipment, $rate);
  }

  /**
   * {@inheritdoc}
   */
  public function selectDefaultRate(ShipmentInterface $shipment, array $rates): ShippingRate {
    if (!$rates) {
      throw new \InvalidArgumentException('At least one rate is required.');
    }
    $default_rate = reset($rates);
    if ($shipment->getShippingMethodId() && $shipment->getShippingService()) {
      foreach ($rates as $rate) {
        if ((int) $shipment->getShippingMethodId() !== (int) $rate->getShippingMethodId()) {
          continue;
        }
        if ($shipment->getShippingService() !== $rate->getService()->getId()) {
          continue;
        }
        return $rate;
      }
    }
    return $default_rate;
  }

  /**
   * Sorts rates by amount, keeping the provider order for equal amounts.
   *
   * @param \Drupal\aculta_commerce\Shipping\ShippingRate[] $rates
   *   The rates.
   *
   * @return \Drupal\aculta_commerce\Shipping\ShippingRate[]
   *   The sorted rates.
   */
  protected function sortRates(array $rates): array {
    uasort($rates, static function (ShippingRate $a, ShippingRate $b): int {
      return $a->getAmount()->compareTo($b->getAmount());
    });
    return $rates;
  }

}
