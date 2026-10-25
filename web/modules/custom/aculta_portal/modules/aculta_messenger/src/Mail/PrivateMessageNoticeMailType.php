<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Mail template for a privacy-minimal private-message notice. */
final class PrivateMessageNoticeMailType implements MailTypeInterface {

  public const ID = 'private_message_notice';

  public function __construct(
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * {@inheritdoc} */
  public function id(): string {
    return self::ID;
  }

  /**
   * {@inheritdoc} */
  public function variables(): array {
    return [
      'summary' => [
        'label' => (string) $this->translation->translate('Aviso de mensagem privada'),
        'sample' => 'Você tem uma nova mensagem de outra pessoa.',
      ],
      'url' => [
        'label' => (string) $this->translation->translate('Link para abrir a caixa de mensagens'),
        'sample' => 'https://aculta.org/minha-conta/mensagens',
      ],
    ];
  }

  /**
   * {@inheritdoc} */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Aviso de nova mensagem privada na ACULTA');
  }

  /**
   * {@inheritdoc} */
  public function defaultBody(): string {
    return "{{ summary }}\n\n" . (string) $this->translation->translate('Acesse sua conta para ler e responder: {{ url }}');
  }

}
