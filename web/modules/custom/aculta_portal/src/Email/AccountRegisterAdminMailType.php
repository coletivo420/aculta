<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

/**
 * Conta criada pelo administrador (user.register_admin_created), texto aprovado em 2026-10-20.
 */
final class AccountRegisterAdminMailType extends AccountMailTypeBase {

  public const ID = 'account_register_admin';

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
    return (string) $this->translation->translate('Um administrador criou sua conta em {{ site }}');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return (string) $this->translation->translate("{{ name }},\n\nUm administrador do site {{ site }} criou uma conta para você. Para entrar pela primeira vez e definir sua senha, clique no link abaixo ou copie e cole no navegador. O link pode ser usado uma vez e vale por 24 horas.\n\n{{ one_time_url }}\n\nDepois de definir a senha, você pode entrar em {{ login_url }} com seu usuário ({{ username }}) ou com seu e-mail.\n\n— Equipe {{ site }}");
  }

}
