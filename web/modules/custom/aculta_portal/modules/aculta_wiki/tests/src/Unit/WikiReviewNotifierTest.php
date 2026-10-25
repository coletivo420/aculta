<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_wiki\Notifier\WikiReviewNotifier;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Aviso de revisão: configuração, destinatários, contexto sem autoria e fila.
 */
#[CoversClass(WikiReviewNotifier::class)]
#[Group('aculta_wiki')]
final class WikiReviewNotifierTest extends UnitTestCase {

  private const UUID = '11111111-1111-4111-8111-111111111111';

  /**
   * Fila em memória usada pelo notificador.
   */
  private object $queue;

  /**
   * Desligado, não enfileira nada.
   */
  public function testDisabledSendsNothing(): void {
    $notifier = $this->notifier(['review_notifications' => FALSE, 'review_recipients' => 'curadoria@example.invalid']);

    $this->assertSame(0, $notifier->notifyReview($this->verbete()));
    $this->assertSame([], $this->queue->items);
  }

  /**
   * Ligado sem destinatário válido, não enfileira.
   */
  public function testEmptyRecipientsSendsNothing(): void {
    $notifier = $this->notifier(['review_notifications' => TRUE, 'review_recipients' => ' , nao-e-email ']);

    $this->assertSame(0, $notifier->notifyReview($this->verbete()));
    $this->assertSame([], $this->queue->items);
  }

  /**
   * Sem a fila do mailer, o aviso não é enviado e nada quebra.
   */
  public function testWithoutQueueSendsNothing(): void {
    $this->queue = $this->memoryQueue();
    $notifier = new WikiReviewNotifier(
      $this->configFactory(['review_notifications' => TRUE, 'review_recipients' => 'curadoria@example.invalid']),
      $this->translation(),
      $this->uuid(),
    );

    $this->assertSame(0, $notifier->notifyReview($this->verbete()));
    $this->assertSame([], $this->queue->items);
  }

  /**
   * Um aviso por endereço válido, em minúsculas e sem autoria no contexto.
   */
  public function testEnqueuesOneMessagePerRecipient(): void {
    $notifier = $this->notifier(
      ['review_notifications' => TRUE, 'review_recipients' => 'Curadoria@Example.invalid, revisao@example.invalid'],
      $this->publicUrl('https://wiki420.aculta.org/node/42/edit'),
    );

    $this->assertSame(2, $notifier->notifyReview($this->verbete()));

    $this->assertCount(2, $this->queue->items);
    $message = $this->queue->items[0]->toArray();
    $this->assertSame('aculta_wiki_review', $message['type']);
    $this->assertSame('curadoria@example.invalid', $message['to']);
    $this->assertSame('pt-br', $message['langcode']);
    $this->assertSame(self::UUID, $message['correlation_id']);
    $this->assertSame([
      'verbete' => 'Cannabis medicinal',
      'estado' => 'Em revisão',
      'link' => 'https://wiki420.aculta.org/node/42/edit',
    ], $message['context']);
    $this->assertSame('revisao@example.invalid', $this->queue->items[1]->to);
  }

  /**
   * Sem o serviço de URL pública, o link vai vazio e o e-mail não quebra.
   */
  public function testLinkIsEmptyWithoutPublicUrl(): void {
    $notifier = $this->notifier(['review_notifications' => TRUE, 'review_recipients' => 'curadoria@example.invalid']);

    $notifier->notifyReview($this->verbete());

    $this->assertSame('', $this->queue->items[0]->context['link']);
  }

  /**
   * A configuração manda avisar ou não.
   */
  public function testIsEnabledReadsConfig(): void {
    $this->assertTrue($this->notifier(['review_notifications' => TRUE])->isEnabled());
    $this->assertFalse($this->notifier([])->isEnabled());
  }

  /**
   * Separadores, caixa e repetição no texto de destinatários.
   */
  public function testParseRecipients(): void {
    $this->assertSame(
      ['a@example.invalid', 'b@example.invalid'],
      WikiReviewNotifier::parseRecipients(" A@Example.invalid;\nb@example.invalid, a@example.invalid "),
    );
    $this->assertSame([], WikiReviewNotifier::parseRecipients('curadoria'));
    $this->assertSame([], WikiReviewNotifier::parseRecipients(''));
  }

  /**
   * Notificador com fila em memória e a configuração informada.
   */
  private function notifier(array $settings, ?object $publicUrl = NULL): WikiReviewNotifier {
    $this->queue = $this->memoryQueue();
    return new WikiReviewNotifier(
      $this->configFactory($settings),
      $this->translation(),
      $this->uuid(),
      $this->queue,
      $publicUrl,
    );
  }

  /**
   * Fila que guarda as mensagens recebidas.
   */
  private function memoryQueue(): object {
    return new class() {

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
  }

  /**
   * Fábrica de configuração com os valores informados.
   */
  private function configFactory(array $settings): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn (string $key): mixed => $settings[$key] ?? NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with(WikiReviewNotifier::CONFIG)->willReturn($config);
    return $factory;
  }

  /**
   * URL pública que devolve o endereço informado.
   */
  private function publicUrl(string $url): object {
    return new class($url) {

      public function __construct(private readonly string $url) {}

      /**
       * Endereço absoluto do caminho.
       */
      public function absolute(string $path): string {
        return $this->url;
      }

    };
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

  /**
   * Verbete com título e identificador.
   */
  private function verbete(): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('label')->willReturn('Cannabis medicinal');
    $node->method('id')->willReturn(42);
    return $node;
  }

}
