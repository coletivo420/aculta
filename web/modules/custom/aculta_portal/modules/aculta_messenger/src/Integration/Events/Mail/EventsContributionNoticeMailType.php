<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Events\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Privacy-minimal mail template for a proposal outcome. */
final class EventsContributionNoticeMailType implements MailTypeInterface {

  public const ID = 'events_contribution_personal_notice';

  public function __construct(private readonly TranslationInterface $translation) {}

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
        'label' => (string) $this->translation->translate('Resumo do aviso de Eventos'),
        'sample' => 'Há uma atualização sobre uma proposta sua de Eventos no ACULTA.',
      ],
      'url' => [
        'label' => (string) $this->translation->translate('Link para abrir o aviso'),
        'sample' => 'https://example.com/culturas',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Aviso sobre sua proposta de Eventos');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return "{{ summary }}\n\n" . (string) $this->translation->translate('Acesse o aviso: {{ url }}');
  }

}
