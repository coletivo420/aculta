<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cart;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\commerce_cart\CartProviderInterface;

/**
 * Apresentação neutra do carrinho para a barra multidomínio e a gaveta do carrinho.
 *
 * Entrega ao tema só contagem, itens, total e a URL do carrinho no MAIN. O carrinho continua sendo a
 * commerce_order do Drupal Commerce; esta classe não cria storage paralelo. A cacheabilidade (contexto
 * cart, usuário e tags das orders) é devolvida em 'cache' para o chamador acumular na página.
 */
final class CartUtility {

  use StringTranslationTrait;

  public function __construct(
    private readonly CartProviderInterface $cartProvider,
    private readonly DomainPurposeManager $domainPurposeManager,
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
  }

  /**
   * @return array{count: int, empty: bool, url: string, total: string, items: list<array{title: string, quantity: string, total: string}>, cache: \Drupal\Core\Cache\CacheableMetadata}
   */
  public function build(): array {
    $cache = (new CacheableMetadata())->addCacheContexts(['cart', 'user', 'session']);
    $count = 0;
    $items = [];
    $sum = 0.0;
    $currency = NULL;

    foreach ($this->cartProvider->getCarts() as $cart) {
      $cache->addCacheableDependency($cart);
      foreach ($cart->getItems() as $item) {
        $quantity = (int) $item->getQuantity();
        $count += $quantity;
        $total = $item->getTotalPrice();
        if ($total !== NULL) {
          $sum += (float) $total->getNumber();
          $currency = $total->getCurrencyCode();
        }
        $items[] = [
          'title' => (string) $item->getTitle(),
          'quantity' => (string) $quantity,
          'total' => $total !== NULL ? $this->formatPrice((float) $total->getNumber(), (string) $currency) : '',
        ];
      }
    }

    $url = $this->domainPurposeManager->routeUrl('main', 'commerce_cart.page');

    return [
      'count' => $count,
      'empty' => $count === 0,
      'url' => $url !== NULL ? $url->toString() : '',
      'total' => $items === [] ? '' : $this->formatPrice($sum, (string) $currency),
      'items' => $items,
      'cache' => $cache,
    ];
  }

  private function formatPrice(float $amount, string $currency): string {
    $prefix = $currency === 'BRL' ? 'R$ ' : '';
    return $prefix . number_format($amount, 2, ',', '.');
  }

}
