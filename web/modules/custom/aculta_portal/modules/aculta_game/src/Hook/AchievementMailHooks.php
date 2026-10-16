<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/** Plain-text email copy for achievement notices. */
final class AchievementMailHooks {

  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    if ($key !== 'achievement_earned') {
      return;
    }
    $message['subject'] = (string) new TranslatableMarkup('Você conquistou: @achievement', ['@achievement' => $params['label']]);
    $message['body'][] = (string) new TranslatableMarkup('Parabéns! Você recebeu a conquista “@achievement”.', ['@achievement' => $params['label']]);
    $message['body'][] = (string) new TranslatableMarkup('Veja suas conquistas e as opções de visibilidade na sua conta: @url', ['@url' => $params['url']]);
  }

}
