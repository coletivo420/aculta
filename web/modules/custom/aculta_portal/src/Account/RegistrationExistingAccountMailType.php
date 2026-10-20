<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Aviso a quem já tem conta quando alguém tenta se cadastrar com o mesmo e-mail (DT-P06).
 *
 * Recebe `login_url` e `recovery_url` prontos no contexto: o tipo não consulta rotas.
 */
final class RegistrationExistingAccountMailType implements MailTypeInterface {

  public function __construct(
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return RegistrationMailPolicy::MAIL_KEY;
  }

  /**
   * {@inheritdoc}
   */
  public function subject(array $context): string {
    return (string) $this->translation->translate('Tentativa de cadastro com o seu e-mail');
  }

  /**
   * {@inheritdoc}
   */
  public function body(array $context): string {
    return (string) $this->translation->translate("Olá,\n\nAlguém tentou criar uma conta na Associação Cultural Antiproibicionista usando este endereço de e-mail. Ele já está cadastrado, por isso nenhuma conta nova foi criada.\n\nSe foi você, acesse a sua conta:\n@login\n\nSe você esqueceu a senha, recupere-a:\n@recovery\n\nSe não foi você, não é preciso fazer nada. Sua conta continua como está.\n\nAssociação Cultural Antiproibicionista", [
      '@login' => (string) ($context['login_url'] ?? ''),
      '@recovery' => (string) ($context['recovery_url'] ?? ''),
    ]);
  }

}
