<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod;

use Drupal\aculta_commerce\Entity\ShipmentInterface;
use Drupal\aculta_commerce\Shipping\PackageType;
use Drupal\aculta_commerce\Shipping\ShippingRate;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the interface for ACULTA shipping method plugins.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
interface ShippingMethodInterface extends PluginInspectionInterface {

  /**
   * Calculates rates for the given shipment.
   *
   * @param \Drupal\aculta_commerce\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \Drupal\aculta_commerce\Shipping\ShippingRate[]
   *   The rates. Never a fabricated zero-cost rate on provider failure.
   */
  public function calculateRates(ShipmentInterface $shipment): array;

  /**
   * Applies the given rate to the shipment.
   */
  public function selectRate(ShipmentInterface $shipment, ShippingRate $rate): void;

  /**
   * Gets whether the plugin applies to the given shipment.
   */
  public function applies(ShipmentInterface $shipment): bool;

  /**
   * Gets the plugin label.
   */
  public function getLabel(): string;

  /**
   * Gets the enabled shipping services.
   *
   * @return \Drupal\aculta_commerce\Shipping\ShippingService[]
   *   The services, keyed by ID.
   */
  public function getServices(): array;

  /**
   * Gets the default package type.
   */
  public function getDefaultPackageType(): PackageType;

  /**
   * Gets the shipment workflow ID.
   */
  public function getWorkflowId(): string;

  /**
   * Sets the parent shipping method entity.
   */
  public function setParentEntity(EntityInterface $parent_entity): static;

  /**
   * Gets the plugin configuration.
   */
  public function getConfiguration(): array;

  /**
   * Sets the plugin configuration.
   */
  public function setConfiguration(array $configuration): void;

  /**
   * Builds the configuration form.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array;

  /**
   * Validates the configuration form.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Submits the configuration form.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void;

}
