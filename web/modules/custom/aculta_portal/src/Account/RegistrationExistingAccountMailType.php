<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Aviso a quem já tem conta quando alguém tenta se cadastrar com o mesmo e-mail (DT-P06).
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
  public function variables(): array {
    return [
      'login_url' => ['label' => (string) $this->translation->translate('Endereço de login'), 'sample' => 'https://example.invalid/user/login'],
      'recovery_url' => ['label' => (string) $this->translation->translate('Endereço para recuperar a senha'), 'sample' => 'https://example.invalid/user/password'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Tentativa de cadastro com o seu e-mail');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return (string) $this->translation->translate("Olá,\n\nAlguém tentou criar uma conta na Associação Cultural Antiproibicionista usando este endereço de e-mail. Ele já está cadastrado, por isso nenhuma conta nova foi criada.\n\nSe foi você, acesse a sua conta:\n{{ login_url }}\n\nSe você esqueceu a senha, recupere-a:\n{{ recovery_url }}\n\nSe não foi você, não é preciso fazer nada. Sua conta continua como está.\n\nAssociação Cultural Antiproibicionista");
  }

}
