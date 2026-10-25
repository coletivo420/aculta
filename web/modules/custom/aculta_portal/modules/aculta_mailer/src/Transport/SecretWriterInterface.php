<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Transport;

/**
 * Gravação de credenciais do contrato no arquivo de segredos do servidor.
 *
 * Implementada pelo aculta_portal (gerenciador de segredos). Credencial gravada aqui nunca vai ao banco nem à configuração.
 */
interface SecretWriterInterface {

  /**
   * Grava o valor de uma credencial do contrato. Nunca registrar o valor.
   *
   * @return array{ok: bool, message: string}
   *   Resultado e mensagem segura para exibir.
   */
  public function save(string $name, string $value): array;

}
