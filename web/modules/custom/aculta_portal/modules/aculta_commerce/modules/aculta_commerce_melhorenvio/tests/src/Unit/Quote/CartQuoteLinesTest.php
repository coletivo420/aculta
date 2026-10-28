<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Quote;

use Drupal\aculta_commerce_melhorenvio\Quote\CartQuoteLines;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Só produtos da loja entram no frete: LMS (produto_curso), doações e outros ficam de fora.
 */
#[CoversClass(CartQuoteLines::class)]
#[Group('aculta_commerce_melhorenvio')]
final class CartQuoteLinesTest extends UnitTestCase {

  private function item(string $bundle, int $quantity): OrderItemInterface {
    $product = $this->createMock(ProductInterface::class);
    $product->method('bundle')->willReturn($bundle);
    $variation = $this->createMock(ProductVariationInterface::class);
    $variation->method('getProduct')->willReturn($product);
    $item = $this->createMock(OrderItemInterface::class);
    $item->method('getPurchasedEntity')->willReturn($variation);
    $item->method('getQuantity')->willReturn($quantity);
    $item->method('getTotalPrice')->willReturn(NULL);
    return $item;
  }

  private function cart(array $items, string $orderType = 'default'): OrderInterface {
    $cart = $this->createMock(OrderInterface::class);
    $cart->method('getItems')->willReturn($items);
    $cart->method('bundle')->willReturn($orderType);
    return $cart;
  }

  /**
   * Itens de LMS e de doação são ignorados; só o produto da loja vai ao cálculo, em todos os carrinhos.
   */
  public function testOnlyStoreProductsAreQuoted(): void {
    $carts = $this->createMock(CartProviderInterface::class);
    $carts->method('getCarts')->willReturn([
      $this->cart([$this->item('produto_loja', 2), $this->item('produto_curso', 1)]),
      $this->cart([$this->item('default', 1)]),
    ]);
    $lines = (new CartQuoteLines($carts))->lines();
    $this->assertCount(1, $lines);
    $this->assertSame(2, $lines[0]['quantity']);
  }

  /**
   * Pedido misto: produto da loja junto de LMS num pedido de curso entra só o produto da loja. Doação no pedido de
   * doação não entra.
   */
  public function testStoreItemInMixedOrderIsQuotedButDigitalItemsAreNot(): void {
    $carts = $this->createMock(CartProviderInterface::class);
    $carts->method('getCarts')->willReturn([
      $this->cart([$this->item('produto_curso', 1), $this->item('produto_loja', 1)], 'curso'),
      $this->cart([$this->item('default', 1)], 'doacao'),
    ]);
    $lines = (new CartQuoteLines($carts))->lines();
    $this->assertCount(1, $lines);
  }

  /**
   * Carrinho só com LMS e doação não tem linhas de frete: o frete não é calculado.
   */
  public function testDigitalOnlyCartHasNoFreightLines(): void {
    $carts = $this->createMock(CartProviderInterface::class);
    $carts->method('getCarts')->willReturn([$this->cart([$this->item('produto_curso', 1), $this->item('default', 1)])]);
    $this->assertSame([], (new CartQuoteLines($carts))->lines());
  }

}
