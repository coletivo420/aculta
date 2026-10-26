<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

/**
 * Immutable carrier quote projection; no amount comes from the browser.
 *
 * The canonical amount is a decimal string, never a float. Checkout adapters
 * should convert it to commerce_price Price only after trusted validation.
 * This is preparatory domain code, not a shipping-method plugin.
 */
final readonly class ShippingQuote {

  /**
   * @param string $methodId
   *   Stable internal shipping method ID.
   * @param string $serviceId
   *   Carrier service ID scoped to method ID.
   * @param string $label
   *   Display label. Render with Drupal escaping.
   * @param string $amount
   *   Non-negative decimal string, up to six decimal places.
   * @param string $currency
   *   Three-letter ISO currency code.
   * @param int|null $validUntil
   *   Unix timestamp, or NULL when the quote has no explicit expiry.
   */
  public function __construct(
    public string $methodId,
    public string $serviceId,
    public string $label,
    public string $amount,
    public string $currency,
    public ?int $validUntil = NULL,
  ) {
    foreach ([$methodId, $serviceId] as $id) {
      if (preg_match('/\A[A-Za-z0-9_.:-]{1,128}\z/D', $id) !== 1) {
        throw new \InvalidArgumentException('Invalid shipping method or service ID.');
      }
    }
    if ($label === '' || trim($label) === '' || preg_match('/[\x00-\x1F\x7F]/', $label)) {
      throw new \InvalidArgumentException('Invalid shipping label.');
    }
    if (preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,6})?\z/D', $amount) !== 1) {
      throw new \InvalidArgumentException('Shipping amount must be a non-negative decimal string.');
    }
    if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
      throw new \InvalidArgumentException('Invalid shipping currency.');
    }
    if ($validUntil !== NULL && $validUntil < 0) {
      throw new \InvalidArgumentException('Invalid shipping quote expiry.');
    }
  }

  /**
   * Stable identity; the selected price must still be revalidated server-side.
   */
  public function id(): string {
    return $this->methodId . ':' . $this->serviceId;
  }

  /**
   * Whether the quote is fresh and denominated in the order currency.
   */
  public function canUse(string $currency, int $now): bool {
    return $this->currency === $currency
      && ($this->validUntil === NULL || $now < $this->validUntil);
  }

}
