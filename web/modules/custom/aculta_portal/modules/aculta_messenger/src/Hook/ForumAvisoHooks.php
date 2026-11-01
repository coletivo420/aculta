<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\comment\CommentInterface;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_messenger\Aviso\ForumNoticeEventProducerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Respostas em tópico seguido geram aviso no site (ACULTA Fórum, F5.2).
 */
final class ForumAvisoHooks {

  public function __construct(
    private readonly ForumNoticeEventProducerInterface $events,
  ) {}

  /**
   * Implements hook_comment_insert().
   */
  #[Hook('comment_insert')]
  public function commentInsert(CommentInterface $comment): void {
    $this->events->publishComment($comment);
  }

  /**
   * Implements hook_comment_update().
   *
   * Comentário aprovado depois da criação conta na aprovação.
   *
   * A deduplicação por identificador impede contagem dupla se a atualização
   * se repetir.
   */
  #[Hook('comment_update')]
  public function commentUpdate(CommentInterface $comment): void {
    $original = $comment->getOriginal();
    if ($comment->isPublished() && $original !== NULL
      && !$original->isPublished()) {
      $this->events->publishComment($comment);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for revisionable Forum posts.
   */
  #[Hook('aculta_forum_post_insert')]
  public function postInsert(ForumPost $post): void {
    $this->events->publishPost($post);
  }

  /**
   * Implements hook_ENTITY_TYPE_update() when moderation first publishes a post.
   */
  #[Hook('aculta_forum_post_update')]
  public function postUpdate(ForumPost $post): void {
    $original = $post->getOriginal();
    if ($post->isPublished()
      && $original instanceof ForumPost
      && !$original->isPublished()
      && $original->hasField('first_published')
      && $original->get('first_published')->isEmpty()
      && $post->hasField('first_published')
      && !$post->get('first_published')->isEmpty()) {
      $this->events->publishPost($post);
    }
  }

}
