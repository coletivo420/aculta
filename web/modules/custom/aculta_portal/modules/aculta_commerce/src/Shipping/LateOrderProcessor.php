<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Completes the order refresh process for shipments.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Transfers
 * the selected shipment amount to the order as a shipping adjustment.
 */
class LateOrderProcessor implements OrderProcessorInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new LateOrderProcessor object.
   */
  public function __construct(protected ShippingOrderManagerInterface $shippingOrderManager) {}

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order): void {
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }
    $shipments = $order->get('shipments')->referencedEntities();
    $single_shipment = count($shipments) === 1;

    foreach ($shipments as $shipment) {
      if ($shipment->hasTranslationChanges()) {
        $shipment->save();
      }
      if ($amount = $shipment->getAmount()) {
        $order->addAdjustment(new Adjustment([
          'type' => 'shipping',
          'label' => $single_shipment ? $this->t('Shipping') : $shipment->getTitle(),
          'amount' => $amount,
          'source_id' => $shipment->id(),
        ]));
      }
    }
    $order->set('shipments', $shipments);
  }

}
