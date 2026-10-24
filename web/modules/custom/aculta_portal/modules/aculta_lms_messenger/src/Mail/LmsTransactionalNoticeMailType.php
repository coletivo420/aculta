<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_messenger\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Transactional LMS notice rendered and delivered by ACULTA Mailer.
 */
final class LmsTransactionalNoticeMailType implements MailTypeInterface {

  public const ID = 'lms_transactional_notice';

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
      'summary' => [
        'label' => (string) $this->translation->translate('Resumo do aviso educacional'),
        'sample' => 'Uma nova atualização está disponível no seu curso.',
      ],
      'url' => [
        'label' => (string) $this->translation->translate('Link para abrir o aviso'),
        'sample' => 'https://aculta.org/minha-conta/avisos',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Aviso educacional do ACULTA');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return implode("\n\n", [
      '{{ summary }}',
      (string) $this->translation->translate('Acesse o aviso: {{ url }}'),
    ]);
  }

}
