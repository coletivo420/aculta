<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Bloqueia as páginas antigas do fórum: criação de tópico por /node/add e edição/resposta de
 * comentário em página própria. Toda a interação do fórum acontece no popup (HTMX).
 */
final class ForumPopupOnlyAccess {

  /**
   * Nome do bundle de tópicos do fórum.
   */
  private const TOPIC_BUNDLE = 'aculta_forum_topic';

  /**
   * Nome do campo de discussão dos comentários do fórum.
   */
  private const DISCUSSION_FIELD = 'field_aculta_discussion';

  /**
   * Checa a rota: nega as páginas antigas e libera o restante.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch): AccessResultInterface {
    $name = $routeMatch->getRouteName();

    if ($name === 'node.add') {
      $type = $routeMatch->getParameter('node_type');
      $bundle = is_object($type) && method_exists($type, 'id') ? $type->id() : (string) $type;
      return AccessResult::forbiddenIf($bundle === self::TOPIC_BUNDLE)
        ->addCacheContexts(['route']);
    }

    if ($name === 'entity.comment.edit_form') {
      $comment = $routeMatch->getParameter('comment');
      return AccessResult::forbiddenIf(
        $comment instanceof CommentInterface && $comment->bundle() === 'aculta_discussion_post'
      )->addCacheContexts(['route']);
    }

    if ($name === 'comment.reply') {
      return AccessResult::forbiddenIf($routeMatch->getParameter('field_name') === self::DISCUSSION_FIELD)
        ->addCacheContexts(['route']);
    }

    return AccessResult::allowed();
  }

}
