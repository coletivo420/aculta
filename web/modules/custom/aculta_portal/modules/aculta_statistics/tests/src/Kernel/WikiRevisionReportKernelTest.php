<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Controller\StatisticsDashboardController;
use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\aculta_statistics\Wiki\WikiRevisionReport;
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
 * Edição e revisão por verbete: contagens, ordem, recorte e CSV.
 */
#[CoversClass(WikiRevisionReport::class)]
#[CoversClass(StatisticsDashboardController::class)]
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class WikiRevisionReportKernelTest extends KernelTestBase {

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
   * Conta revisões publicadas e em rascunho, sem duplicar tradução.
   */
  public function testCountsPublishedAndDraftRevisions(): void {
    $beta = $this->node('Beta', [
      [1000, TRUE],
      [2000, TRUE],
      [3000, FALSE],
    ]);

    $report = $this->container->get(WikiRevisionReport::class)->report();
    $this->assertSame(1, $report['total_entries']);
    $this->assertSame(3, $report['total_revisions']);
    $this->assertSame(1, $report['total_drafts']);
    $this->assertCount(1, $report['entries']);
    $entry = $report['entries'][0];
    $this->assertSame((int) $beta->id(), (int) $entry['node']->id());
    $this->assertSame(3, $entry['revisions']);
    $this->assertSame(2, $entry['published']);
    $this->assertSame(1, $entry['drafts']);
    $this->assertSame(3000, $entry['last_edit']);
  }

  /**
   * Rascunho pendente vem primeiro; entre iguais, a edição mais recente.
   */
  public function testDraftsComeFirstThenMostRecentEdit(): void {
    $alfa = $this->node('Alfa', [[1000, FALSE]]);
    $gama = $this->node('Gama', [[9000, TRUE]]);
    $beta = $this->node('Beta', [[2000, TRUE], [3000, FALSE]]);

    $report = $this->container->get(WikiRevisionReport::class)->report();
    $this->assertSame([(int) $beta->id(), (int) $alfa->id(), (int) $gama->id()], array_map(
      static fn(array $entry): int => (int) $entry['node']->id(),
      $report['entries'],
    ));
  }

  /**
   * Outro tipo de conteúdo não entra no relatório, mesmo com revisões.
   */
  public function testOtherBundlesAreIgnored(): void {
    $this->node('Artigo', [[1000, TRUE], [2000, TRUE]], 'article');

    $report = $this->container->get(WikiRevisionReport::class)->report();
    $this->assertSame(0, $report['total_entries']);
    $this->assertSame([], $report['entries']);
  }

  /**
   * O painel mostra os primeiros verbetes; os totais e o CSV cobrem todos.
   */
  public function testPanelCutsTheListAndCsvExportsEverything(): void {
    $this->node('Beta, com vírgula', [[1000, TRUE], [2000, FALSE]]);
    $this->node('Alfa', [[1500, TRUE]]);
    $this->node('Gama', [[500, TRUE]]);

    $html = $this->renderArea('wiki');
    $this->assertStringContainsString('data-portal-ui="statistics-wiki-revisions"', $html);
    $this->assertStringContainsString('Edição e revisão por verbete', $html);
    $this->assertStringContainsString('3 verbetes com histórico, 4 revisões, 1 em rascunho.', $html);
    $this->assertStringContainsString('href="/node/' . $this->nidByTitle('Alfa') . '"', $html);
    $this->assertStringNotContainsString('Mostrando os', $html);

    $csv = $this->export()->getContent();
    $expected = "verbete,revisoes,publicadas,rascunhos,ultima_edicao\n"
      . '"Beta, com vírgula",2,1,1,' . gmdate('c', 2000) . "\n"
      . 'Alfa,1,1,0,' . gmdate('c', 1500) . "\n"
      . 'Gama,1,1,0,' . gmdate('c', 500) . "\n";
    $this->assertSame($expected, $csv);
  }

  /**
   * O recorte vale só para a lista; os totais contam todo o histórico.
   */
  public function testLimitCutsTheListNotTheTotals(): void {
    $this->node('Alfa', [[1000, TRUE], [2000, TRUE]]);
    $this->node('Beta', [[3000, FALSE]]);

    $report = $this->container->get(WikiRevisionReport::class)->report(1);
    $this->assertCount(1, $report['entries']);
    $this->assertSame(1, $report['limit']);
    $this->assertSame(2, $report['total_entries']);
    $this->assertSame(3, $report['total_revisions']);
    $this->assertSame(1, $report['total_drafts']);
  }

  /**
   * Cria um nó com as revisões informadas: timestamp e se é publicada.
   *
   * @param string $title
   *   Título do nó.
   * @param array<int, array{0: int, 1: bool}> $revisions
   *   Revisões na ordem de criação.
   * @param string $type
   *   Tipo de conteúdo; por padrão, o verbete da Wiki.
   */
  private function node(string $title, array $revisions, string $type = 'wiki_entry'): NodeInterface {
    $node = Node::create(['type' => $type, 'title' => $title]);
    foreach ($revisions as [$timestamp, $published]) {
      $node->setNewRevision(TRUE);
      $node->setRevisionCreationTime($timestamp);
      $published ? $node->setPublished() : $node->setUnpublished();
      $node->save();
    }
    return $node;
  }

  /**
   * ID do verbete pelo título.
   */
  private function nidByTitle(string $title): int {
    $ids = $this->container->get('entity_type.manager')->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', $title)
      ->execute();
    return (int) reset($ids);
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
   * Exporta o histórico de edição.
   */
  private function export(): Response {
    return StatisticsDashboardController::create($this->container)->wikiRevisionExport();
  }

}
