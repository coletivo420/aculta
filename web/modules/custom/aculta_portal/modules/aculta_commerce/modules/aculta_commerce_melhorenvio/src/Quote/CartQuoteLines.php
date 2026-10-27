<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Linhas do carrinho atual para cotação: variação, quantidade e valor da linha. Não calcula nada.
 */
class CartQuoteLines {

  /**
   * Cria o leitor.
   */
  public function __construct(
    private readonly CartProviderInterface $carts,
  ) {}

  /**
   * Linhas do carrinho do visitante (sessão atual). Lista vazia sem carrinho.
   *
   * @return list<array{variation: \Drupal\commerce_product\Entity\ProductVariationInterface, quantity: int, total: float}>
   */
  public function lines(): array {
    $cart = $this->carts->getCart('default');
    if ($cart === NULL) {
      return [];
    }
    $lines = [];
    foreach ($cart->getItems() as $item) {
      $variation = $item->getPurchasedEntity();
      if (!$variation instanceof ProductVariationInterface) {
        continue;
      }
      $total = $item->getTotalPrice();
      $lines[] = [
        'variation' => $variation,
        'quantity' => max(1, (int) $item->getQuantity()),
        'total' => $total ? (float) $total->getNumber() : 0.0,
      ];
    }
    return $lines;
  }

}
