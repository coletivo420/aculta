<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Security;

/**
 * Ambiente e credenciais do cliente OAuth do OpenStreetMap.
 */
interface OpenStreetMapCredentialProviderInterface {

  /**
   * Ambiente efetivo do site, resolvido pelo contrato central.
   */
  public function siteEnvironment(): string;

  /**
   * ID OAuth da aplicação no ambiente indicado.
   */
  public function clientId(string $environment): ?string;

  /**
   * Segredo OAuth da aplicação no ambiente indicado.
   */
  public function clientSecret(string $environment): ?string;

  /**
   * Chave hex de 64 caracteres para cifrar o token no State API.
   */
  public function tokenEncryptionKey(): ?string;

}
