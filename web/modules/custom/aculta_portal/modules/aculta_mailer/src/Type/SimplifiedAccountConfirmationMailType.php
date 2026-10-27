<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Type;

use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Confirmação da conta simplificada criada ao concluir o checkout (loja, LMS e doação).
 *
 * Depois que a pessoa confirma o e-mail pelo link, o site a convida a criar uma senha. O link é um segredo: não aparece
 * em log nem em texto de alerta.
 */
final class SimplifiedAccountConfirmationMailType implements MailTypeInterface {

  public const ID = 'account_simplified_confirmation';

  /**
   * Cria o tipo.
   */
  public function __construct(
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return self::ID;
  }

  /**
   * {@inheritdoc}
   */
  public function variables(): array {
    return [
      'nome' => [
        'label' => (string) $this->translation->translate('Nome'),
        'sample' => 'Maria',
      ],
      'email' => [
        'label' => (string) $this->translation->translate('E-mail da conta'),
        'sample' => 'pessoa@example.invalid',
      ],
      'confirm_url' => [
        'label' => (string) $this->translation->translate('Link de confirmação (segredo)'),
        'sample' => 'https://example.invalid/confirmar-conta/exemplo/hash',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Confirme sua conta na Associação Cultural Antiproibicionista');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return (string) $this->translation->translate("Olá, {{ nome }}.\n\nVocê concluiu uma compra na Associação Cultural Antiproibicionista e criamos uma conta simplificada com o e-mail {{ email }}.\n\nPara confirmar que o e-mail é seu, acesse o link abaixo:\n\n{{ confirm_url }}\n\nDepois de confirmar, você receberá o convite para criar uma senha e acessar sua conta.\n\nSe você não fez essa compra, ignore esta mensagem. Nenhuma senha foi criada e a conta não será usada sem a sua confirmação.");
  }

}
