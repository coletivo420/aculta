<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Security;

use Drupal\aculta_portal\Secrets\SecretsManager;

/**
 * Resolve credenciais pelo SecretsManager sem persistir os valores.
 */
final class OpenStreetMapCredentialProvider implements OpenStreetMapCredentialProviderInterface {

  public function __construct(
    private readonly ?SecretsManager $secrets,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function siteEnvironment(): string {
    return $this->secrets?->environment() ?? 'test';
  }

  /**
   * {@inheritdoc}
   */
  public function clientId(string $environment): ?string {
    return $this->valueForEnvironment($environment, 'CLIENT_ID');
  }

  /**
   * {@inheritdoc}
   */
  public function clientSecret(string $environment): ?string {
    return $this->valueForEnvironment($environment, 'CLIENT_SECRET');
  }

  /**
   * {@inheritdoc}
   */
  public function tokenEncryptionKey(): ?string {
    return $this->secrets?->value('ACULTA_OSM_TOKEN_ENCRYPTION_KEY');
  }

  /**
   * Lê credencial somente quando pertence ao ambiente ativo do site.
   */
  private function valueForEnvironment(string $environment, string $suffix): ?string {
    if (!in_array($environment, ['test', 'production'], TRUE) || $environment !== $this->siteEnvironment()) {
      return NULL;
    }
    $prefix = $environment === 'test' ? 'OSM_TEST_' : 'OSM_PRODUCTION_';
    return $this->secrets?->value($prefix . $suffix);
  }

}
