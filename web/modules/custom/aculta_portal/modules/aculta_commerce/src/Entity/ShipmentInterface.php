<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Entity;

use Drupal\aculta_commerce\Shipping\PackageType;
use Drupal\aculta_commerce\Shipping\ProposedShipment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\physical\Weight;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;

/**
 * Defines the interface for ACULTA shipments.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
interface ShipmentInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the parent order.
   */
  public function getOrder(): ?OrderInterface;

  /**
   * Gets the parent order ID.
   */
  public function getOrderId(): ?int;

  /**
   * Gets the package type.
   */
  public function getPackageType(): ?PackageType;

  /**
   * Sets the package type.
   */
  public function setPackageType(?PackageType $package_type): static;

  /**
   * Gets the shipping method entity.
   */
  public function getShippingMethod(): ?ShippingMethodInterface;

  /**
   * Gets the shipping method ID.
   */
  public function getShippingMethodId(): ?int;

  /**
   * Sets the shipping method ID.
   */
  public function setShippingMethodId(?int $shipping_method_id): static;

  /**
   * Gets the shipping service ID.
   */
  public function getShippingService(): ?string;

  /**
   * Sets the shipping service ID.
   */
  public function setShippingService(?string $shipping_service): static;

  /**
   * Gets the shipping service label.
   */
  public function getShippingServiceLabel(): ?string;

  /**
   * Sets the shipping service label.
   */
  public function setShippingServiceLabel(?string $shipping_service_label): static;

  /**
   * Gets the shipping profile.
   */
  public function getShippingProfile(): ?ProfileInterface;

  /**
   * Sets the shipping profile.
   */
  public function setShippingProfile(ProfileInterface $profile): static;

  /**
   * Gets the shipment title.
   */
  public function getTitle(): ?string;

  /**
   * Sets the shipment title.
   */
  public function setTitle(string $title): static;

  /**
   * Gets the shipment items.
   *
   * @return \Drupal\aculta_commerce\Shipping\ShipmentItem[]
   *   The shipment items.
   */
  public function getItems(): array;

  /**
   * Gets whether the shipment has items.
   */
  public function hasItems(): bool;

  /**
   * Gets the total quantity.
   */
  public function getTotalQuantity(): string;

  /**
   * Gets the total declared value.
   */
  public function getTotalDeclaredValue(): ?Price;

  /**
   * Gets the shipment weight.
   */
  public function getWeight(): ?Weight;

  /**
   * Sets the shipment weight.
   */
  public function setWeight(Weight $weight): static;

  /**
   * Gets the original shipping amount.
   */
  public function getOriginalAmount(): ?Price;

  /**
   * Sets the original shipping amount.
   */
  public function setOriginalAmount(?Price $amount): static;

  /**
   * Gets the shipping amount.
   */
  public function getAmount(): ?Price;

  /**
   * Sets the shipping amount.
   */
  public function setAmount(?Price $amount): static;

  /**
   * Gets the tracking code.
   */
  public function getTrackingCode(): ?string;

  /**
   * Sets the tracking code.
   */
  public function setTrackingCode(?string $tracking_code): static;

  /**
   * Gets the shipment state.
   */
  public function getState(): StateItemInterface;

  /**
   * Gets the shipment creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the shipment creation timestamp.
   */
  public function setCreatedTime(int $timestamp): static;

  /**
   * Gets data attached to the shipment.
   */
  public function getData(string $key, mixed $default = NULL): mixed;

  /**
   * Sets data attached to the shipment.
   */
  public function setData(string $key, mixed $value): static;

  /**
   * Unsets data attached to the shipment.
   */
  public function unsetData(string $key): static;

  /**
   * Clears the selected rate, keeping the shipment and its profile.
   */
  public function clearRate(): static;

  /**
   * Populates the shipment from a proposed shipment.
   */
  public function populateFromProposedShipment(ProposedShipment $proposed_shipment): static;

}
