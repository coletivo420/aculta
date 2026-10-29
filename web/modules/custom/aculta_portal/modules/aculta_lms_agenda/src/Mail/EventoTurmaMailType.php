<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Email notice sent to group members when an event is published.
 *
 * Only declared variables are replaced; the context provides prepared text and
 * URLs.
 */
final class EventoTurmaMailType implements MailTypeInterface {

  /**
   * Identificador do tipo, em snake_case, igual à chave enviada ao Mailer.
   */
  public const ID = 'aculta_evento_turma';

  /**
   * Construtor.
   */
  public function __construct(
    private readonly TranslationInterface $traducao,
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
      'titulo' => [
        'label' => (string) $this->traducao->translate('Título do evento'),
        'sample' => 'Oficina de cerâmica',
      ],
      'turma' => [
        'label' => (string) $this->traducao->translate('Turma'),
        'sample' => 'Turma A',
      ],
      'url' => [
        'label' => (string) $this->traducao->translate('Endereço do evento'),
        'sample' => 'https://eventos.exemplo.invalid/eventos/oficina-de-ceramica',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return 'Novo evento na sua turma: {{ titulo }}';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return "Olá.\n\nFoi publicado um evento da turma {{ turma }}: {{ titulo }}.\n\nVeja as datas em: {{ url }}\n\nVocê recebe este aviso porque está matriculado nesta turma.";
  }

}
