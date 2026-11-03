<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_wiki\Hook\WikiReviewHooks;
use Drupal\aculta_wiki\Service\WikiEditorialEventQueue;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * A transição para "Em revisão" só enfileira o fato editorial.
 *
 * O e-mail editorial e os avisos pessoais pertencem ao Messenger e são
 * cobertos pelos testes do próprio Messenger.
 */
#[CoversClass(WikiReviewHooks::class)]
#[Group('aculta_wiki')]
final class WikiReviewHooksTest extends UnitTestCase {

  private const UUID = '11111111-1111-4111-8111-111111111111';

  /**
   * Event payloads added to the reliable queue fixture.
   *
   * @var list<array<string, mixed>>
   */
  private array $events = [];

  /**
   * Passar de "Rascunho" para "Em revisão" enfileira um evento.
   */
  public function testDraftToReviewQueuesEvent(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', 'review', $this->node('wiki_entry', 'draft')));

    $this->assertCount(1, $this->events);
    $this->assertSame(10, $this->events[0]['source_revision_id']);
  }

  /**
   * A verbete inserted directly into review also produces an event. */
  public function testInsertInReviewQueuesEvent(): void {
    $this->hooks()->entityInsert($this->node('wiki_entry', 'review'));

    $this->assertCount(1, $this->events);
  }

  /**
   * Non-default revisions do not open an editorial queue item. */
  public function testNonDefaultReviewRevisionDoesNotQueue(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', 'review', $this->node('wiki_entry', 'draft'), FALSE));

    $this->assertSame([], $this->events);
  }

  /**
   * Salvar um verbete que já estava em revisão não enfileira de novo.
   */
  public function testReviewToReviewDoesNotQueue(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', 'review', $this->node('wiki_entry', 'review')));

    $this->assertSame([], $this->events);
  }

  /**
   * Voltar de "Publicado" para "Em revisão" enfileira.
   */
  public function testPublishedToReviewQueuesEvent(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', 'review', $this->node('wiki_entry', 'published')));

    $this->assertCount(1, $this->events);
  }

  /**
   * Atualizar um verbete fora de revisão não enfileira.
   */
  public function testOtherStatesDoNotQueue(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', 'published', $this->node('wiki_entry', 'draft')));

    $this->assertSame([], $this->events);
  }

  /**
   * Outro tipo de conteúdo não enfileira, mesmo em revisão.
   */
  public function testOtherBundleDoesNotQueue(): void {
    $this->hooks()->entityUpdate($this->node('page', 'review', $this->node('page', 'draft')));

    $this->assertSame([], $this->events);
  }

  /**
   * Entidade que não é nó não enfileira.
   */
  public function testNonNodeEntityDoesNotQueue(): void {
    $this->hooks()->entityUpdate($this->createMock(EntityInterface::class));

    $this->assertSame([], $this->events);
  }

  /**
   * Nó sem o campo de moderação não enfileira e não quebra.
   */
  public function testNodeWithoutModerationFieldDoesNotQueue(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', NULL));

    $this->assertSame([], $this->events);
  }

  /**
   * Hook ligado à fila editorial real do módulo.
   */
  private function hooks(): WikiReviewHooks {
    $queue = $this->createMock(QueueInterface::class);
    $queue->method('createItem')->willReturnCallback(function (array $payload): int {
      $this->events[] = $payload;
      return count($this->events);
    });
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->with('aculta_wiki_editorial_events', TRUE)->willReturn($queue);
    return new WikiReviewHooks(new WikiEditorialEventQueue($queueFactory, $this->createMock(Connection::class)));
  }

  /**
   * Nó com tipo, estado editorial e original.
   */
  private function node(string $bundle, ?string $state, ?NodeInterface $original = NULL, bool $defaultRevision = TRUE): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('hasField')->willReturnCallback(static fn (string $name): bool => $name === 'moderation_state' && $state !== NULL);
    if ($state !== NULL) {
      $list = $this->createMock(FieldItemListInterface::class);
      $list->method('isEmpty')->willReturn(FALSE);
      $list->method('__get')->willReturnCallback(static fn (string $name): mixed => $name === 'value' ? $state : NULL);
      $node->method('get')->with('moderation_state')->willReturn($list);
    }
    $node->method('getOriginal')->willReturn($original);
    $node->method('isDefaultRevision')->willReturn($defaultRevision);
    $node->method('getRevisionId')->willReturn(10);
    $node->method('uuid')->willReturn(self::UUID);
    $node->method('label')->willReturn('Cannabis medicinal');
    $node->method('id')->willReturn(42);
    return $node;
  }

}
