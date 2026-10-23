<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Política de disponibilidade de checkout e validação de conflitos entre contextos.
 *
 * Camada de consulta sobre os providers. Não altera rotas, não implementa `CheckoutOrderManagerInterface` e
 * não guarda preferência por usuário ou sessão; a escolha do fluxo continua nos checkout flows nativos do Commerce.
 */
final class CheckoutPolicyManager {

  public function __construct(private readonly CheckoutConfigurationResolver $resolver) {}

  /** Contexto desconhecido nunca libera checkout. */
  public function allowsDedicated(string $contextId): bool {
    return $this->resolver->resolve($contextId)?->mode->allowsDedicated() ?? FALSE;
  }

  /** Contexto desconhecido nunca libera checkout. */
  public function allowsCommon(string $contextId): bool {
    return $this->resolver->resolve($contextId)?->mode->allowsCommon() ?? FALSE;
  }

  /**
   * Tipos de pedido reivindicados por mais de um contexto.
   *
   * Evita reatribuir em silêncio tipos de pedido de outra experiência (ADR-013).
   *
   * @return array<string, list<string>>
   *   Tipo de pedido → IDs dos contextos que o reivindicam (ordenados), somente quando houver mais de um.
   */
  public function orderTypeConflicts(): array {
    $donos = [];
    foreach ($this->resolver->resolveAll() as $id => $configuracao) {
      foreach ($configuracao->orderTypeIds as $tipo) {
        $donos[$tipo][] = $id;
      }
    }
    $conflitos = array_filter($donos, static fn(array $ids): bool => count($ids) > 1);
    ksort($conflitos);
    return array_map(static function (array $ids): array {
      sort($ids);
      return $ids;
    }, $conflitos);
  }

}
