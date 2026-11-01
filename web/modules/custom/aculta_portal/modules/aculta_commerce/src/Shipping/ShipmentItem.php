<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\commerce_price\Price;
use Drupal\physical\Weight;

/**
 * Immutable value object describing an item included in a shipment.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). This is the
 * packing-time value object; the persisted representation is the
 * aculta_shipment_item field type.
 */
final class ShipmentItem {

  /**
   * Constructs a new ShipmentItem object.
   *
   * @param int|string|null $order_item_id
   *   The source order item ID.
   * @param string $title
   *   The item title.
   * @param string $quantity
   *   The quantity, as a decimal string.
   * @param \Drupal\physical\Weight $weight
   *   The total weight for this quantity.
   * @param \Drupal\commerce_price\Price $declared_value
   *   The declared value for this quantity.
   */
  public function __construct(
    protected int|string|null $order_item_id,
    protected string $title,
    protected string $quantity,
    protected Weight $weight,
    protected Price $declared_value,
  ) {}

  /**
   * Gets the source order item ID.
   */
  public function getOrderItemId(): int|string|null {
    return $this->order_item_id;
  }

  /**
   * Gets the item title.
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * Gets the quantity.
   */
  public function getQuantity(): string {
    return $this->quantity;
  }

  /**
   * Gets the total weight.
   */
  public function getWeight(): Weight {
    return $this->weight;
  }

  /**
   * Gets the declared value.
   */
  public function getDeclaredValue(): Price {
    return $this->declared_value;
  }

}
