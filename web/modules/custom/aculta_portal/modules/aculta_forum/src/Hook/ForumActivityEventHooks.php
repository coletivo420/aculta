<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Event\ForumActivityEvent;
use Drupal\comment\CommentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Emite ForumActivityEvent quando um tópico ou um comentário do Fórum é criado.
 *
 * Só criação: edição, movimentação e exclusão não emitem evento. Comentários de outros contextos
 * (publicações, LMS, Wiki) não são do Fórum e são ignorados.
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
    if ($node->bundle() !== self::TOPIC_BUNDLE) {
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
    $entity = $comment->getCommentedEntity();
    if (!$entity instanceof NodeInterface || $entity->bundle() !== self::TOPIC_BUNDLE) {
      return;
    }
    $this->dispatcher->dispatch(
      new ForumActivityEvent((int) $comment->id(), $this->time->getRequestTime()),
      ForumActivityEvent::COMMENT_CREATED,
    );
  }

}
