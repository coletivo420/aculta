<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Configuração efetiva de checkout de um contexto, como o provider a declara.
 *
 * Imutável e normalizada (IDs únicos e ordenados), para que a resolução seja determinística. Só referencia
 * tipos de pedido e checkout flows do Commerce por ID; não os cria nem os duplica.
 */
final class CheckoutContextConfiguration {

  /** @var list<string> */
  public readonly array $orderTypeIds;

  /** @var list<string> */
  public readonly array $checkoutFlowIds;

  /**
   * @param list<string> $orderTypeIds
   *   Tipos de pedido do Commerce que o contexto reivindica.
   * @param list<string> $checkoutFlowIds
   *   Checkout flows do Commerce que o contexto utiliza.
   * @param bool $gatesCommonCheckout
   *   TRUE quando a modalidade do contexto também liga ou desliga as rotas globais de carrinho e checkout do
   *   Commerce (hoje, só Doações). Declara o efeito existente; não o cria.
   */
  public function __construct(
    public readonly string $contextId,
    public readonly CheckoutMode $mode,
    array $orderTypeIds = [],
    array $checkoutFlowIds = [],
    public readonly bool $gatesCommonCheckout = FALSE,
  ) {
    $this->orderTypeIds = self::normalize($orderTypeIds);
    $this->checkoutFlowIds = self::normalize($checkoutFlowIds);
  }

  /**
   * @param list<string> $ids
   * @return list<string>
   */
  private static function normalize(array $ids): array {
    $ids = array_values(array_unique(array_filter($ids, static fn(string $id): bool => $id !== '')));
    sort($ids);
    return $ids;
  }

}
