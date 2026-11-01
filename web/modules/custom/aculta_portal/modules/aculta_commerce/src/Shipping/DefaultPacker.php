<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\physical\Calculator;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Creates a single shipment per order.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Only
 * purchasable entities with a weight field are packed; the engine never
 * treats a non-shippable item as physical.
 */
class DefaultPacker implements PackerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new DefaultPacker object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(OrderInterface $order, ProfileInterface $shipping_profile): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function pack(OrderInterface $order, ProfileInterface $shipping_profile): array {
    $items = [];
    foreach ($order->getItems() as $order_item) {
      if (!$order_item->getUnitPrice() || $order_item->isNew()) {
        continue;
      }
      $purchased_entity = $order_item->getPurchasedEntity();
      if (!$purchased_entity || !$purchased_entity->hasField('weight')) {
        continue;
      }
      if ($purchased_entity->get('weight')->isEmpty()) {
        $purchased_entity->set('weight', new Weight('0', WeightUnit::GRAM));
      }
      $quantity = $order_item->getQuantity();
      if (Calculator::compare($quantity, '0') == 0) {
        continue;
      }
      /** @var \Drupal\physical\Weight $weight */
      $weight = $purchased_entity->get('weight')->first()->toMeasurement();
      $items[] = new ShipmentItem(
        $order_item->id(),
        (string) $order_item->getTitle(),
        (string) $quantity,
        $weight->multiply($quantity),
        $order_item->getUnitPrice()->multiply($quantity),
      );
    }

    if (empty($items)) {
      return [];
    }
    return [
      new ProposedShipment([
        'type' => $this->getShipmentType($order),
        'order_id' => $order->id(),
        'title' => $this->t('Shipment #1')->render(),
        'items' => $items,
        'shipping_profile' => $shipping_profile,
      ]),
    ];
  }

  /**
   * Gets the shipment type configured on the order type.
   */
  protected function getShipmentType(OrderInterface $order): string {
    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $order_type_storage->load($order->bundle());
    return (string) ($order_type?->getThirdPartySetting('aculta_commerce', 'shipment_type') ?: 'default');
  }

}
