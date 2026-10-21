<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Controller\StatisticsDashboardController;
use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Painel central: métrica e período escolhidos, gráfico, exportação CSV e exclusão de métricas restritas.
 */
#[CoversClass(StatisticsDashboardController::class)]
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class StatisticsDashboardKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'aculta_statistics'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('aculta_statistics', [DailyAggregateStorage::TABLE]);
  }

  /**
   * A página mostra o período escolhido e o total; o CSV tem uma linha por dia e dimensão.
   */
  public function testPageAndExportFollowTheSelection(): void {
    $storage = $this->container->get('aculta_statistics.storage');
    $storage->record('wiki.entries.read', '10', 3);
    $storage->record('wiki.entries.read', '11', 2);

    $request = Request::create('/painel-administrativo/aculta/estatisticas', 'GET', ['metrica' => 'wiki.entries.read', 'dias' => 14]);
    $page = StatisticsDashboardController::create($this->container)->overview($request);
    $html = (string) $this->container->get('renderer')->renderInIsolation($page);
    $this->assertStringContainsString('<option value="14" selected', $html);
    $this->assertStringContainsString('<option value="wiki.entries.read" selected', $html);
    $this->assertStringContainsString('<svg', $html);
    $this->assertStringContainsString('5 leituras no período (14 dias)', $html);

    $export = StatisticsDashboardController::create($this->container)->export($request);
    $this->assertSame('text/csv; charset=utf-8', $export->headers->get('Content-Type'));
    $this->assertStringContainsString('dia,dimensao,total', (string) $export->getContent());
    $this->assertStringContainsString(',10,3', (string) $export->getContent());
    $this->assertStringContainsString(',11,2', (string) $export->getContent());
  }

  /**
   * Filtro por domínio soma só o purpose escolhido, na página e no CSV.
   */
  public function testDomainFilterOnOrderCounts(): void {
    $storage = $this->container->get('aculta_statistics.storage');
    $storage->record('commerce.orders.by_domain', 'main.aculta|BRL', 2);
    $storage->record('commerce.orders.by_domain', 'loja.aculta|BRL', 5);

    $request = Request::create('/painel-administrativo/aculta/estatisticas', 'GET', ['metrica' => 'commerce.orders.by_domain', 'dias' => 7, 'dominio' => 'loja.aculta']);
    $page = StatisticsDashboardController::create($this->container)->overview($request);
    $html = (string) $this->container->get('renderer')->renderInIsolation($page);
    $this->assertStringContainsString('<option value="loja.aculta" selected', $html);
    $this->assertStringContainsString('5 pedidos no período (7 dias)', $html);

    $export = (string) StatisticsDashboardController::create($this->container)->export($request)->getContent();
    $this->assertStringContainsString('loja.aculta|BRL,5', $export);
    $this->assertStringNotContainsString('main.aculta', $export);
  }

  /**
   * A comparação mostra o período anterior e a variação, sem afirmar causa.
   */
  public function testComparisonWithPreviousPeriod(): void {
    $storage = $this->container->get('aculta_statistics.storage');
    $today = $storage->currentDay();
    $storage->record('wiki.entries.read', '10', 4);
    $storage->replaceDay('wiki.entries.read', $today - 8, ['10' => 2]);

    $request = Request::create('/painel-administrativo/aculta/estatisticas', 'GET', ['metrica' => 'wiki.entries.read', 'dias' => 7]);
    $page = StatisticsDashboardController::create($this->container)->overview($request);
    $html = (string) $this->container->get('renderer')->renderInIsolation($page);
    $this->assertStringContainsString('Período anterior (mesmo tamanho): 2 leituras', $html);
    $this->assertStringContainsString('Variação: +100%', $html);
    $this->assertStringContainsString('não sua causa', $html);
  }

  /**
   * Métrica restrita (dado pessoal por aluno) não é oferecida no painel agregado.
   */
  public function testRestrictedMetricIsNotOffered(): void {
    $request = Request::create('/painel-administrativo/aculta/estatisticas', 'GET', ['metrica' => 'lms.student.status']);
    $page = StatisticsDashboardController::create($this->container)->overview($request);
    $html = (string) $this->container->get('renderer')->renderInIsolation($page);

    $this->assertStringNotContainsString('lms.student.status', $html);
    $this->assertStringNotContainsString('value="lms.student.status"', $html);
  }

}
