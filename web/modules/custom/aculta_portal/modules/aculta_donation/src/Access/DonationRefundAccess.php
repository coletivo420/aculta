<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Access;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Doações não podem ser estornadas (decisão do responsável, 2026-10-17).
 *
 * Bloqueia a operação de estorno (refund-payment) quando o pedido tem item de doação. O controle é do servidor: a
 * checagem é somada às exigências da rota do Commerce, que continuam valendo.
 */
final class DonationRefundAccess {

  /** Tipo do item de pedido de doação. */
  public const TIPO_ITEM_DOACAO = 'donation';

  /** Operação do Commerce que estorna um pagamento. */
  public const OPERACAO_ESTORNO = 'refund-payment';

  public function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResult {
    if ($route_match->getParameter('operation') !== self::OPERACAO_ESTORNO) {
      return AccessResult::allowed();
    }
    $pedido = $route_match->getParameter('commerce_order');
    if (!$pedido instanceof OrderInterface) {
      return AccessResult::allowed();
    }
    return self::pedidoTemDoacao($pedido)
      ? AccessResult::forbidden('Doações não podem ser estornadas.')->addCacheableDependency($pedido)
      : AccessResult::allowed()->addCacheableDependency($pedido);
  }

  /** Indica se o pedido tem ao menos um item de doação. */
  public static function pedidoTemDoacao(OrderInterface $pedido): bool {
    foreach ($pedido->getItems() as $item) {
      if ($item->bundle() === self::TIPO_ITEM_DOACAO) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
