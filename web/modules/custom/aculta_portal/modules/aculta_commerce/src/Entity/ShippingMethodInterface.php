<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Entity;

use Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod\ShippingMethodInterface as ShippingMethodPluginInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

/**
 * Defines the interface for ACULTA shipping methods.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
interface ShippingMethodInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface {

  /**
   * Gets the shipping method stores.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface[]
   *   The stores.
   */
  public function getStores(): array;

  /**
   * Sets the shipping method stores.
   */
  public function setStores(array $stores): static;

  /**
   * Gets the shipping method store IDs.
   *
   * @return string[]
   *   The store IDs.
   */
  public function getStoreIds(): array;

  /**
   * Sets the shipping method store IDs.
   */
  public function setStoreIds(array $store_ids): static;

  /**
   * Gets the shipping method plugin.
   */
  public function getPlugin(): ShippingMethodPluginInterface;

  /**
   * Gets the shipping method name.
   */
  public function getName(): ?string;

  /**
   * Sets the shipping method name.
   */
  public function setName(string $name): static;

  /**
   * Gets the shipping method conditions.
   *
   * @return \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface[]
   *   The conditions.
   */
  public function getConditions(): array;

  /**
   * Sets the shipping method conditions.
   */
  public function setConditions(array $conditions): static;

  /**
   * Gets the condition operator.
   */
  public function getConditionOperator(): string;

  /**
   * Sets the condition operator.
   */
  public function setConditionOperator(string $condition_operator): static;

  /**
   * Gets the shipping method weight.
   */
  public function getWeight(): int;

  /**
   * Sets the shipping method weight.
   */
  public function setWeight(int $weight): static;

  /**
   * Gets whether the shipping method is enabled.
   */
  public function isEnabled(): bool;

  /**
   * Sets whether the shipping method is enabled.
   */
  public function setEnabled(bool $enabled): static;

  /**
   * Checks whether the shipping method applies to the given shipment.
   */
  public function applies(ShipmentInterface $shipment): bool;

}
