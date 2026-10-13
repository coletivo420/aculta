<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

/**
 * Onde o e-mail pode ser alterado.
 *
 * Regra do responsável: o formulário de troca de e-mail existe somente dentro de Minha Conta (ChangeMailForm, do
 * change_mail_page). A edição de conta do Core (administrador e conta própria) não oferece o campo de e-mail.
 * Criar conta, por administrador, continua oferecendo o campo.
 */
final class EmailChangePolicy {

  /**
   * Indica se o campo de e-mail aparece no formulário de conta do Core.
   *
   * @param bool $isNew
   *   Conta ainda não gravada (criação).
   * @param string $operation
   *   Operação do formulário de entidade ("default" para edição, "register" para criação).
   */
  public static function mailFieldAllowed(bool $isNew, string $operation): bool {
    return $isNew || $operation !== 'default';
  }

}
