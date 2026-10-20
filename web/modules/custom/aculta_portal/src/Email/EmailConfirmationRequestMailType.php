<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Pedido de confirmação de endereço de e-mail (F9). O texto é o mesmo que o contrib enviava.
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
        'sample' => 'https://example.invalid/email-confirmer/reply/exemplo/hash',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Request for confirmation of email address from Associação Cultural Antiproibicionista');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return (string) $this->translation->translate("We have received a request to confirm {{ email }} on the Associação Cultural Antiproibicionista website. Please, reply to this request using the link below.\n\n{{ confirm_url }}");
  }

}
