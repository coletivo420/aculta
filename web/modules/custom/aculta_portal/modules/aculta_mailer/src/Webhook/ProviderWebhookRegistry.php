<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Webhook;

/**
 * Provedores de webhook por ID.
 */
final class ProviderWebhookRegistry {

  /**
   * Provedores por ID.
   *
   * @var array<string, \Drupal\aculta_mailer\Webhook\ProviderWebhookInterface>
   */
  private array $providers = [];

  /**
   * Indexa os provedores pelo ID.
   *
   * @param iterable<\Drupal\aculta_mailer\Webhook\ProviderWebhookInterface> $providers
   *   Provedores registrados por módulos.
   */
  public function __construct(iterable $providers) {
    foreach ($providers as $provider) {
      $this->providers[$provider->id()] = $provider;
    }
  }

  /**
   * Indica se o provedor está registrado.
   */
  public function has(string $id): bool {
    return isset($this->providers[$id]);
  }

  /**
   * Provedor registrado; lança exceção se não existir.
   */
  public function get(string $id): ProviderWebhookInterface {
    if (!isset($this->providers[$id])) {
      throw new \InvalidArgumentException(sprintf('Provedor de webhook não registrado: %s.', $id));
    }
    return $this->providers[$id];
  }

}
