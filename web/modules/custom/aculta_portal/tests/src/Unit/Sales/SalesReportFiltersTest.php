<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Sales;

use Drupal\aculta_portal\Sales\SalesReportFilters;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Filtros do relatório de vendas: datas, loja, granularidade e limites.
 */
#[Group('aculta_portal')]
final class SalesReportFiltersTest extends UnitTestCase {

  private \DateTimeZone $fuso;

  private \DateTimeImmutable $hoje;

  protected function setUp(): void {
    parent::setUp();
    $this->fuso = new \DateTimeZone('America/Sao_Paulo');
    $this->hoje = new \DateTimeImmutable('2026-10-16 12:00:00', $this->fuso);
  }

  public function testSemParametrosUsaUltimos30Dias(): void {
    $f = SalesReportFilters::fromQuery([], $this->fuso, $this->hoje);
    $this->assertSame('2026-09-17', $f->inicio->format('Y-m-d'));
    $this->assertSame('2026-10-16', $f->fim->format('Y-m-d'));
    $this->assertSame([], $f->erros);
    $this->assertNull($f->lojaId);
    $this->assertSame('dia', $f->granularidade);
  }

  public function testDatasValidasSaoAplicadas(): void {
    $f = SalesReportFilters::fromQuery(['de' => '2026-01-01', 'ate' => '2026-01-31'], $this->fuso, $this->hoje);
    $this->assertSame('2026-01-01', $f->inicio->format('Y-m-d'));
    $this->assertSame('2026-01-31', $f->fim->format('Y-m-d'));
    $this->assertSame([], $f->erros);
  }

  public function testDataInvalidaVoltaAoPadraoSemExcecao(): void {
    $f = SalesReportFilters::fromQuery(['de' => '2026-02-30', 'ate' => 'abc'], $this->fuso, $this->hoje);
    $this->assertSame('2026-10-16', $f->fim->format('Y-m-d'));
    $this->assertSame('2026-09-17', $f->inicio->format('Y-m-d'));
  }

  public function testIntervaloInvertidoEhSinalizado(): void {
    $f = SalesReportFilters::fromQuery(['de' => '2026-05-10', 'ate' => '2026-05-01'], $this->fuso, $this->hoje);
    $this->assertContains('intervalo_invertido', $f->erros);
    $this->assertTrue($f->inicio <= $f->fim);
  }

  public function testIntervaloMaiorQueLimiteEhReduzido(): void {
    $f = SalesReportFilters::fromQuery(['de' => '2020-01-01', 'ate' => '2026-10-16'], $this->fuso, $this->hoje);
    $this->assertContains('intervalo_grande', $f->erros);
    $this->assertSame(SalesReportFilters::MAX_DIAS, $f->inicio->diff($f->fim)->days + 1);
  }

  public function testLojaSoAceitaInteiroPositivo(): void {
    $this->assertSame(7, SalesReportFilters::fromQuery(['loja' => '7'], $this->fuso, $this->hoje)->lojaId);
    $this->assertNull(SalesReportFilters::fromQuery(['loja' => '0'], $this->fuso, $this->hoje)->lojaId);
    $this->assertNull(SalesReportFilters::fromQuery(['loja' => '7; DROP'], $this->fuso, $this->hoje)->lojaId);
  }

  public function testGranularidadeDesconhecidaVoltaAoDia(): void {
    $this->assertSame('semana', SalesReportFilters::fromQuery(['granularidade' => 'semana'], $this->fuso, $this->hoje)->granularidade);
    $this->assertSame('dia', SalesReportFilters::fromQuery(['granularidade' => 'ano'], $this->fuso, $this->hoje)->granularidade);
  }

  public function testTimestampsCobremOUltimoDiaInteiro(): void {
    $f = SalesReportFilters::fromQuery(['de' => '2026-10-01', 'ate' => '2026-10-01'], $this->fuso, $this->hoje);
    [$inicio, $fim] = $f->timestamps();
    $this->assertSame(strtotime('2026-10-01 00:00:00 -03:00'), $inicio);
    $this->assertSame(strtotime('2026-10-01 23:59:59 -03:00'), $fim);
  }

}
