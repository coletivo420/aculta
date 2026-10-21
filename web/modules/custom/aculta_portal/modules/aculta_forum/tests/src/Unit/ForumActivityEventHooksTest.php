<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Event\ForumActivityEvent;
use Drupal\aculta_forum\Hook\ForumActivityEventHooks;
use Drupal\comment\CommentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Evento de atividade do Fórum: emitido só para tópico e comentário do Fórum, uma vez por criação.
 */
#[CoversClass(ForumActivityEventHooks::class)]
#[Group('aculta_forum')]
final class ForumActivityEventHooksTest extends TestCase {

  /**
   * Tópico do Fórum emite TOPIC_CREATED com o identificador do nó.
   */
  public function testTopicInsertDispatchesTopicCreated(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('id')->willReturn(42);

    $dispatcher = $this->dispatcherExpecting(ForumActivityEvent::TOPIC_CREATED, 42);
    (new ForumActivityEventHooks($dispatcher, $this->time()))->topicCreated($node);
  }

  /**
   * Nó de outro tipo não emite evento de tópico.
   */
  public function testOtherNodeDoesNotDispatch(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->never())->method('dispatch');
    (new ForumActivityEventHooks($dispatcher, $this->time()))->topicCreated($node);
  }

  /**
   * Comentário em tópico do Fórum emite COMMENT_CREATED.
   */
  public function testCommentOnForumTopicDispatchesCommentCreated(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn($topic);
    $comment->method('id')->willReturn(7);

    $dispatcher = $this->dispatcherExpecting(ForumActivityEvent::COMMENT_CREATED, 7);
    (new ForumActivityEventHooks($dispatcher, $this->time()))->commentCreated($comment);
  }

  /**
   * Comentário em outro contexto (publicação, LMS, Wiki) não é do Fórum e não emite evento.
   */
  public function testCommentOnOtherContentDoesNotDispatch(): void {
    $article = $this->createMock(NodeInterface::class);
    $article->method('bundle')->willReturn('article');
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn($article);

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->never())->method('dispatch');
    (new ForumActivityEventHooks($dispatcher, $this->time()))->commentCreated($comment);
  }

  /**
   * Dispatcher que espera um único evento com o nome e o identificador informados.
   */
  private function dispatcherExpecting(string $name, int $entityId): EventDispatcherInterface {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())->method('dispatch')
      ->with($this->callback(static fn (object $event): bool => $event instanceof ForumActivityEvent && $event->entityId === $entityId), $name)
      ->willReturnArgument(0);
    return $dispatcher;
  }

  /**
   * Relógio fixo para o instante do evento.
   */
  private function time(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    return $time;
  }

}
