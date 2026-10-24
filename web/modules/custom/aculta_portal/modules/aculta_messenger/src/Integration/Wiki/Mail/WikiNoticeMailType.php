<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Wiki\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Mail template for a privacy-minimal Wiki notice. */
final class WikiNoticeMailType implements MailTypeInterface {

  public const ID = 'wiki_personal_notice';

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
        'label' => (string) $this->translation->translate('Resumo do aviso da Wiki'),
        'sample' => 'Há uma atualização editorial disponível.',
      ],
      'url' => [
        'label' => (string) $this->translation->translate('Link para abrir o aviso'),
        'sample' => 'https://aculta.org/minha-conta/avisos',
      ],
    ];
  }

  /**
   * {@inheritdoc} */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Aviso da Wiki ACULTA');
  }

  /**
   * {@inheritdoc} */
  public function defaultBody(): string {
    return "{{ summary }}\n\n" . (string) $this->translation->translate('Acesse o aviso: {{ url }}');
  }

}
