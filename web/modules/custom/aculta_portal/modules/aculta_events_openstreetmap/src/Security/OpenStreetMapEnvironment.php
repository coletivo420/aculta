<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Security;

use Drupal\Core\Site\Settings;

/**
 * Resolves a fixed OpenStreetMap endpoint from the explicit site environment.
 *
 * O default é sempre a API de desenvolvimento.
 *
 * Produção exige configuração explícita e ambiente de site production.
 * Um site de teste nunca seleciona a API de produção.
 */
final class OpenStreetMapEnvironment {

  private const TEST_HOST = 'https://master.apis.dev.openstreetmap.org';

  private const PRODUCTION_HOST = 'https://www.openstreetmap.org';

  public function __construct(
    private readonly OpenStreetMapCredentialProviderInterface $credentials,
    private readonly Settings $settings,
  ) {}

  /**
   * Ambiente de API ou NULL quando diverge do ambiente do site. */
  public function environment(): ?string {
    $configured = $this->settings->get('aculta_openstreetmap_environment');
    return self::resolve($this->credentials->siteEnvironment(), is_string($configured) ? $configured : NULL);
  }

  /**
   * Política testável: produção nunca herda o default de desenvolvimento. */
  public static function resolve(string $siteEnvironment, ?string $configuredEnvironment): ?string {
    if ($configuredEnvironment === NULL || $configuredEnvironment === '') {
      if ($siteEnvironment === 'production') {
        return NULL;
      }
      $configuredEnvironment = 'test';
    }
    if (!in_array($configuredEnvironment, ['test', 'production'], TRUE)
      || $configuredEnvironment !== $siteEnvironment) {
      return NULL;
    }
    return $configuredEnvironment;
  }

  /**
   * URL fixa da API de edição no ambiente ativo. */
  public function apiBaseUrl(): ?string {
    return match ($this->environment()) {
      'test' => self::TEST_HOST . '/api/0.6',
      'production' => 'https://api.openstreetmap.org/api/0.6',
      default => NULL,
    };
  }

  /**
   * URL fixa do OAuth no ambiente ativo. */
  public function oauthBaseUrl(): ?string {
    return match ($this->environment()) {
      'test' => self::TEST_HOST,
      'production' => self::PRODUCTION_HOST,
      default => NULL,
    };
  }

}
