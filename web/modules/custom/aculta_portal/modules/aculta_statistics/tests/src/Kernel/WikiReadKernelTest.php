<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Drupal\aculta_statistics\Controller\WikiReadController;

/**
 * Beacon de leitura de verbete: conta só verbete publicado da Wiki.
 *
 * Cada requisição HTTP fica em um teste próprio: o Kernel de teste mantém o estado da
 * requisição anterior, e uma segunda chamada na mesma execução responde 405 por engano.
 */
#[CoversClass(WikiReadController::class)]
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class WikiReadKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_statistics', 'node', 'user', 'system', 'field', 'text', 'filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('aculta_statistics', [DailyAggregateStorage::TABLE]);
    $this->installConfig(['aculta_statistics']);
    $this->installConfig(['user', 'node']);
    // Visitante anônimo lê conteúdo publicado: a leitura pública é a condição do beacon.
    Role::load('anonymous')->grantPermission('access content')->save();
    NodeType::create(['type' => 'wiki_entry', 'name' => 'Verbete'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Artigo'])->save();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Verbete publicado: a leitura é gravada e a resposta é 204.
   */
  public function testPublishedEntryIsCounted(): void {
    $nid = $this->node('wiki_entry', TRUE);

    $response = $this->post($nid);

    $this->assertSame(204, $response->getStatusCode());
    $storage = $this->container->get('aculta_statistics.storage');
    $this->assertSame(1, $storage->total('wiki.entries.read', 0, $storage->currentDay() + 1, (string) $nid));
  }

  /**
   * Rascunho responde 404 e não é contado.
   */
  public function testDraftIsNotCounted(): void {
    $draft = $this->node('wiki_entry', FALSE);

    $this->assertSame(404, $this->post($draft)->getStatusCode());
    $this->assertNoReadRecorded();
  }

  /**
   * Conteúdo de outro tipo responde 404 e não é contado.
   */
  public function testOtherBundleIsNotCounted(): void {
    $article = $this->node('article', TRUE);

    $this->assertSame(404, $this->post($article)->getStatusCode());
    $this->assertNoReadRecorded();
  }

  /**
   * Confirma que nenhuma leitura foi gravada.
   */
  private function assertNoReadRecorded(): void {
    $storage = $this->container->get('aculta_statistics.storage');
    $this->assertSame(0, $storage->total('wiki.entries.read', 0, $storage->currentDay() + 1));
  }

  /**
   * Cria um nó do tipo informado, publicado ou não.
   */
  private function node(string $type, bool $published): int {
    $node = Node::create(['type' => $type, 'title' => 'Teste', 'status' => $published ? 1 : 0]);
    $node->save();
    return (int) $node->id();
  }

  /**
   * Envia o beacon como o navegador faria.
   */
  private function post(int $nid): \Symfony\Component\HttpFoundation\Response {
    return $this->container->get('http_kernel')->handle(Request::create("/estatisticas/wiki/$nid/leitura", 'POST'));
  }

}
