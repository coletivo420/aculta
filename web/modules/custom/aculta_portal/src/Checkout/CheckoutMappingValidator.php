<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Valida um tipo de pedido escolhido para um contexto contra o que os outros contextos já reivindicam.
 *
 * Evita reatribuir em silêncio tipos de pedido de outra experiência (ADR-013). Só leitura.
 */
final class CheckoutMappingValidator {

  public function __construct(private readonly CheckoutConfigurationResolver $resolver) {}

  /**
   * IDs dos outros contextos que já reivindicam o tipo de pedido.
   *
   * @return list<string>
   */
  public function conflictingContexts(string $contextId, string $orderTypeId): array {
    $outros = [];
    foreach ($this->resolver->resolveAll() as $id => $configuracao) {
      if ($id !== $contextId && in_array($orderTypeId, $configuracao->orderTypeIds, TRUE)) {
        $outros[] = $id;
      }
    }
    sort($outros);
    return $outros;
  }

}
