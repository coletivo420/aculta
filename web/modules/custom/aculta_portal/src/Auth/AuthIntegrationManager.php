<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Auth;

use Drupal\Core\Config\ConfigFactoryInterface;

/** Reads availability of authentication integrations for Portal consumers. */
final class AuthIntegrationManager {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Determines whether the configured Google provider can be presented.
   */
  public function isGoogleConfigured(): bool {
    $config = $this->configFactory->get('social_auth_google.settings');
    return !empty($config->get('client_id')) && !empty($config->get('client_secret'));
  }

}
