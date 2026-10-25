<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Event\ForumActivityEvent;
use Drupal\comment\CommentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Emite ForumActivityEvent na primeira publicação de conteúdo do Fórum.
 *
 * Só a primeira publicação conta: edição posterior, movimentação, rascunho,
 * revisão não padrão e conteúdo sincronizado não emitem evento. Comentários de
 * outros contextos (publicações, LMS, Wiki) são ignorados.
 */
final class ForumActivityEventHooks {

  private const TOPIC_BUNDLE = 'aculta_forum_topic';

  public function __construct(
    private readonly EventDispatcherInterface $dispatcher,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Tópico novo do Fórum já publicado.
   */
  #[Hook('node_insert')]
  public function topicCreated(NodeInterface $node): void {
    if (!$this->isPublishedForumTopic($node)) {
      return;
    }
    $this->dispatchTopic($node);
  }

  /**
   * Tópico pendente que é publicado pela primeira vez.
   */
  #[Hook('node_update')]
  public function topicPublished(NodeInterface $node): void {
    if (!$this->isPublishedForumTopic($node)) {
      return;
    }
    $original = $node->getOriginal();
    if ($original instanceof NodeInterface && !$original->isPublished()) {
      $this->dispatchTopic($node);
    }
  }

  /**
   * Comentário novo já publicado num tópico do Fórum.
   */
  #[Hook('comment_insert')]
  public function commentCreated(CommentInterface $comment): void {
    if (!$this->isPublishedForumComment($comment)) {
      return;
    }
    $this->dispatchComment($comment);
  }

  /**
   * Comentário pendente ou rejeitado que é publicado pela primeira vez.
   */
  #[Hook('comment_update')]
  public function commentPublished(CommentInterface $comment): void {
    if (!$this->isPublishedForumComment($comment)) {
      return;
    }
    $original = $comment->getOriginal();
    if ($original !== NULL && !$original->isPublished()) {
      $this->dispatchComment($comment);
    }
  }

  /**
   * Post revisionável novo já publicado na revisão padrão.
   *
   * Conteúdo sincronizado/importado não representa atividade nova de membro e
   * não deve alimentar as métricas públicas de criação.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if (!$this->isPublishedForumPost($entity)) {
      return;
    }
    $this->dispatchPost($entity);
  }

  /**
   * Post pendente que é publicado pela primeira vez na revisão padrão.
   */
  #[Hook('entity_update')]
  public function entityPublished(EntityInterface $entity): void {
    if (!$this->isPublishedForumPost($entity)) {
      return;
    }
    $original = $entity->getOriginal();
    if ($original instanceof ForumPost && !$original->isPublished()) {
      $this->dispatchPost($entity);
    }
  }

  /**
   * Dispatches topic creation activity once.
   */
  private function dispatchTopic(NodeInterface $node): void {
    $this->dispatcher->dispatch(
      new ForumActivityEvent((int) $node->id(), $this->time->getRequestTime()),
      ForumActivityEvent::TOPIC_CREATED,
    );
  }

  /**
   * Dispatches comment creation activity once.
   */
  private function dispatchComment(CommentInterface $comment): void {
    $this->dispatcher->dispatch(
      new ForumActivityEvent((int) $comment->id(), $this->time->getRequestTime()),
      ForumActivityEvent::COMMENT_CREATED,
    );
  }

  /**
   * Dispatches post creation activity once.
   */
  private function dispatchPost(ForumPost $post): void {
    $this->dispatcher->dispatch(
      new ForumActivityEvent((int) $post->id(), $this->time->getRequestTime()),
      ForumActivityEvent::POST_CREATED,
    );
  }

  /**
   * Checks a published Forum topic that is not being synchronized.
   */
  private function isPublishedForumTopic(NodeInterface $node): bool {
    return $node->bundle() === self::TOPIC_BUNDLE
      && $node->isPublished()
      && !$this->isSynchronizing($node);
  }

  /**
   * Checks a published Comment on a Forum topic that is not synchronized.
   */
  private function isPublishedForumComment(CommentInterface $comment): bool {
    if (!$comment->isPublished() || $this->isSynchronizing($comment)) {
      return FALSE;
    }
    $entity = $comment->getCommentedEntity();
    return $entity instanceof NodeInterface && $entity->bundle() === self::TOPIC_BUNDLE;
  }

  /**
   * Checks a published default ForumPost revision that is not synchronized.
   */
  private function isPublishedForumPost(EntityInterface $entity): bool {
    return $entity instanceof ForumPost
      && $entity->isPublished()
      && $entity->isDefaultRevision()
      && !$this->isSynchronizing($entity);
  }

  /**
   * Indicates entities written by synchronization or migration.
   */
  private function isSynchronizing(EntityInterface $entity): bool {
    return $entity instanceof SynchronizableInterface && $entity->isSyncing();
  }

}
