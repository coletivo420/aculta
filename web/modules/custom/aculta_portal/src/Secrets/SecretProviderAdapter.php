<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Secrets;

use Drupal\aculta_mailer\Transport\SecretProviderInterface;

/**
 * Entrega ao aculta_mailer as credenciais do contrato, pelo gerenciador de segredos deste módulo.
 */
final class SecretProviderAdapter implements SecretProviderInterface {

  public function __construct(
    private readonly SecretsManager $secrets,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function value(string $name): ?string {
    return $this->secrets->value($name);
  }

}
