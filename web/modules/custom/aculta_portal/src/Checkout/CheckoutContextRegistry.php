<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Registry dos providers de contexto de checkout (tag `aculta_portal.checkout_provider`).
 *
 * A ordem é determinística: peso e, em empate, ID. Dois providers com o mesmo ID são um erro de configuração
 * e falham de forma explícita em vez de um sobrescrever o outro em silêncio.
 */
final class CheckoutContextRegistry {

  /**
   * Providers ordenados por ID, ou NULL antes do primeiro uso.
   *
   * @var array<string, \Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface>|null
   */
  private ?array $ordenados = NULL;

  /**
   * Constrói o registro a partir dos providers marcados com a tag.
   *
   * @param iterable<\Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface> $providers
   *   Providers de contexto de checkout.
   */
  public function __construct(private readonly iterable $providers) {}

  /**
   * Retorna os providers na ordem determinística.
   *
   * @return array<string, \Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface>
   *   Providers por ID, na ordem determinística.
   */
  public function all(): array {
    if ($this->ordenados !== NULL) {
      return $this->ordenados;
    }
    $por_id = [];
    foreach ($this->providers as $provider) {
      $id = $provider->id();
      if (isset($por_id[$id])) {
        throw new \LogicException(sprintf('Provider de checkout duplicado para o contexto "%s": %s e %s.', $id, $por_id[$id]::class, $provider::class));
      }
      $por_id[$id] = $provider;
    }
    uasort($por_id, static fn(CheckoutContextProviderInterface $a, CheckoutContextProviderInterface $b): int => [$a->weight(), $a->id()] <=> [$b->weight(), $b->id()]);
    return $this->ordenados = $por_id;
  }

  /**
   * Retorna o provider do identificador informado.
   *
   * @param string $id
   *   Identificador do provider.
   *
   * @return \Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface|null
   *   Provider encontrado, ou NULL quando ausente.
   */
  public function get(string $id): ?CheckoutContextProviderInterface {
    return $this->all()[$id] ?? NULL;
  }

  /**
   * Informa se existe um provider com o identificador informado.
   *
   * @param string $id
   *   Identificador do provider.
   *
   * @return bool
   *   TRUE quando o provider existe.
   */
  public function has(string $id): bool {
    return isset($this->all()[$id]);
  }

}
