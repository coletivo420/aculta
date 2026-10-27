<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Linhas do carrinho para cotação de frete: somente produtos da loja (bundle produto_loja).
 *
 * Lê todos os carrinhos da sessão, como a gaveta do carrinho. LMS (produto_curso), doações e qualquer outro produto
 * digital ficam fora do frete: o cálculo é restrito à loja.
 */
class CartQuoteLines {

  /**
   * Bundle de produto físico da loja: o único que entra no frete.
   */
  public const BUNDLE_LOJA = 'produto_loja';

  /**
   * Tipo de pedido da loja. Pedidos de curso (LMS), doação e assinatura têm tipos próprios e não entram no frete.
   */
  public const ORDER_TYPE_LOJA = 'default';

  /**
   * Cria o leitor.
   */
  public function __construct(
    private readonly CartProviderInterface $carts,
  ) {}

  /**
   * Linhas de produto da loja de todos os carrinhos do visitante. Lista vazia sem itens da loja.
   *
   * @return list<array{variation: \Drupal\commerce_product\Entity\ProductVariationInterface, quantity: int, total: float}>
   */
  public function lines(): array {
    $lines = [];
    foreach ($this->carts->getCarts() as $cart) {
      if ($cart->bundle() !== self::ORDER_TYPE_LOJA) {
        continue;
      }
      foreach ($cart->getItems() as $item) {
        $variation = $item->getPurchasedEntity();
        if (!$variation instanceof ProductVariationInterface || !$this->isStoreProduct($variation)) {
          continue;
        }
        $total = $item->getTotalPrice();
        $lines[] = [
          'variation' => $variation,
          'quantity' => max(1, (int) $item->getQuantity()),
          'total' => $total ? (float) $total->getNumber() : 0.0,
        ];
      }
    }
    return $lines;
  }

  /**
   * Produto físico da loja (produto_loja). LMS, doações e demais produtos não entram no frete.
   */
  private function isStoreProduct(ProductVariationInterface $variation): bool {
    $product = $variation->getProduct();
    return $product !== NULL && $product->bundle() === self::BUNDLE_LOJA;
  }

}
