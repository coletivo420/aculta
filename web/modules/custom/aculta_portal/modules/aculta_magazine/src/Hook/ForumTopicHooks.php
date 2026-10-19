<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\comment\CommentInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Keeps a small compatibility adjustment for legacy Forum comment links.
 */
final class ForumTopicHooks {

  /**
   * Implements hook_comment_links_alter().
   *
   * Nos tópicos do Fórum, o Core repete a solicitação de login em cada
   * comentário e na área do tópico. Fica só a da área do tópico, que é a
   * resposta principal.
   */
  #[Hook('comment_links_alter')]
  public function commentLinksAlter(array &$links, CommentInterface $entity, array &$context): void {
    // CommentLazyBuilders::renderLinks places links in this array.
    if (!isset($links['comment']['#links']['comment-forbidden'])) {
      return;
    }
    $commented = $entity->getCommentedEntity();
    if ($commented instanceof NodeInterface && $commented->bundle() === 'forum') {
      unset($links['comment']['#links']['comment-forbidden']);
    }
  }

}
