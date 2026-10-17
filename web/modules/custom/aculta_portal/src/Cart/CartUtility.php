<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cart;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Carrinho único do site, agrupado por separador (loja, cursos e apoio). Cada separador tem o próprio botão de checkout,
 * no domínio principal (rotas do Commerce).
 */
final class CartUtility {

  use StringTranslationTrait;

  public function __construct(
    private readonly CartProviderInterface $cartProvider,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly CheckoutOrderManagerInterface $checkoutOrderManager,
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
  }

  /**
   * Dados neutros do painel: totais do carrinho e um grupo por separador (loja, cursos, apoio).
   *
   * @return array{count: int, empty: bool, url: string, total: string, sites: list<array{key: string, label: string, count: int, empty: bool, total: string, items: list<array{title: string, quantity: string, total: string}>, checkout_url: string}>, cache: \Drupal\Core\Cache\CacheableMetadata}
   */
  public function build(): array {
    $cache = (new CacheableMetadata())->addCacheContexts(['cart', 'user', 'session']);
    $grupos = [];
    foreach (CartSite::ORDEM as $site) {
      $grupos[$site] = ['key' => $site, 'label' => CartSite::rotulo($site), 'count' => 0, 'empty' => TRUE, 'total' => '', 'items' => [], 'checkout_url' => '', '_soma' => 0.0, '_moeda' => NULL];
    }

    foreach ($this->cartProvider->getCarts() as $cart) {
      $cache->addCacheableDependency($cart);
      $site = CartSite::paraTipo($cart->bundle());
      if ($grupos[$site]['checkout_url'] === '') {
        $grupos[$site]['checkout_url'] = $this->urlDoCheckout($cart);
      }
      foreach ($cart->getItems() as $item) {
        $quantity = (int) $item->getQuantity();
        $total = $item->getTotalPrice();
        $grupos[$site]['count'] += $quantity;
        if ($total !== NULL) {
          $grupos[$site]['_soma'] += (float) $total->getNumber();
          $grupos[$site]['_moeda'] = $total->getCurrencyCode();
        }
        $grupos[$site]['items'][] = [
          'title' => (string) $item->getTitle(),
          'quantity' => (string) $quantity,
          'total' => $total !== NULL ? $this->formatPrice((float) $total->getNumber(), $total->getCurrencyCode()) : '',
        ];
      }
    }

    $count = 0;
    $soma = 0.0;
    $moeda = NULL;
    $sites = [];
    foreach ($grupos as $grupo) {
      $count += $grupo['count'];
      $soma += $grupo['_soma'];
      $moeda = $grupo['_moeda'] ?? $moeda;
      $sites[] = [
        'key' => $grupo['key'],
        'label' => $grupo['label'],
        'count' => $grupo['count'],
        'empty' => $grupo['count'] === 0,
        'total' => $grupo['items'] === [] ? '' : $this->formatPrice($grupo['_soma'], (string) $grupo['_moeda']),
        'items' => $grupo['items'],
        'checkout_url' => $grupo['checkout_url'],
      ];
    }

    $url = $this->domainPurposeManager->routeUrl('main', 'commerce_cart.page');

    return [
      'count' => $count,
      'empty' => $count === 0,
      'url' => $url !== NULL ? $url->toString() : '',
      'total' => $count === 0 ? '' : $this->formatPrice($soma, (string) $moeda),
      'sites' => $sites,
      'cache' => $cache,
    ];
  }

  /**
   * Botão de checkout do separador: o checkout do Commerce, no domínio principal, no passo atual do pedido.
   */
  private function urlDoCheckout(OrderInterface $pedido): string {
    $passo = $this->checkoutOrderManager->getCheckoutStepId($pedido);
    $url = $this->domainPurposeManager->routeUrl('main', 'commerce_checkout.form', [
      'commerce_order' => $pedido->id(),
      'step' => $passo,
    ]);
    return $url !== NULL ? $url->toString() : '';
  }

  private function formatPrice(float $amount, string $currency): string {
    $prefix = $currency === 'BRL' ? 'R$ ' : '';
    return $prefix . number_format($amount, 2, ',', '.');
  }

}
