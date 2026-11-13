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
        ],
        'path' => $path . '/templates',
        'template' => 'discussion-reply-composer',
      ],
      'discussion_reaction_bar' => [
        'variables' => [
          'target_id' => '',
          'entity_type' => '',
          'entity_id' => '',
          'reactions' => [],
          'total_count' => 0,
          'user_reaction' => NULL,
          'allowed_reactions' => [],
          'default_reaction' => 'broccoli',
          'can_react' => FALSE,
          'is_own_content' => FALSE,
          'react_url' => '',
          'picker_url' => NULL,
          'csrf_token' => '',
        ],
        'path' => $path . '/templates',
        'template' => 'discussion-reaction-bar',
      ],
      'discussion_reaction_picker' => [
        'variables' => [
          'target_id' => '',
          'entity_type' => '',
          'entity_id' => '',
          'allowed_reactions' => [],
          'emoji_categories' => [],
          'user_reaction' => NULL,
          'can_react' => FALSE,
          'react_url' => '',
          'csrf_token' => '',
        ],
        'path' => $path . '/templates',
        'template' => 'discussion-reaction-picker',
      ],
    ];
  }

}
