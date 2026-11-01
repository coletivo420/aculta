<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Resolve a configuração efetiva de checkout de cada contexto, a partir dos providers.
 *
 * Só leitura e sem persistência própria: a fonte da verdade é a configuração do módulo proprietário.
 */
final class CheckoutConfigurationResolver {

  public function __construct(private readonly CheckoutContextRegistry $registry) {}

  /** NULL quando o contexto não está registrado. */
  public function resolve(string $contextId): ?CheckoutContextConfiguration {
    return $this->registry->get($contextId)?->configuration();
  }

  /**
   * @return array<string, \Drupal\aculta_portal\Checkout\CheckoutContextConfiguration>
   *   Configurações por ID de contexto, na ordem do registry.
   */
  public function resolveAll(): array {
    $configuracoes = [];
    foreach ($this->registry->all() as $id => $provider) {
      $configuracoes[$id] = $provider->configuration();
    }
    return $configuracoes;
  }

}
