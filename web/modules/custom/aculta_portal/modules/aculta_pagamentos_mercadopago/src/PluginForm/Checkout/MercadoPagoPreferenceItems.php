<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\PluginForm\Checkout;

use Drupal\commerce_price\Price;

/**
 * Builds preference line items that exactly represent the Commerce amount.
 */
final class MercadoPagoPreferenceItems {

  /**
   * Normalizes preference items to the order merchandise total.
   *
   * Commerce may apply promotions, taxes, or other order adjustments after
   * the unit price was set. Checkout Pro calculates from preference items, so
   * send each adjusted line total and allocate any remaining order-level
   * adjustment before creating the remote preference.
   *
   * @param array $lines
   *   Rows containing `item` (Mercado Pago payload) and `amount` (adjusted
   *   Commerce line total as a Price).
   * @param \Drupal\commerce_price\Price $target
   *   The order total less separately represented shipping.
   *
   * @return array
   *   Mercado Pago preference items whose sum equals the target.
   *
   * @throws \InvalidArgumentException
   *   When the order cannot be represented as valid non-negative items.
   */
  public static function build(array $lines, Price $target): array {
    if (!$target->isPositive()) {
      throw new \InvalidArgumentException('The merchandise total must be positive.');
    }

    $normalized = [];
    $sum = new Price('0', $target->getCurrencyCode());
    foreach ($lines as $line) {
      $item = $line['item'] ?? NULL;
      $amount = $line['amount'] ?? NULL;
      if (!is_array($item) || !$amount instanceof Price
        || $amount->getCurrencyCode() !== $target->getCurrencyCode()
        || $amount->isNegative()) {
        throw new \InvalidArgumentException('An order line has an invalid adjusted amount.');
      }
      if (!$amount->isPositive()) {
        continue;
      }
      $quantity = filter_var($item['quantity'] ?? NULL, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      if ($quantity === FALSE) {
        throw new \InvalidArgumentException('An order line has an invalid quantity.');
      }
      if ($quantity > 1) {
        $item['title'] = sprintf('%s (%d un.)', (string) ($item['title'] ?? ''), $quantity);
      }
      // A single unit priced at the adjusted line total preserves every cent
      // even when a line promotion cannot be evenly divided by its quantity.
      $item['quantity'] = 1;
      $item['currency_id'] = $amount->getCurrencyCode();
      $normalized[] = ['item' => $item, 'amount' => $amount];
      $sum = $sum->add($amount);
    }

    if ($normalized === []) {
      throw new \InvalidArgumentException('The order has no positive merchandise lines.');
    }

    $difference = $target->subtract($sum);
    if ($difference->isPositive()) {
      $normalized[] = [
        'item' => [
          'title' => 'Outros ajustes do pedido',
          'quantity' => 1,
          'currency_id' => $target->getCurrencyCode(),
        ],
        'amount' => $difference,
      ];
    }
    elseif ($difference->isNegative()) {
      $discount = $difference->multiply('-1');
      // Apply an order-level discount to the largest lines first. This keeps
      // all preference item prices non-negative without losing precision.
      usort($normalized, static fn (array $a, array $b): int => $b['amount']->compareTo($a['amount']));
      foreach ($normalized as &$line) {
        if (!$discount->isPositive()) {
          break;
        }
        $line_total = $line['amount'];
        if ($line_total->compareTo($discount) <= 0) {
          $discount = $discount->subtract($line_total);
          $line['amount'] = new Price('0', $target->getCurrencyCode());
        }
        else {
          $line['amount'] = $line_total->subtract($discount);
          $discount = new Price('0', $target->getCurrencyCode());
        }
      }
      unset($line);
      $normalized = array_values(array_filter($normalized, static fn (array $line): bool => $line['amount']->isPositive()));
      if ($discount->isPositive()) {
        throw new \InvalidArgumentException('Order-level discounts exceed the available merchandise value.');
      }
    }

    return array_map(static function (array $line): array {
      $line['item']['unit_price'] = (float) $line['amount']->getNumber();
      return $line['item'];
    }, $normalized);
  }

}
