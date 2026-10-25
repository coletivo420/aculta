<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cart;

use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\commerce_cart\CartProviderInterface;
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
    TranslationInterface $translation,
    private readonly ?CheckoutSettings $settings = NULL,
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
    $count = 0;
    $soma = 0.0;
    $moeda = NULL;

    foreach ($this->cartProvider->getCarts() as $cart) {
      $cache->addCacheableDependency($cart);
      $site = in_array($cart->bundle(), $this->settings?->lmsOrderTypes() ?? [], TRUE) ? CartSite::CURSOS : CartSite::paraTipo($cart->bundle());
      $checkout = $site === CartSite::ASSINATURA ? $this->urlDoCheckout($cart) : '';
      foreach ($cart->getItems() as $item) {
        $quantity = (int) $item->getQuantity();
        $total = $item->getTotalPrice();
        $count += $quantity;
        if ($total !== NULL) {
          $soma += (float) $total->getNumber();
          $moeda = $total->getCurrencyCode();
        }
        $grupos[$site]['site'] = $site;
        $grupos[$site]['label'] = CartSite::rotulo($site);
        $grupos[$site]['checkout_url'] = $checkout !== '' ? $checkout : ($grupos[$site]['checkout_url'] ?? '');
        $grupos[$site]['ultimo'] = max($grupos[$site]['ultimo'] ?? 0, (int) $item->id());
        $grupos[$site]['items'][] = [
          'id' => (int) $item->id(),
          'title' => (string) $item->getTitle(),
          'quantity' => (string) $quantity,
          'total' => $total !== NULL ? $this->formatPrice((float) $total->getNumber(), $total->getCurrencyCode()) : '',
          'total_value' => $total !== NULL ? (float) $total->getNumber() : 0.0,
        ];
      }
    }

    // Lista única: separadores (grupos) pelo item adicionado por último; itens do mais recente para o mais antigo.
    uasort($grupos, static fn(array $a, array $b): int => $b['ultimo'] <=> $a['ultimo']);
    $sites = [];
    foreach ($grupos as $grupo) {
      usort($grupo['items'], static fn(array $a, array $b): int => $b['id'] <=> $a['id']);
      $soma_grupo = array_sum(array_column($grupo['items'], 'total_value'));
      $sites[] = [
        'key' => $grupo['site'],
        'label' => $grupo['label'],
        'count' => array_sum(array_map(static fn(array $i): int => (int) $i['quantity'], $grupo['items'])),
        'empty' => FALSE,
        'total' => $this->formatPrice($soma_grupo, (string) $moeda),
        'items' => array_map(static fn(array $i): array => array_diff_key($i, ['id' => TRUE, 'total_value' => TRUE]), $grupo['items']),
        'checkout_url' => $grupo['checkout_url'],
      ];
    }

    $url = $this->domainPurposeManager->routeUrl('main', 'commerce_cart.page');
    // Um botão para todo o carrinho (exceto assinatura): junta os itens e abre o checkout do pedido único.
    $finalizar = $this->domainPurposeManager->routeUrl('main', 'aculta_portal.cart_checkout');

    return [
      'count' => $count,
      'empty' => $count === 0,
      'url' => $url !== NULL ? $url->toString() : '',
      'total' => $count === 0 ? '' : $this->formatPrice($soma, (string) $moeda),
      'sites' => $sites,
      'checkout_url' => $finalizar !== NULL ? $finalizar->toString() : '',
      'cache' => $cache,
    ];
  }

  /**
   * Botão de checkout do separador: o checkout do Commerce, no domínio principal, no passo atual do pedido.
   */
  private function urlDoCheckout(OrderInterface $pedido): string {
    // Usa o passo guardado no pedido, sem resolver o fluxo aqui (o resolvedor falha para alguns fluxos, como o de doação,
    // e derruba a página inteira). Passo ausente ou inválido: o Commerce redireciona ao passo correto na própria etapa.
    $passo = $pedido->get('checkout_step')->value ?: 'login';
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
