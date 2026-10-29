<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_portal\Saved\SavedContentFilter;
use Drupal\aculta_portal\Saved\SavedContentQuery;
use Drupal\flag\Entity\Flag;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Testes Kernel para a consulta transversal de salvos (ARQ-07).
 *
 * Cobre:
 * - Isolamento estrito entre usuários (sem vazamento).
 * - Filtragem por Entity Access (conteúdo despublicado não vaza).
 * - Resiliência a registros órfãos por exclusão de entidade.
 * - Filtragem por categoria (salvos vs seguindo) e bundle.
 * - Paginação estável.
 * - Metadados de cache (tags e contexts).
 * - Bloqueio estrito de flags de moderação/denúncia.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class SavedContentQueryKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'flag',
  ];

  /**
   * The save flag.
   */
  private Flag $flagSalvar;

  /**
   * The follow flag.
   */
  private Flag $flagSeguir;

  /**
   * The moderation flag.
   */
  private Flag $flagModeracao;

  /**
   * The query service under test.
   */
  private SavedContentQuery $queryService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('flagging');
    $this->installSchema('flag', ['flag_counts']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['user', 'filter', 'flag', 'node']);

    NodeType::create(['type' => 'topico', 'name' => 'Tópico'])->save();
    NodeType::create(['type' => 'artigo', 'name' => 'Artigo'])->save();

    // Criar UID 1 (superadmin) para que os usuários de teste tenham UID > 1.
    User::create([
      'uid' => 1,
      'name' => 'superadmin',
      'status' => 1,
    ])->save();

    // Permissão para usuários autenticados verem nós publicados.
    $authenticated = Role::load('authenticated');
    $this->assertNotNull($authenticated);
    $authenticated->grantPermission('access content')->save();

    // Criar flags do sistema.
    $this->flagSalvar = Flag::create([
      'id' => 'salvar_topico',
      'label' => 'Salvar Tópico',
      'entity_type' => 'node',
      'bundles' => ['topico', 'artigo'],
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
      'global' => FALSE,
    ]);
    $this->flagSalvar->save();

    $this->flagSeguir = Flag::create([
      'id' => 'seguir_topico',
      'label' => 'Seguir Tópico',
      'entity_type' => 'node',
      'bundles' => ['topico', 'artigo'],
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
      'global' => FALSE,
    ]);
    $this->flagSeguir->save();

    $this->flagModeracao = Flag::create([
      'id' => 'sinalizar_conteudo',
      'label' => 'Sinalizar Abuso',
      'entity_type' => 'node',
      'bundles' => ['topico', 'artigo'],
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
      'global' => FALSE,
    ]);
    $this->flagModeracao->save();

    $this->queryService = new SavedContentQuery(
      $this->container->get('database'),
      $this->container->get('entity_type.manager')
    );
  }

  /**
   * Valida que itens salvos pelo Usuário A não são visíveis pelo Usuário B.
   */
  public function testIsolamentoEntreDuasContas(): void {
    $userA = User::create(['name' => 'user_a', 'status' => 1]);
    $userA->save();

    $userB = User::create(['name' => 'user_b', 'status' => 1]);
    $userB->save();

    $nodeA = Node::create([
      'type' => 'topico',
      'title' => 'Tópico Privado de A',
      'status' => 1,
      'uid' => $userA->id(),
    ]);
    $nodeA->save();

    $nodeB = Node::create([
      'type' => 'topico',
      'title' => 'Tópico Privado de B',
      'status' => 1,
      'uid' => $userB->id(),
    ]);
    $nodeB->save();

    /** @var \Drupal\flag\FlagServiceInterface $flagService */
    $flagService = $this->container->get('flag');
    $flagService->flag($this->flagSalvar, $nodeA, $userA);
    $flagService->flag($this->flagSalvar, $nodeB, $userB);

    $filterA = new SavedContentFilter(account: $userA);
    $resultA = $this->queryService->query($filterA);

    $this->assertSame(1, $resultA->totalCount);
    $this->assertCount(1, $resultA->items);
    $this->assertSame((int) $nodeA->id(), (int) $resultA->items[0]->entityId);
    $this->assertSame('Tópico Privado de A', $resultA->items[0]->title);

    $filterB = new SavedContentFilter(account: $userB);
    $resultB = $this->queryService->query($filterB);

    $this->assertSame(1, $resultB->totalCount);
    $this->assertCount(1, $resultB->items);
    $this->assertSame((int) $nodeB->id(), (int) $resultB->items[0]->entityId);
    $this->assertSame('Tópico Privado de B', $resultB->items[0]->title);
  }

  /**
   * Conteúdo despublicado marcado pelo usuário não pode vazar na consulta.
   */
  public function testEntityAccessDespublicado(): void {
    $user = User::create(['name' => 'regular_user', 'status' => 1]);
    $user->save();

    $nodePublicado = Node::create([
      'type' => 'topico',
      'title' => 'Tópico Público',
      'status' => 1,
      'uid' => 1,
    ]);
    $nodePublicado->save();

    $nodeDespublicado = Node::create([
      'type' => 'topico',
      'title' => 'Tópico Ocultado',
      'status' => 0,
      'uid' => 1,
    ]);
    $nodeDespublicado->save();

    /** @var \Drupal\flag\FlagServiceInterface $flagService */
    $flagService = $this->container->get('flag');
    $flagService->flag($this->flagSalvar, $nodePublicado, $user);
    $flagService->flag($this->flagSalvar, $nodeDespublicado, $user);

    $filter = new SavedContentFilter(account: $user);
    $result = $this->queryService->query($filter);

    $this->assertSame(1, $result->totalCount, 'Apenas o nó publicado deve ser contabilizado');
    $this->assertCount(1, $result->items);
    $this->assertSame((int) $nodePublicado->id(), (int) $result->items[0]->entityId);
  }

  /**
   * Resiliência a exclusão física da entidade no banco com flag órfã.
   */
  public function testEntidadeExcluidaFisicamente(): void {
    $user = User::create(['name' => 'resilience_user', 'status' => 1]);
    $user->save();

    $node = Node::create([
      'type' => 'topico',
      'title' => 'Tópico Temporário',
      'status' => 1,
      'uid' => 1,
    ]);
    $node->save();

    /** @var \Drupal\flag\FlagServiceInterface $flagService */
    $flagService = $this->container->get('flag');
    $flagService->flag($this->flagSalvar, $node, $user);

    // Deletar o nó diretamente.
    $node->delete();

    $filter = new SavedContentFilter(account: $user);
    $result = $this->queryService->query($filter);

    $this->assertSame(0, $result->totalCount);
    $this->assertCount(0, $result->items);
  }

  /**
   * Filtragem por categoria (salvos vs seguindo) e por bundle.
   */
  public function testFiltragemPorCategoriaEbundle(): void {
    $user = User::create(['name' => 'cat_user', 'status' => 1]);
    $user->save();

    $topico = Node::create([
      'type' => 'topico',
      'title' => 'Discussão Geral',
      'status' => 1,
      'uid' => 1,
    ]);
    $topico->save();

    $artigo = Node::create([
      'type' => 'artigo',
      'title' => 'Artigo Especial',
      'status' => 1,
      'uid' => 1,
    ]);
    $artigo->save();

    /** @var \Drupal\flag\FlagServiceInterface $flagService */
    $flagService = $this->container->get('flag');
    // Usuário salva o tópico e segue o artigo.
    $flagService->flag($this->flagSalvar, $topico, $user);
    $flagService->flag($this->flagSeguir, $artigo, $user);

    // Filtro por salvos: deve vir apenas o tópico.
    $filterSaved = new SavedContentFilter(account: $user, category: 'saved');
    $resultSaved = $this->queryService->query($filterSaved);
    $this->assertSame(1, $resultSaved->totalCount);
    $this->assertSame((int) $topico->id(), (int) $resultSaved->items[0]->entityId);
    $this->assertSame('saved', $resultSaved->items[0]->category);

    // Filtro por seguindo: deve vir apenas o artigo.
    $filterFollowing = new SavedContentFilter(account: $user, category: 'following');
    $resultFollowing = $this->queryService->query($filterFollowing);
    $this->assertSame(1, $resultFollowing->totalCount);
    $this->assertSame((int) $artigo->id(), (int) $resultFollowing->items[0]->entityId);
    $this->assertSame('following', $resultFollowing->items[0]->category);

    // Filtro por bundle 'artigo': deve vir apenas o artigo.
    $filterBundle = new SavedContentFilter(account: $user, bundle: 'artigo');
    $resultBundle = $this->queryService->query($filterBundle);
    $this->assertSame(1, $resultBundle->totalCount);
    $this->assertSame((int) $artigo->id(), (int) $resultBundle->items[0]->entityId);
  }

  /**
   * Paginação estável respeitando limit e offset.
   */
  public function testPaginacaoEstavel(): void {
    $user = User::create(['name' => 'page_user', 'status' => 1]);
    $user->save();

    /** @var \Drupal\flag\FlagServiceInterface $flagService */
    $flagService = $this->container->get('flag');

    $nodes = [];
    for ($i = 1; $i <= 5; $i++) {
      $node = Node::create([
        'type' => 'topico',
        'title' => "Tópico $i",
        'status' => 1,
        'uid' => 1,
      ]);
      $node->save();
      $flagService->flag($this->flagSalvar, $node, $user);
      $nodes[] = $node;
    }

    // Página 1: limit 2, offset 0.
    $page1 = $this->queryService->query(new SavedContentFilter(account: $user, limit: 2, offset: 0));
    $this->assertSame(5, $page1->totalCount);
    $this->assertCount(2, $page1->items);
    $this->assertTrue($page1->hasMore);

    // Página 2: limit 2, offset 2.
    $page2 = $this->queryService->query(new SavedContentFilter(account: $user, limit: 2, offset: 2));
    $this->assertSame(5, $page2->totalCount);
    $this->assertCount(2, $page2->items);
    $this->assertTrue($page2->hasMore);

    // Página 3: limit 2, offset 4.
    $page3 = $this->queryService->query(new SavedContentFilter(account: $user, limit: 2, offset: 4));
    $this->assertSame(5, $page3->totalCount);
    $this->assertCount(1, $page3->items);
    $this->assertFalse($page3->hasMore);
  }

  /**
   * Metadados de cache tags e contexts.
   */
  public function testCacheabilityMetadata(): void {
    $user = User::create(['name' => 'cache_user', 'status' => 1]);
    $user->save();

    $node = Node::create([
      'type' => 'topico',
      'title' => 'Tópico Cache',
      'status' => 1,
      'uid' => 1,
    ]);
    $node->save();

    /** @var \Drupal\flag\FlagServiceInterface $flagService */
    $flagService = $this->container->get('flag');
    $flagService->flag($this->flagSalvar, $node, $user);

    $result = $this->queryService->query(new SavedContentFilter(account: $user));

    $contexts = $result->getCacheContexts();
    $this->assertContains('user', $contexts);
    $this->assertContains('user.permissions', $contexts);

    $tags = $result->getCacheTags();
    $this->assertContains('flagging_list', $tags);
    $this->assertContains('user:' . $user->id(), $tags);
    $this->assertContains('node:' . $node->id(), $tags);
  }

  /**
   * Flags de moderação ou denúncia nunca devem aparecer na biblioteca.
   */
  public function testBloqueioDeFlagsDeModeracao(): void {
    $user = User::create(['name' => 'mod_user', 'status' => 1]);
    $user->save();

    $node = Node::create([
      'type' => 'topico',
      'title' => 'Tópico Denunciado',
      'status' => 1,
      'uid' => 1,
    ]);
    $node->save();

    /** @var \Drupal\flag\FlagServiceInterface $flagService */
    $flagService = $this->container->get('flag');
    $flagService->flag($this->flagModeracao, $node, $user);

    $result = $this->queryService->query(new SavedContentFilter(account: $user));
    $this->assertSame(0, $result->totalCount);
    $this->assertCount(0, $result->items);
  }

}
