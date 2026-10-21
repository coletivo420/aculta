<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Controller\WikiReadController;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Leitura de verbete enviada pelo beacon.
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

    $response = $this->controller($storage)->record($this->node('wiki_entry', TRUE, TRUE, 42));

    $this->assertInstanceOf(Response::class, $response);
    $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
  }

  /**
   * Conteúdo de outro tipo não é contado e responde 404.
   */
  public function testOtherBundleIsNotCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->expectException(NotFoundHttpException::class);
    $this->controller($storage)->record($this->node('article', TRUE, TRUE, 7));
  }

  /**
   * Verbete não publicado não é contado.
   */
  public function testUnpublishedEntryIsNotCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->expectException(NotFoundHttpException::class);
    $this->controller($storage)->record($this->node('wiki_entry', FALSE, TRUE, 8));
  }

  /**
   * Sem permissão de visualização não é contado.
   */
  public function testEntryWithoutViewAccessIsNotCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->expectException(NotFoundHttpException::class);
    $this->controller($storage)->record($this->node('wiki_entry', TRUE, FALSE, 9));
  }

  /**
   * Cria o controlador com o gravador real sobre o storage informado.
   */
  private function controller(AggregateStorageInterface $storage): WikiReadController {
    return new WikiReadController(new StatisticsRecorder(new MetricCatalog(), $storage));
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
