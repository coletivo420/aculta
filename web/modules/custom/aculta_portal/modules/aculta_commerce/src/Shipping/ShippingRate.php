<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\commerce_price\Price;

/**
 * Represents a shipping rate returned by a trusted shipping method plugin.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Amounts are
 * always commerce_price Price objects; rates are never built from browser
 * input.
 */
final class ShippingRate {

  /**
   * The rate ID.
   */
  protected string $id;

  /**
   * The shipping method ID.
   */
  protected string $shippingMethodId;

  /**
   * The shipping service.
   */
  protected ShippingService $service;

  /**
   * The original amount.
   */
  protected Price $originalAmount;

  /**
   * The amount.
   */
  protected Price $amount;

  /**
   * The description.
   */
  protected string $description;

  /**
   * Additional data.
   */
  protected array $data;

  /**
   * Constructs a new ShippingRate object.
   *
   * @param array $definition
   *   The definition. Required: shipping_method_id, service, amount.
   */
  public function __construct(array $definition) {
    foreach (['shipping_method_id', 'service', 'amount'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new \InvalidArgumentException(sprintf('Missing required property %s.', $required_property));
      }
    }
    if (!$definition['service'] instanceof ShippingService) {
      throw new \InvalidArgumentException(sprintf('Property "service" should be an instance of %s.', ShippingService::class));
    }
    if (!$definition['amount'] instanceof Price) {
      throw new \InvalidArgumentException(sprintf('Property "amount" should be an instance of %s.', Price::class));
    }
    if (empty($definition['id'])) {
      $definition['id'] = $definition['shipping_method_id'] . '--' . $definition['service']->getId();
    }

    $this->id = (string) $definition['id'];
    $this->shippingMethodId = (string) $definition['shipping_method_id'];
    $this->service = $definition['service'];
    $this->originalAmount = $definition['original_amount'] ?? $definition['amount'];
    $this->amount = $definition['amount'];
    $this->description = (string) ($definition['description'] ?? '');
    $this->data = $definition['data'] ?? [];
  }

  /**
   * Gets the rate ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets the shipping method ID.
   */
  public function getShippingMethodId(): string {
    return $this->shippingMethodId;
  }

  /**
   * Gets the shipping service.
   */
  public function getService(): ShippingService {
    return $this->service;
  }

  /**
   * Gets the original amount.
   */
  public function getOriginalAmount(): Price {
    return $this->originalAmount;
  }

  /**
   * Gets the amount.
   */
  public function getAmount(): Price {
    return $this->amount;
  }

  /**
   * Gets the description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Gets arbitrary data attached to the rate.
   */
  public function getData(string $key, mixed $default = NULL): mixed {
    return $this->data[$key] ?? $default;
  }

}
