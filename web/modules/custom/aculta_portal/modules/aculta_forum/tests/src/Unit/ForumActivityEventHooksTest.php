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
 * Evento de atividade do Fórum: um evento por tópico ou comentário criado.
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
    $node->method('isPublished')->willReturn(TRUE);
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
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('id')->willReturn(7);

    $dispatcher = $this->dispatcherExpecting(ForumActivityEvent::COMMENT_CREATED, 7);
    (new ForumActivityEventHooks($dispatcher, $this->time()))->commentCreated($comment);
  }

  /**
   * Comentários de publicação, LMS ou Wiki não emitem evento do Fórum.
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
   * Tópico não publicado não gera atividade pública.
   */
  public function testUnpublishedTopicDoesNotDispatch(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('isPublished')->willReturn(FALSE);

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->never())->method('dispatch');
    (new ForumActivityEventHooks($dispatcher, $this->time()))->topicCreated($node);
  }

  /**
   * Tópico pendente publicado depois conta uma única vez.
   */
  public function testPendingTopicCountsOnFirstPublication(): void {
    $original = $this->createMock(NodeInterface::class);
    $original->method('isPublished')->willReturn(FALSE);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('id')->willReturn(43);
    $node->method('getOriginal')->willReturn($original);

    $dispatcher = $this->dispatcherExpecting(ForumActivityEvent::TOPIC_CREATED, 43);
    (new ForumActivityEventHooks($dispatcher, $this->time()))->topicPublished($node);
  }

  /**
   * Editar tópico já publicado não repete a atividade.
   */
  public function testPublishedTopicEditDoesNotDispatch(): void {
    $original = $this->createMock(NodeInterface::class);
    $original->method('isPublished')->willReturn(TRUE);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getOriginal')->willReturn($original);

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->never())->method('dispatch');
    (new ForumActivityEventHooks($dispatcher, $this->time()))->topicPublished($node);
  }

  /**
   * Comentário pendente não gera atividade até ser publicado.
   */
  public function testPendingCommentDoesNotDispatch(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('isPublished')->willReturn(FALSE);
    $comment->method('getCommentedEntity')->willReturn($topic);

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->never())->method('dispatch');
    (new ForumActivityEventHooks($dispatcher, $this->time()))->commentCreated($comment);
  }

  /**
   * Comentário pendente aprovado conta uma vez.
   */
  public function testPendingCommentCountsOnApproval(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $original = $this->createMock(CommentInterface::class);
    $original->method('isPublished')->willReturn(FALSE);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('getCommentedEntity')->willReturn($topic);
    $comment->method('getOriginal')->willReturn($original);
    $comment->method('id')->willReturn(8);

    $dispatcher = $this->dispatcherExpecting(ForumActivityEvent::COMMENT_CREATED, 8);
    (new ForumActivityEventHooks($dispatcher, $this->time()))->commentPublished($comment);
  }

  /**
   * Editar comentário já publicado não repete a atividade.
   */
  public function testPublishedCommentEditDoesNotDispatch(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $original = $this->createMock(CommentInterface::class);
    $original->method('isPublished')->willReturn(TRUE);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('getCommentedEntity')->willReturn($topic);
    $comment->method('getOriginal')->willReturn($original);

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->never())->method('dispatch');
    (new ForumActivityEventHooks($dispatcher, $this->time()))->commentPublished($comment);
  }

  /**
   * Dispatcher que espera o nome e o ID informados em um único evento.
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
