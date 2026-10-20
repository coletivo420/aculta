<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

/**
 * Reset de senha (user.password_reset), texto aprovado em 2026-10-20.
 */
final class AccountPasswordResetMailType extends AccountMailTypeBase {

  public const ID = 'account_password_reset';

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return self::ID;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Redefinição de senha em {{ site }}');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return (string) $this->translation->translate("{{ name }},\n\nRecebemos um pedido para redefinir a senha da sua conta em {{ site }}.\n\nPara criar uma nova senha, clique no link abaixo ou copie e cole no navegador. O link pode ser usado uma vez e vale por 24 horas.\n\n{{ one_time_url }}\n\nSe não foi você quem pediu, ignore esta mensagem: sua senha não será alterada.\n\n— Equipe {{ site }}");
  }

}
