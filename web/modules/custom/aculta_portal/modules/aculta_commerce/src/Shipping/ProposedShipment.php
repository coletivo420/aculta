<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\profile\Entity\ProfileInterface;

/**
 * Represents a shipment proposed by a packer, before it is persisted.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
final class ProposedShipment {

  /**
   * Constructs a new ProposedShipment object.
   *
   * @param array $definition
   *   The definition. Required: type, order_id, items, shipping_profile.
   */
  public function __construct(protected array $definition) {
    foreach (['type', 'order_id', 'items', 'shipping_profile'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new \InvalidArgumentException(sprintf('Missing required property %s.', $required_property));
      }
    }
    if (!$definition['shipping_profile'] instanceof ProfileInterface) {
      throw new \InvalidArgumentException('Property "shipping_profile" should implement ProfileInterface.');
    }
    foreach ($definition['items'] as $item) {
      if (!$item instanceof ShipmentItem) {
        throw new \InvalidArgumentException(sprintf('Shipment items should be instances of %s.', ShipmentItem::class));
      }
    }
  }

  /**
   * Gets the shipment type.
   */
  public function getType(): string {
    return (string) $this->definition['type'];
  }

  /**
   * Gets the order ID.
   */
  public function getOrderId(): int|string {
    return $this->definition['order_id'];
  }

  /**
   * Gets the shipment title.
   */
  public function getTitle(): string {
    return (string) ($this->definition['title'] ?? 'Shipment');
  }

  /**
   * Gets the shipment items.
   *
   * @return \Drupal\aculta_commerce\Shipping\ShipmentItem[]
   *   The shipment items.
   */
  public function getItems(): array {
    return $this->definition['items'];
  }

  /**
   * Gets the shipping profile.
   */
  public function getShippingProfile(): ProfileInterface {
    return $this->definition['shipping_profile'];
  }

}
