<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Donation\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Mailer template contract for privacy-minimal Donation notices. */
final class DonationNoticeMailType implements MailTypeInterface {

  public const ID = 'doacao_aviso_pessoal';

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
        'label' => (string) $this->translation->translate('Resumo do aviso de doação'),
        'sample' => 'O pagamento da sua doação foi aprovado.',
      ],
      'url' => [
        'label' => (string) $this->translation->translate('Link para abrir o aviso'),
        'sample' => 'https://aculta.org/avisos-mensagens/avisos',
      ],
    ];
  }

  /**
   * {@inheritdoc} */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Aviso sobre sua doação à ACULTA');
  }

  /**
   * {@inheritdoc} */
  public function defaultBody(): string {
    return "{{ summary }}\n\n" . (string) $this->translation->translate('Acesse o aviso: {{ url }}');
  }

}
