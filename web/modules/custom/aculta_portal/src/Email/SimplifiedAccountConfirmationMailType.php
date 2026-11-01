<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Confirmação de e-mail do cadastro simplificado (conta criada ao concluir uma compra).
 *
 * Diferente do pedido de confirmação de troca de e-mail: o texto diz que a confirmação leva à criação da senha.
 * `confirm_url` é o link de resposta com o hash da confirmação: é segredo, por isso não entra em logs (ver aculta_mailer).
 */
final class SimplifiedAccountConfirmationMailType implements MailTypeInterface {

  public const ID = 'simplified_account_confirmation';

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
      'email' => [
        'label' => (string) $this->translation->translate('E-mail da conta'),
        'sample' => 'pessoa@example.invalid',
      ],
      'confirm_url' => [
        'label' => (string) $this->translation->translate('Link de resposta (segredo)'),
        'sample' => 'https://example.invalid/aculta-mail-confirmer/responder/exemplo/hash',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Confirme seu e-mail e crie sua senha');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return (string) $this->translation->translate("Recebemos uma compra na Associação Cultural Antiproibicionista feita com o e-mail {{ email }}. Uma conta foi criada para você.\n\nPara concluir, confirme o endereço clicando no link abaixo ou copiando e colando no navegador. Depois da confirmação, você cria sua senha. O link pode ser usado uma vez e vale por 24 horas.\n\n{{ confirm_url }}\n\nSe você não fez essa compra, ignore esta mensagem: nada mais será feito sem a sua confirmação.\n\n— Equipe Associação Cultural Antiproibicionista");
  }

}
