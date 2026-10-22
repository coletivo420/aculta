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
 * Emite ForumActivityEvent para criação de tópico, comentário ou post no Fórum.
 *
 * Só criação: edição, movimentação e exclusão não emitem evento. Comentários
 * de outros contextos (publicações, LMS, Wiki) são ignorados.
 */
final class ForumActivityEventHooks {

  private const TOPIC_BUNDLE = 'aculta_forum_topic';

  public function __construct(
    private readonly EventDispatcherInterface $dispatcher,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Tópico novo do Fórum.
   */
  #[Hook('node_insert')]
  public function topicCreated(NodeInterface $node): void {
    if ($node->bundle() !== self::TOPIC_BUNDLE || $this->isSynchronizing($node)) {
      return;
    }
    $this->dispatcher->dispatch(
      new ForumActivityEvent((int) $node->id(), $this->time->getRequestTime()),
      ForumActivityEvent::TOPIC_CREATED,
    );
  }

  /**
   * Comentário novo num tópico do Fórum.
   */
  #[Hook('comment_insert')]
  public function commentCreated(CommentInterface $comment): void {
    if ($this->isSynchronizing($comment)) {
      return;
    }
    $entity = $comment->getCommentedEntity();
    if (!$entity instanceof NodeInterface || $entity->bundle() !== self::TOPIC_BUNDLE) {
      return;
    }
    $this->dispatcher->dispatch(
      new ForumActivityEvent((int) $comment->id(), $this->time->getRequestTime()),
      ForumActivityEvent::COMMENT_CREATED,
    );
  }

  /**
   * Post revisionável criado no Fórum.
   *
   * Conteúdo sincronizado/importado não representa atividade nova de membro e
   * não deve alimentar as métricas públicas de criação.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if (!$entity instanceof ForumPost || $this->isSynchronizing($entity)) {
      return;
    }

    $this->dispatcher->dispatch(
      new ForumActivityEvent((int) $entity->id(), $this->time->getRequestTime()),
      ForumActivityEvent::POST_CREATED,
    );
  }

  /**
   * Indicates entities written by synchronization or migration.
   */
  private function isSynchronizing(EntityInterface $entity): bool {
    return $entity instanceof SynchronizableInterface && $entity->isSyncing();
  }

}
