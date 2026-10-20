<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Pedido de confirmação de endereço de e-mail (F9). Texto aprovado em 2026-10-20 (antes, o texto do contrib).
 *
 * `confirm_url` é o link de resposta com o hash da confirmação: é segredo, por isso não entra em logs (ver aculta_mailer).
 */
final class EmailConfirmationRequestMailType implements MailTypeInterface {

  public const ID = 'email_confirmation_request';

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
        'label' => (string) $this->translation->translate('Endereço a confirmar'),
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
    return (string) $this->translation->translate('Confirme seu novo endereço de e-mail na Associação Cultural Antiproibicionista');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return (string) $this->translation->translate("Recebemos um pedido para usar {{ email }} como e-mail da sua conta na Associação Cultural Antiproibicionista.\n\nSe foi você, confirme clicando no link abaixo ou copiando e colando no navegador. O link pode ser usado uma vez e vale por 24 horas.\n\n{{ confirm_url }}\n\nSe não foi você, ignore esta mensagem: nada será alterado.\n\n— Equipe Associação Cultural Antiproibicionista");
  }

}
