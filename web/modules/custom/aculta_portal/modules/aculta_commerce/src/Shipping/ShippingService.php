<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

/**
 * Represents a shipping service offered by a shipping method.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). The ACULTA
 * engine owns this value object and does not depend on the contributed module.
 */
final class ShippingService {

  /**
   * Constructs a new ShippingService object.
   *
   * @param string $id
   *   The service ID, unique within the shipping method.
   * @param string $label
   *   The service label.
   */
  public function __construct(
    protected string $id,
    protected string $label,
  ) {}

  /**
   * Gets the service ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets the service label.
   */
  public function getLabel(): string {
    return $this->label;
  }

}
