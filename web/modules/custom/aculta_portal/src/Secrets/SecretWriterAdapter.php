<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Secrets;

use Drupal\aculta_mailer\Transport\SecretWriterInterface;

/**
 * Grava credenciais no arquivo de segredos pelo gerenciador (mesmas regras do painel de credenciais).
 */
final class SecretWriterAdapter implements SecretWriterInterface {

  public function __construct(
    private readonly SecretsManager $secrets,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function save(string $name, string $value): array {
    $result = $this->secrets->save([$name => $value]);
    return ['ok' => $result['ok'], 'message' => $result['message']];
  }

}
