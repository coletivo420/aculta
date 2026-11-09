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
      'views_view_unformatted__aculta_forum_categories__page_categories' => [
        'template' => 'views-view-unformatted--aculta-forum-categories--page-categories',
        'path' => $path . '/templates',
        'base hook' => 'views_view_unformatted',
      ],
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
        ],
        'path' => $path . '/templates',
        'template' => 'discussion-reply-composer',
      ],
    ];
  }

}
