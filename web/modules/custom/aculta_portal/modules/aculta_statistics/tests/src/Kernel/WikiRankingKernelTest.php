<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Controller\StatisticsDashboardController;
use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ranking por verbete na área Wiki: ordem, recorte do período, título atual e CSV.
 */
#[CoversClass(StatisticsDashboardController::class)]
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class WikiRankingKernelTest extends KernelTestBase {

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
    $this->installConfig(['system', 'user', 'node']);
    NodeType::create(['type' => 'wiki_entry', 'name' => 'Verbete'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Artigo'])->save();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * O ranking segue as leituras do período, ignora outro tipo e resolve o título na renderização.
   */
  public function testRankingFollowsThePeriodAndTheCurrentTitle(): void {
    $storage = $this->container->get('aculta_statistics.storage');
    $beta = $this->node('Beta, com vírgula');
    $alfa = $this->node('Alfa');
    $artigo = $this->node('Artigo', 'article');
    $storage->record('wiki.entries.read', (string) $beta->id(), 3);
    $storage->record('wiki.entries.read', (string) $alfa->id(), 2);
    $storage->record('wiki.entries.read', (string) $artigo->id(), 10);
    $storage->record('wiki.entries.read', 'sessao', 5);
    // Leitura fora do período: não entra no recorte de 7 dias.
    $storage->replaceDay('wiki.entries.read', $storage->currentDay() - 40, [(string) $beta->id() => 100]);

    $html = $this->renderArea('wiki');

    $this->assertStringContainsString('data-portal-ui="statistics-wiki-ranking"', $html);
    $this->assertStringContainsString('Verbetes mais lidos no período', $html);
    $positionBeta = strpos($html, 'Beta, com vírgula');
    $positionAlfa = strpos($html, 'Alfa');
    $this->assertNotFalse($positionBeta);
    $this->assertNotFalse($positionAlfa);
    $this->assertLessThan($positionAlfa, $positionBeta);
    $this->assertStringContainsString('href="/node/' . $beta->id() . '"', $html);
    $this->assertStringNotContainsString('Artigo', $html);
    $this->assertStringNotContainsString('sessao', $html);

    $csv = $this->export()->getContent();
    $expected = "posicao,verbete,leituras\n"
      . '1,"Beta, com vírgula",3' . "\n"
      . "2,Alfa,2\n";
    $this->assertSame($expected, $csv);
  }

  /**
   * Verbete excluído sai do ranking sem erro, na página e no CSV.
   */
  public function testDeletedEntryLeavesTheRankingWithoutError(): void {
    $storage = $this->container->get('aculta_statistics.storage');
    $verbete = $this->node('Verbete excluído');
    $storage->record('wiki.entries.read', (string) $verbete->id(), 4);
    $verbete->delete();

    $html = $this->renderArea('wiki');
    $this->assertStringContainsString('Nenhuma leitura de verbete no período.', $html);
    $this->assertStringNotContainsString('Verbete excluído', $html);
    $this->assertSame("posicao,verbete,leituras\n", $this->export()->getContent());
  }

  /**
   * Dimensão que não é ID de verbete (ou de nó inexistente) não aparece no ranking.
   */
  public function testDimensionsWithoutReadableEntryAreIgnored(): void {
    $this->container->get('aculta_statistics.storage')->record('wiki.entries.read', '10', 3);

    $this->assertStringContainsString('Nenhuma leitura de verbete no período.', $this->renderArea('wiki'));
    $this->assertSame("posicao,verbete,leituras\n", $this->export()->getContent());
  }

  /**
   * Cria um nó publicado do tipo informado.
   */
  private function node(string $title, string $type = 'wiki_entry'): NodeInterface {
    $node = Node::create([
      'type' => $type,
      'title' => $title,
      'status' => 1,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Renderiza o painel da área informada no período padrão.
   */
  private function renderArea(string $area): string {
    $request = Request::create('/painel-administrativo/aculta/estatisticas/area/' . $area, 'GET');
    $page = StatisticsDashboardController::create($this->container)->overview($request, $area);
    return (string) $this->container->get('renderer')->renderInIsolation($page);
  }

  /**
   * Exporta o ranking no período padrão.
   */
  private function export(): Response {
    $request = Request::create('/painel-administrativo/aculta/estatisticas/area/wiki/ranking.csv', 'GET');
    return StatisticsDashboardController::create($this->container)->wikiRankingExport($request);
  }

}
