<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Plugin\Field\FieldType;

use Drupal\aculta_commerce\Shipping\ShipmentItem as ShipmentItemValue;
use Drupal\commerce_price\Price;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;

/**
 * Field type storing an ACULTA shipment item.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). The value is
 * stored as JSON in a single text column, which keeps the table portable and
 * avoids a custom typed-data plugin.
 *
 * @property string|null $value
 */
#[FieldType(
  id: 'aculta_shipment_item',
  label: new TranslatableMarkup('Shipment item'),
  description: new TranslatableMarkup('Stores shipment items'),
  category: 'aculta_commerce',
  no_ui: TRUE,
)]
class ShipmentItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Value'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return $this->value === NULL || $this->value === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'value' => [
          'description' => 'The serialized shipment item.',
          'type' => 'text',
          'size' => 'big',
          'not null' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Sets the item from a shippable value object.
   */
  public function setShipmentItem(ShipmentItemValue $item): static {
    $weight = $item->getWeight();
    $declared_value = $item->getDeclaredValue();
    $this->value = json_encode([
      'order_item_id' => $item->getOrderItemId(),
      'title' => $item->getTitle(),
      'quantity' => $item->getQuantity(),
      'weight' => $weight->getNumber(),
      'weight_unit' => $weight->getUnit(),
      'declared_value' => $declared_value->getNumber(),
      'declared_currency' => $declared_value->getCurrencyCode(),
    ], JSON_THROW_ON_ERROR);
    return $this;
  }

  /**
   * Gets the shipment item value object.
   */
  public function getShipmentItem(): ?ShipmentItemValue {
    if ($this->isEmpty()) {
      return NULL;
    }
    $data = json_decode((string) $this->value, TRUE);
    if (!is_array($data)) {
      return NULL;
    }
    try {
      return new ShipmentItemValue(
        $data['order_item_id'] ?? NULL,
        (string) ($data['title'] ?? ''),
        (string) ($data['quantity'] ?? '0'),
        new Weight((string) ($data['weight'] ?? '0'), (string) ($data['weight_unit'] ?? WeightUnit::GRAM)),
        new Price((string) ($data['declared_value'] ?? '0'), (string) ($data['declared_currency'] ?? 'BRL')),
      );
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Gets the source order item ID.
   */
  public function getOrderItemId(): int|string|null {
    return $this->getShipmentItem()?->getOrderItemId();
  }

  /**
   * Gets the item quantity.
   */
  public function getQuantity(): string {
    return $this->getShipmentItem()?->getQuantity() ?? '0';
  }

  /**
   * Gets the item weight.
   */
  public function getWeight(): ?Weight {
    return $this->getShipmentItem()?->getWeight();
  }

  /**
   * Gets the item declared value.
   */
  public function getDeclaredValue(): ?Price {
    return $this->getShipmentItem()?->getDeclaredValue();
  }

}
