<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Privacy-minimal email for friendship requests and acceptances. */
final class FriendshipNoticeMailType implements MailTypeInterface {

  public const ID = 'friendship_notice';

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
        'label' => (string) $this->translation->translate('Aviso de amizade'),
        'sample' => 'Você recebeu um pedido de amizade.',
      ],
      'url' => [
        'label' => (string) $this->translation->translate('Link para abrir sua conta'),
        'sample' => 'https://aculta.org/minha-conta',
      ],
    ];
  }

  /**
   * {@inheritdoc} */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Aviso de amizade na ACULTA');
  }

  /**
   * {@inheritdoc} */
  public function defaultBody(): string {
    return "{{ summary }}\n\n" . (string) $this->translation->translate('Acesse sua conta para continuar: {{ url }}');
  }

}
