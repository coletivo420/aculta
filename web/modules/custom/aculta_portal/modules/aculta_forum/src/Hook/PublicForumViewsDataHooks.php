<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Adds the Forum-only Views sort backed by Core comment statistics.
 */
final class PublicForumViewsDataHooks {

  /**
   * Implements hook_views_data_alter().
   *
   * The Core last_updated sort handler stores the void result of
   * Sql::addOrderBy() in a typed string property. Expose a narrowly named
   * virtual sort field so the public Forum View can use its safe equivalent
   * without replacing Core's handler for unrelated Views.
   *
   * @param array<string, mixed> $data
   *   Views data definitions.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $data['comment_entity_statistics']['aculta_forum_last_activity'] = [
      'title' => 'ACULTA Forum: última atividade',
      'help' => 'Ordena por atividade mais recente entre a atualização do tópico e sua última resposta publicada.',
      'sort' => [
        'id' => 'aculta_forum_last_activity',
        'no group by' => TRUE,
      ],
    ];
    $data['comment_entity_statistics']['aculta_forum_reply_count'] = [
      'title' => 'ACULTA Forum: respostas publicadas',
      'help' => 'Conta Comments e ForumPosts publicados no tópico.',
      'field' => [
        'id' => 'aculta_forum_reply_count',
        'no group by' => TRUE,
      ],
      'sort' => [
        'id' => 'aculta_forum_reply_count',
        'no group by' => TRUE,
      ],
    ];
    $data['comment_entity_statistics']['aculta_forum_no_replies'] = [
      'title' => 'ACULTA Forum: sem respostas públicas',
      'help' => 'Filtra tópicos sem Comments ou ForumPosts publicados.',
      'filter' => [
        'id' => 'aculta_forum_no_replies',
      ],
    ];
    $data['history']['aculta_forum_unread_content'] = [
      'title' => 'ACULTA Forum: conteúdo não lido',
      'help' => 'Filtra tópicos com atualização, Comment ou ForumPost ainda não lido.',
      'filter' => [
        'id' => 'aculta_forum_unread_content',
      ],
    ];
    $data['node']['aculta_forum_unread_posts'] = [
      'title' => 'ACULTA Forum: publicações não lidas',
      'help' => 'Exibe a contagem e o link para o primeiro ForumPost não lido.',
      'field' => [
        'id' => 'aculta_forum_unread_posts',
        'no group by' => TRUE,
      ],
    ];
  }

}
