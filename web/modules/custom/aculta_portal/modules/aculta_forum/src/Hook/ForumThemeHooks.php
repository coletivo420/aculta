<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registro de templates do fórum como tema.
 */
class ForumThemeHooks {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Definições de tema para o fórum e discussões.
   */
  #[Hook('theme')]
  public function theme(mixed $existing, string $type, string $theme, string $path): array {
    return [
      'discussion_reply_composer' => [
        'variables' => [
          'status' => 'fechado',
          'mode' => 'responder',
          'target_id' => '',
          'parent_id' => NULL,
          'parent_author' => NULL,
          'parent_subject' => NULL,
          'host_title' => NULL,
          'messages' => NULL,
          'form' => NULL,
          'component' => NULL,
        ],
        'path' => $path . '/templates',
        'template' => 'discussion-reply-composer',
      ],
    ];
  }

}
