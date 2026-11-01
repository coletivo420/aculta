<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeAccessRebuild;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * F3 (busca): o índice "conteúdo público" aplica content_access. Um visitante anônimo encontra só nós
 * publicados; o total e a paginação do índice batem com o que a pessoa vê.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class SearchContentAccessKernelTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter', 'search_api', 'search_api_db'];

  private Index $index;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig(['system', 'user', 'node', 'filter', 'search_api', 'search_api_db']);
    NodeType::create(['type' => 'article', 'name' => 'Artigo'])->save();
    // Visitante anônimo lê conteúdo publicado: a permissão vem do papel anonymous (como no site).
    $anonymous = Role::load('anonymous');
    $this->assertNotNull($anonymous, 'O papel anonymous existe no Kernel.');
    $anonymous->grantPermission('access content')->save();

    $server = Server::create([
      'id' => 'teste_db',
      'name' => 'Teste',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => ['min_chars' => 3, 'database' => 'default:default'],
    ]);
    $server->save();

    $this->index = Index::create([
      'id' => 'teste_conteudo',
      'name' => 'Teste conteúdo',
      'status' => TRUE,
      'server' => 'teste_db',
      'datasource_settings' => [
        'entity:node' => [
          'bundles' => ['default' => FALSE, 'selected' => ['article']],
          'languages' => ['default' => TRUE, 'selected' => []],
        ],
      ],
      'field_settings' => [
        'title' => ['label' => 'Título', 'datasource_id' => 'entity:node', 'property_path' => 'title', 'type' => 'text'],
        'status' => ['label' => 'Publicado', 'datasource_id' => 'entity:node', 'property_path' => 'status', 'type' => 'boolean'],
      ],
      // Processadores de texto como no índice de produção: sem eles a busca por palavra não casa.
      'processor_settings' => [
        'content_access' => ['weights' => ['preprocess_query' => -30]],
        'tokenizer' => ['weights' => [], 'spaces' => '', 'ignored' => '._-', 'overlap_cjk' => 1, 'minimum_word_size' => '3'],
        'transliteration' => ['weights' => ['preprocess_index' => -2, 'preprocess_query' => -2]],
      ],
      'options' => ['index_directly' => TRUE, 'cron_limit' => 50],
    ]);
    $this->index->save();
  }

  public function testAnonymousSeesOnlyPublishedContent(): void {
    Node::create(['type' => 'article', 'title' => 'Artigo público', 'status' => 1])->save();
    Node::create(['type' => 'article', 'title' => 'Artigo rascunho', 'status' => 0])->save();
    // Os grants de leitura são reconstruídos antes da indexação (o Kernel não os grava no save).
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    $this->index->indexItems();

    // Visitante real (usuário anônimo com o papel anonymous), como em uma requisição do site.
    $this->container->get('current_user')->setAccount(User::getAnonymousUser());
    // Sem palavra-chave: o teste verifica o acesso (content_access), não a correspondência de texto.
    $query = $this->index->query();
    $titles = $this->titles($query->execute()->getResultItems());

    $this->assertContains('Artigo público', $titles, 'Visitante vê o conteúdo publicado.');
    $this->assertNotContains('Artigo rascunho', $titles, 'Visitante não vê conteúdo não publicado.');
    $this->assertEquals(1, $query->execute()->getResultCount(), 'O total do índice conta só o que é visível.');
  }

  public function testContentAccessProcessorIsEnabledOnIndex(): void {
    $this->index->save();
    $processors = $this->index->getProcessors();
    $this->assertArrayHasKey('content_access', $processors, 'O processador content_access está ativo no índice.');
    $grants = array_filter($this->index->getFields(), static fn($field): bool => $field->getPropertyPath() === 'search_api_node_grants');
    $this->assertCount(1, $grants, 'O campo de grants (search_api_node_grants) existe no índice.');
  }

  /** @param \Drupal\search_api\Item\ItemInterface[] $items */
  private function titles(array $items): array {
    $titles = [];
    foreach ($items as $item) {
      $titles[] = (string) $item->getField('title')?->getValues()[0];
    }
    return $titles;
  }

}
