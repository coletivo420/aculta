<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Transport;

/**
 * Fonte de credenciais dos transportes.
 *
 * Implementada pelo aculta_portal (gerenciador de segredos), para que este módulo não dependa dele.
 */
interface SecretProviderInterface {

  /**
   * Valor de uma credencial do contrato, ou NULL quando não configurada. Nunca registrar o retorno.
   */
  public function value(string $name): ?string;

}
