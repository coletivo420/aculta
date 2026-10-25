<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Mail template for a privacy-minimal direct Forum notice. */
final class ForumNoticeMailType implements MailTypeInterface {

  public const ID = 'forum_direct_notice';

  public function __construct(private readonly TranslationInterface $translation) {}

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
        'label' => (string) $this->translation->translate('Resumo do aviso do Fórum'),
        'sample' => 'Há uma novidade em um tópico que você segue.',
      ],
      'url' => [
        'label' => (string) $this->translation->translate('Link para abrir o tópico'),
        'sample' => 'https://aculta.org/forum',
      ],
    ];
  }

  /**
   * {@inheritdoc} */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Aviso do ACULTA Forum');
  }

  /**
   * {@inheritdoc} */
  public function defaultBody(): string {
    return "{{ summary }}\n\n" . (string) $this->translation->translate('Acesse o tópico: {{ url }}');
  }

}
