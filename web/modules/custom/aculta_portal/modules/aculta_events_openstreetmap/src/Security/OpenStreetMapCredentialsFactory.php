<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Security;

use Drupal\aculta_portal\Secrets\SecretsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a credentials adapter for the module services.
 *
 * It also supports isolated Kernel tests without Portal bootstrap.
 */
final class OpenStreetMapCredentialsFactory {

  /**
   * Guarda o container para localizar o serviço central do Portal.
   */
  public function __construct(private readonly ContainerInterface $container) {}

  /**
   * Cria o adaptador de credenciais ou um adaptador fechado sem SecretsManager.
   */
  public function create(): OpenStreetMapCredentialProviderInterface {
    $secrets = $this->container->has('aculta_portal.secrets_manager')
      ? $this->container->get('aculta_portal.secrets_manager')
      : NULL;
    return new OpenStreetMapCredentialProvider($secrets instanceof SecretsManager ? $secrets : NULL);
  }

}
