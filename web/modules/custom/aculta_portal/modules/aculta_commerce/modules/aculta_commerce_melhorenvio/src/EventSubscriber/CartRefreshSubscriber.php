<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\EventSubscriber;

use Drupal\aculta_commerce_melhorenvio\Quote\ShippingCartRefresher;
use Drupal\commerce_cart\Event\CartEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Refaz o frete do visitante sempre que o carrinho muda: adição, quantidade alterada ou item removido.
 *
 * Todos os carrinhos passam pelo CartManager do Commerce (carrinho completo, botão de remover e "Adicionar ao carrinho",
 * inclusive o AJAX), então o recálculo fica em um único ponto. O evento de atualização é disparado antes do save do
 * carrinho no formulário; o recálculo atual não lê o carrinho. Quando o cálculo passar a usar o pacote do carrinho,
 * ele precisa rodar depois do save (ver docs/DECISOES-FRETE.md).
 */
class CartRefreshSubscriber implements EventSubscriberInterface {

  /**
   * Cria o subscriber.
   */
  public function __construct(
    private readonly ShippingCartRefresher $refresher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CartEvents::CART_ORDER_ITEM_ADD => 'refreshShipping',
      CartEvents::CART_ORDER_ITEM_UPDATE => 'refreshShipping',
      CartEvents::CART_ORDER_ITEM_REMOVE => 'refreshShipping',
    ];
  }

  /**
   * Recalcula o frete guardado para o visitante. O evento não é usado; só o disparo importa.
   */
  public function refreshShipping(object $event): void {
    $this->refresher->refresh();
  }

}
