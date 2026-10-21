<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Controller\WikiReadController;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\aculta_statistics\Visitor\VisitorKey;
use Drupal\Core\Flood\FloodInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Leitura de verbete enviada pelo beacon, com limites antiabuso.
 */
#[CoversClass(WikiReadController::class)]
#[Group('aculta_statistics')]
final class WikiReadControllerTest extends TestCase {

  /**
   * Verbete publicado e visível conta uma leitura e responde 204.
   */
  public function testPublishedWikiEntryIsCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())->method('record')->with('wiki.entries.read', '42', 1);

    $response = $this->controller($storage, new InMemoryFlood())->record($this->node('wiki_entry', TRUE, TRUE, 42));

    $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
  }

  /**
   * Segunda leitura do mesmo visitante no mesmo verbete responde 204 sem contar.
   */
  public function testRepeatedReadOfSameEntryIsNotCountedTwice(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())->method('record');
    $controller = $this->controller($storage, new InMemoryFlood());
    $node = $this->node('wiki_entry', TRUE, TRUE, 42);

    $controller->record($node);
    $second = $controller->record($node);

    $this->assertSame(Response::HTTP_NO_CONTENT, $second->getStatusCode());
  }

  /**
   * Após o limite por visitante, a leitura responde 429 e não é contada.
   */
  public function testClientLimitReturnsTooManyRequests(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->exactly(WikiReadController::CLIENT_LIMIT))->method('record');
    $controller = $this->controller($storage, new InMemoryFlood());

    for ($id = 1; $id <= WikiReadController::CLIENT_LIMIT; $id++) {
      $controller->record($this->node('wiki_entry', TRUE, TRUE, $id));
    }
    $blocked = $controller->record($this->node('wiki_entry', TRUE, TRUE, WikiReadController::CLIENT_LIMIT + 1));

    $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $blocked->getStatusCode());
    $this->assertSame((string) WikiReadController::CLIENT_WINDOW, $blocked->headers->get('Retry-After'));
  }

  /**
   * Outro visitante não herda o limite do primeiro.
   */
  public function testOtherVisitorIsNotBlockedByFirstVisitor(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->exactly(WikiReadController::CLIENT_LIMIT + 1))->method('record');
    $flood = new InMemoryFlood();
    $first = $this->controller($storage, $flood, '198.51.100.1');
    $second = $this->controller($storage, $flood, '198.51.100.2');

    for ($id = 1; $id <= WikiReadController::CLIENT_LIMIT; $id++) {
      $first->record($this->node('wiki_entry', TRUE, TRUE, $id));
    }
    $response = $second->record($this->node('wiki_entry', TRUE, TRUE, WikiReadController::CLIENT_LIMIT + 1));

    $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
  }

  /**
   * Conteúdo de outro tipo não é contado e responde 404.
   */
  public function testOtherBundleIsNotCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->expectException(NotFoundHttpException::class);
    $this->controller($storage, new InMemoryFlood())->record($this->node('article', TRUE, TRUE, 7));
  }

  /**
   * Verbete não publicado não é contado.
   */
  public function testUnpublishedEntryIsNotCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->expectException(NotFoundHttpException::class);
    $this->controller($storage, new InMemoryFlood())->record($this->node('wiki_entry', FALSE, TRUE, 8));
  }

  /**
   * Sem permissão de visualização não é contado.
   */
  public function testEntryWithoutViewAccessIsNotCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->expectException(NotFoundHttpException::class);
    $this->controller($storage, new InMemoryFlood())->record($this->node('wiki_entry', TRUE, FALSE, 9));
  }

  /**
   * Cria o controlador com o gravador real, o limite informado e o visitante.
   */
  private function controller(AggregateStorageInterface $storage, FloodInterface $flood, string $ip = '203.0.113.7'): WikiReadController {
    $request = Request::create('/estatisticas/wiki/1/leitura', 'POST', [], [], [], ['REMOTE_ADDR' => $ip]);
    $stack = new RequestStack();
    $stack->push($request);

    return new WikiReadController(
      new StatisticsRecorder(new MetricCatalog(), $storage),
      $flood,
      $stack,
      new VisitorKey('salt-de-teste'),
    );
  }

  /**
   * Cria um nó de teste com o tipo, a publicação e o acesso informados.
   */
  private function node(string $bundle, bool $published, bool $viewable, int $id): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('isPublished')->willReturn($published);
    $node->method('access')->with('view')->willReturn($viewable);
    $node->method('id')->willReturn($id);
    return $node;
  }

}

/**
 * Controle de frequência em memória, com a mesma semântica do serviço `flood`.
 */
final class InMemoryFlood implements FloodInterface {

  /**
   * Contagens por evento e identificador.
   *
   * @var array<string, array<string, int>>
   */
  private array $counts = [];

  /**
   * {@inheritdoc}
   */
  public function register($name, $window = 3600, $identifier = NULL): void {
    $key = $identifier ?? '';
    $this->counts[$name][$key] = ($this->counts[$name][$key] ?? 0) + 1;
  }

  /**
   * {@inheritdoc}
   */
  public function clear($name, $identifier = NULL): void {
    unset($this->counts[$name][$identifier ?? '']);
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed($name, $threshold, $window = 3600, $identifier = NULL): bool {
    return ($this->counts[$name][$identifier ?? ''] ?? 0) < $threshold;
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection(): void {
  }

}
