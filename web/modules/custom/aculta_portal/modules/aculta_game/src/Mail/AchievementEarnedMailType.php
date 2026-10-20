<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Aviso de conquista por e-mail.
 */
final class AchievementEarnedMailType implements MailTypeInterface {

  public function __construct(
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'achievement_earned';
  }

  /**
   * {@inheritdoc}
   */
  public function variables(): array {
    return [
      'label' => ['label' => (string) $this->translation->translate('Nome da conquista'), 'sample' => 'Primeira leitura'],
      'url' => ['label' => (string) $this->translation->translate('Endereço das conquistas'), 'sample' => 'https://example.invalid/conta/atividade'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Você conquistou: {{ label }}');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    $lines = [
      (string) $this->translation->translate('Parabéns! Você recebeu a conquista “{{ label }}”.'),
      (string) $this->translation->translate('Veja suas conquistas e as opções de visibilidade na sua conta: {{ url }}'),
    ];
    return implode("\n\n", $lines);
  }

}
