<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_wiki\Hook\WikiReviewHooks;
use Drupal\aculta_wiki\Notifier\WikiReviewNotifier;
use Drupal\aculta_wiki\Service\WikiEditorialEventQueue;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Transição para "Em revisão" avisa uma vez; o resto não dispara nada.
 */
#[CoversClass(WikiReviewHooks::class)]
#[Group('aculta_wiki')]
final class WikiReviewHooksTest extends UnitTestCase {

  private const UUID = '11111111-1111-4111-8111-111111111111';

  /**
   * Fila em memória usada pelo notificador.
   */
  private object $queue;

  /**
   * Event payloads added to the reliable queue fixture.
   *
   * @var list<array<string, mixed>>
   */
  private array $events = [];

  /**
   * Passar de "Rascunho" para "Em revisão" avisa.
   */
  public function testDraftToReviewNotifies(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', 'review', $this->node('wiki_entry', 'draft')));

    $this->assertCount(1, $this->queue->items);
    $this->assertCount(1, $this->events);
    $this->assertSame(10, $this->events[0]['source_revision_id']);
  }

  /**
   * A verbete inserted directly into review also produces an event. */
  public function testInsertInReviewQueuesEvent(): void {
    $this->hooks()->entityInsert($this->node('wiki_entry', 'review'));

    $this->assertCount(1, $this->events);
    $this->assertCount(1, $this->queue->items);
  }

  /**
   * Non-default revisions do not open an editorial queue item. */
  public function testNonDefaultReviewRevisionDoesNotQueue(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', 'review', $this->node('wiki_entry', 'draft'), FALSE));

    $this->assertSame([], $this->events);
    $this->assertSame([], $this->queue->items);
  }

  /**
   * Salvar um verbete que já estava em revisão não avisa de novo.
   */
  public function testReviewToReviewDoesNotNotify(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', 'review', $this->node('wiki_entry', 'review')));

    $this->assertSame([], $this->queue->items);
    $this->assertSame([], $this->events);
  }

  /**
   * Voltar de "Publicado" para "Em revisão" avisa.
   */
  public function testPublishedToReviewNotifies(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', 'review', $this->node('wiki_entry', 'published')));

    $this->assertCount(1, $this->queue->items);
    $this->assertCount(1, $this->events);
  }

  /**
   * Atualizar um verbete fora de revisão não avisa.
   */
  public function testOtherStatesDoNotNotify(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', 'published', $this->node('wiki_entry', 'draft')));

    $this->assertSame([], $this->queue->items);
    $this->assertSame([], $this->events);
  }

  /**
   * Outro tipo de conteúdo não avisa, mesmo em revisão.
   */
  public function testOtherBundleDoesNotNotify(): void {
    $this->hooks()->entityUpdate($this->node('page', 'review', $this->node('page', 'draft')));

    $this->assertSame([], $this->queue->items);
    $this->assertSame([], $this->events);
  }

  /**
   * Entidade que não é nó não avisa.
   */
  public function testNonNodeEntityDoesNotNotify(): void {
    $this->hooks()->entityUpdate($this->createMock(EntityInterface::class));

    $this->assertSame([], $this->queue->items);
    $this->assertSame([], $this->events);
  }

  /**
   * Nó sem o campo de moderação não avisa e não quebra.
   */
  public function testNodeWithoutModerationFieldDoesNotNotify(): void {
    $this->hooks()->entityUpdate($this->node('wiki_entry', NULL));

    $this->assertSame([], $this->queue->items);
    $this->assertSame([], $this->events);
  }

  /**
   * Desativar o e-mail editorial não desativa o evento do site.
   */
  public function testDisabledConfigDoesNotNotify(): void {
    $this->hooks(FALSE)->entityUpdate($this->node('wiki_entry', 'review', $this->node('wiki_entry', 'draft')));

    $this->assertSame([], $this->queue->items);
    $this->assertCount(1, $this->events);
  }

  /**
   * Hook ligado a um notificador com a configuração pedida.
   */
  private function hooks(bool $enabled = TRUE): WikiReviewHooks {
    $this->queue = new class() {

      /**
       * Mensagens enfileiradas.
       *
       * @var list<\Drupal\aculta_mailer\Message\MailMessage>
       */
      public array $items = [];

      /**
       * Recebe a mensagem, como a fila real do mailer.
       */
      public function enqueue(MailMessage $message): void {
        $this->items[] = $message;
      }

    };
    $notifier = new WikiReviewNotifier(
      $this->configFactory($enabled),
      $this->translation(),
      $this->uuid(),
      $this->queue,
    );
    $queue = $this->createMock(QueueInterface::class);
    $queue->method('createItem')->willReturnCallback(function (array $payload): int {
      $this->events[] = $payload;
      return count($this->events);
    });
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->with('aculta_wiki_editorial_events', TRUE)->willReturn($queue);
    return new WikiReviewHooks($notifier, new WikiEditorialEventQueue($queueFactory, $this->createMock(Connection::class)));
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

  /**
   * Fábrica de configuração com um destinatário e o aviso ligado ou não.
   */
  private function configFactory(bool $enabled): ConfigFactoryInterface {
    $settings = [
      'review_notifications' => $enabled,
      'review_recipients' => 'curadoria@example.invalid',
    ];
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn (string $key): mixed => $settings[$key] ?? NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with(WikiReviewNotifier::CONFIG)->willReturn($config);
    return $factory;
  }

  /**
   * UUID fixo no formato aceito pela mensagem.
   */
  private function uuid(): UuidInterface {
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn(self::UUID);
    return $uuid;
  }

  /**
   * Tradução identidade, para o teste sem container.
   */
  private function translation(): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(static fn ($markup): string => $markup->getUntranslatedString());
    return $translation;
  }

}
