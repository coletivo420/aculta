<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

/**
 * Filters and resolves trusted shipping quotes without inventing fallbacks.
 *
 * Carrier quote collection and pricing are deliberately external. An empty
 * result is an unavailable shipping choice, never implicit free shipping.
 */
final class ShippingQuoteSelector {

  /**
   * Keep only fresh, currency-matching quotes, with unique stable identities.
   *
   * @param array<ShippingQuote> $quotes
   *   Quotes from trusted internal rate providers, never request POST values.
   * @param string $currency
   *   Order currency.
   * @param int $now
   *   Unix timestamp supplied by the caller for deterministic tests.
   *
   * @return array<ShippingQuote>
   *   Available quotes in provider order.
   */
  public function available(array $quotes, string $currency, int $now): array {
    if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
      throw new \InvalidArgumentException('Invalid order currency.');
    }
    $available = [];
    $seen = [];
    foreach ($quotes as $quote) {
      if (!$quote instanceof ShippingQuote) {
        throw new \InvalidArgumentException('Unexpected quote payload.');
      }
      if (!$quote->canUse($currency, $now)) {
        continue;
      }
      if (isset($seen[$quote->id()])) {
        throw new \LogicException('Shipping providers produced duplicate quote IDs.');
      }
      $seen[$quote->id()] = TRUE;
      $available[] = $quote;
    }
    return $available;
  }

  /**
   * Resolve a chosen service only from a fresh, trusted server-side list.
   *
   * @param array<ShippingQuote> $quotes
   *   Trusted provider quotes.
   * @param string $currency
   *   Order currency.
   * @param int $now
   *   Unix timestamp supplied by the caller for deterministic tests.
   * @param string $selectedId
   *   The stable quote ID selected by the customer.
   *
   * @return \Drupal\aculta_commerce\Shipping\ShippingQuote|null
   *   Selected quote or NULL, never a fabricated zero-cost choice.
   */
  public function select(array $quotes, string $currency, int $now, string $selectedId): ?ShippingQuote {
    foreach ($this->available($quotes, $currency, $now) as $quote) {
      if ($quote->id() === $selectedId) {
        return $quote;
      }
    }
    return NULL;
  }

}
