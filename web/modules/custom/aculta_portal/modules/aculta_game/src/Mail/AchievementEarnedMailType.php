<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Aviso de conquista por e-mail. Recebe `label` e `url` prontos no contexto.
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
  public function subject(array $context): string {
    return (string) $this->translation->translate('Você conquistou: @achievement', [
      '@achievement' => (string) ($context['label'] ?? ''),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function body(array $context): string {
    $lines = [
      (string) $this->translation->translate('Parabéns! Você recebeu a conquista “@achievement”.', [
        '@achievement' => (string) ($context['label'] ?? ''),
      ]),
      (string) $this->translation->translate('Veja suas conquistas e as opções de visibilidade na sua conta: @url', [
        '@url' => (string) ($context['url'] ?? ''),
      ]),
    ];
    return implode("\n\n", $lines);
  }

}
