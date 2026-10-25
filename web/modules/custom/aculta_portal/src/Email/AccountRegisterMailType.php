<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

/**
 * Cadastro concluído (user.register_no_approval_required), texto aprovado em 2026-10-20.
 */
final class AccountRegisterMailType extends AccountMailTypeBase {

  public const ID = 'account_register';

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
    return (string) $this->translation->translate('Sua conta em {{ site }} está pronta');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return (string) $this->translation->translate("{{ name }},\n\nSeu cadastro em {{ site }} foi concluído. Para entrar pela primeira vez e definir sua senha, clique no link abaixo ou copie e cole no navegador. O link pode ser usado uma vez e vale por 24 horas.\n\n{{ one_time_url }}\n\nDepois de definir a senha, você pode entrar em {{ login_url }} com seu usuário ({{ username }}) ou com seu e-mail.\n\n— Equipe {{ site }}");
  }

}
