<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Connector\Ga4;

use Drupal\aculta_portal\Secrets\SecretsManager;

/**
 * Lê as variáveis do GA4 pelo aculta_portal.secrets_manager (AGENTS.md: credenciais só por ele).
 */
final class SecretsGa4Settings implements Ga4Settings {

  public function __construct(
    // Opcional: sem o aculta_portal instalado, o GA4 fica desligado.
    private readonly ?SecretsManager $secrets = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function propertyId(): ?string {
    $value = trim((string) $this->secrets?->value('GA4_PROPERTY_ID'));
    return preg_match('/^\d{3,20}$/', $value) === 1 ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function serviceAccount(): ?array {
    $raw = trim((string) $this->secrets?->value('GA4_SERVICE_ACCOUNT_JSON'));
    if ($raw === '') {
      return NULL;
    }
    $json = base64_decode($raw, TRUE);
    $key = is_string($json) ? json_decode($json, TRUE) : NULL;
    if (!is_array($key) || ($key['type'] ?? NULL) !== 'service_account' || !is_string($key['private_key'] ?? NULL)) {
      return NULL;
    }
    return $key;
  }

}
