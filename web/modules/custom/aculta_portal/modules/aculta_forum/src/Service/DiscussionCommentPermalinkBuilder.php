<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;

/**
 * Builds canonical permalink URLs pointing directly to the host entity.
 *
 * Ensures human-facing permalinks never target the retired standalone route
 * (/comment/{cid}, blocked by D-65) while preserving the host's route,
 * language, pagination query, and DOM anchor.
 */
final class DiscussionCommentPermalinkBuilder implements DiscussionCommentPermalinkBuilderInterface {

  public function __construct(
    private readonly PagerManagerInterface $pagerManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Página (0 = primeira) do comentário dentro da lista do campo, pela posição na thread.
   */
  private function commentPage(CommentInterface $comment, EntityInterface $host): int {
    $perPage = (int) $host->getFieldDefinition($comment->getFieldName())->getSetting('per_page');
    if ($perPage <= 0) {
      return 0;
    }
    $position = (int) $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', $host->getEntityTypeId())
      ->condition('entity_id', $host->id())
      ->condition('field_name', $comment->getFieldName())
      ->condition('status', CommentInterface::PUBLISHED)
      ->condition('thread', $comment->get('thread')->value, '<')
      ->count()
      ->execute();
    return intdiv($position, $perPage);
  }

  /**
   * Builds the permalink URL to the commented host entity.
   */
  public function build(CommentInterface $comment): ?Url {
    if ($comment->getCommentedEntity() === NULL) {
      return NULL;
    }
    // Link público do comentário: /resposta/{cid}, que redireciona para o tópico na página certa.
    return Url::fromRoute('aculta_forum.comment_reply_permalink', ['comment' => $comment->id()]);
  }

  /**
   * URL do tópico com a página da paginação e a âncora do comentário (destino do redirecionamento).
   */
  public function hostUrl(CommentInterface $comment): ?Url {
    $host = $comment->getCommentedEntity();
    if (!$host instanceof EntityInterface) {
      return NULL;
    }

    $url = $host->toUrl('canonical');
    $options = $url->getOptions();
    $options['fragment'] = 'comment-' . $comment->id();

    // A página do comentário vem da posição dele na lista; a página da requisição atual não serve
    // (um envio por POST sempre chega à página 0).
    $page = $this->commentPage($comment, $host);
    if ($page > 0) {
      $options['query'] = array_merge($options['query'] ?? [], ['page' => (string) $page]);
    }

    $url->setOptions($options);
    return $url;
  }

}
