<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cart;

use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Une o carrinho em um pedido só, com o fluxo de checkout mais exigente entre as categorias presentes
 * (decisão do responsável, 2026-10-17): LMS (curso) > loja (default) > doação (doacao). A assinatura fica de fora:
 * tem checkout próprio.
 */
final class CartConsolidator {

  /** Tipo de pedido de assinatura: fora do carrinho unificado. */
  public const TIPO_ASSINATURA = 'assinatura';

  public function __construct(
    private readonly CartProviderInterface $cartProvider,
    private readonly CartSessionInterface $cartSession,
    private readonly CurrentStoreInterface $currentStore,
    private readonly AccountInterface $currentUser,
    private readonly ?CheckoutSettings $settings = NULL,
  ) {}

  /**
   * Categorias do carrinho, da mais exigente para a mais simples: LMS (tipo configurado e histórico `curso`),
   * Loja e doação.
   *
   * @return list<string>
   */
  private function prioridade(): array {
    return [
      ...($this->settings?->lmsOrderTypes() ?? [CheckoutSettings::LEGACY_LMS_ORDER_TYPE]),
      $this->settings?->shopOrderType() ?? CheckoutSettings::DEFAULT_SHOP_ORDER_TYPE,
      'doacao',
    ];
  }

  /**
   * Junta os carrinhos (exceto assinatura) em um pedido só e o torna o carrinho ativo. NULL se não há o que juntar.
   */
  public function consolidar(): ?OrderInterface {
    $carrinhos = array_values(array_filter(
      $this->cartProvider->getCarts(),
      static fn(OrderInterface $c): bool => $c->bundle() !== self::TIPO_ASSINATURA,
    ));
    if ($carrinhos === []) {
      return NULL;
    }

    $tipo = $this->tipoFinal($carrinhos);
    $destino = NULL;
    foreach ($carrinhos as $carrinho) {
      if ($carrinho->bundle() === $tipo) {
        $destino = $carrinho;
        break;
      }
    }
    if ($destino === NULL) {
      $destino = Order::create([
        'type' => $tipo,
        'state' => 'draft',
        'cart' => TRUE,
        'uid' => $this->currentUser->id(),
        'store_id' => $this->currentStore->getStore()->id(),
      ]);
      $destino->save();
    }

    foreach ($carrinhos as $carrinho) {
      if ($carrinho->id() === $destino->id()) {
        continue;
      }
      foreach ($carrinho->getItems() as $item) {
        $carrinho->removeItem($item);
        $destino->addItem($item);
        $item->set('order_id', $destino->id());
        $item->save();
      }
      $carrinho->save();
      $carrinho->delete();
    }

    $destino->save();
    $this->cartSession->addCartId($destino->id(), CartSessionInterface::ACTIVE);
    return $destino;
  }

  /** Categoria final: a mais exigente entre as presentes. */
  private function tipoFinal(array $carrinhos): string {
    $presentes = array_map(static fn(OrderInterface $c): string => $c->bundle(), $carrinhos);
    foreach ($this->prioridade() as $tipo) {
      if (in_array($tipo, $presentes, TRUE)) {
        return $tipo;
      }
    }
    return $this->settings?->shopOrderType() ?? CheckoutSettings::DEFAULT_SHOP_ORDER_TYPE;
  }

}
