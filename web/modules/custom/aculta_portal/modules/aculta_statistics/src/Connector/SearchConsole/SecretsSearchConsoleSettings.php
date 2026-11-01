<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Connector\SearchConsole;

use Drupal\aculta_statistics\Connector\Ga4\Ga4Settings;
use Drupal\aculta_portal\Secrets\SecretsManager;

/**
 * Lê GSC_SITE_URL pelo aculta_portal.secrets_manager e reaproveita a conta de serviço do GA4.
 */
final class SecretsSearchConsoleSettings implements SearchConsoleSettings {

  public function __construct(
    private readonly ?SecretsManager $secrets,
    private readonly Ga4Settings $ga4,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function siteUrl(): ?string {
    $value = trim((string) $this->secrets?->value('GSC_SITE_URL'));
    return $value !== '' && (str_starts_with($value, 'sc-domain:') || str_starts_with($value, 'https://') || str_starts_with($value, 'http://')) ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function serviceAccount(): ?array {
    return $this->ga4->serviceAccount();
  }

}
