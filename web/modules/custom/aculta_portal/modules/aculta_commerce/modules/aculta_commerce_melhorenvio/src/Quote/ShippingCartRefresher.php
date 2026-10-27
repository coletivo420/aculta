<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

/**
 * Refaz o cálculo de frete com o CEP já guardado, quando o carrinho muda (quantidade alterada ou item removido).
 *
 * Sem cálculo guardado, não faz nada: não calcula por conta própria. O resultado novo substitui o antigo na sessão.
 */
class ShippingCartRefresher {

  /**
   * Cria o serviço.
   */
  public function __construct(
    private readonly ShippingSimulator $simulator,
    private readonly ShippingQuoteStore $store,
  ) {}

  /**
   * Recalcula para o CEP guardado. Não propaga falha: o simulador já devolve estado, nunca exceção.
   */
  public function refresh(): void {
    $stored = $this->store->load();
    if ($stored === NULL) {
      return;
    }
    $postal = (string) $stored['postal_code'];
    $this->store->save($postal, $this->simulator->simulate($postal, NULL));
  }

}
