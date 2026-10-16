<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Controller;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\aculta_portal\Controller\DonationReportController;
use Drupal\aculta_portal\Sales\SalesReportFilters;
use Drupal\aculta_portal\Sales\SalesOrdersTableBuilder;
use Drupal\aculta_portal\Sales\SalesReportQuery;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Relatório de doações: escopo de doação, formatação e tabela vazia.
 */
#[Group('aculta_portal')]
final class DonationReportControllerTest extends UnitTestCase {

  private function controller(SalesReportQuery $consultas): DonationReportController {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('timezone.default')->willReturn('America/Sao_Paulo');
    $configs = $this->createMock(ConfigFactoryInterface::class);
    $configs->method('get')->with('system.date')->willReturn($config);

    $formatador = $this->createMock(CurrencyFormatterInterface::class);
    $formatador->method('format')->willReturnCallback(static fn ($numero, $moeda) => number_format((float) $numero, 2, ',', '.') . ' ' . $moeda);

    $traducao = $this->createMock(TranslationInterface::class);
    $traducao->method('translateString')->willReturnCallback(static fn ($markup) => $markup->getUntranslatedString());

    $pedidos = $this->createMock(SalesOrdersTableBuilder::class);
    $pedidos->method('construir')->willReturn(['tabela' => []]);

    return new DonationReportController($consultas, $configs, $formatador, $pedidos, $traducao);
  }

  public function testConsultaSoDoEscopoDeDoacaoEFormataAsLinhas(): void {
    $consultas = $this->createMock(SalesReportQuery::class);
    $consultas->expects($this->once())
      ->method('porPeriodo')
      ->with($this->isInstanceOf(SalesReportFilters::class), SalesReportQuery::ESCOPO_DOACAO)
      ->willReturn([
        ['periodo' => '2026-10', 'pedidos' => 2, 'total' => '60.00', 'moeda' => 'BRL'],
      ]);

    $render = $this->controller($consultas)->report(Request::create('/painel-administrativo/loja/doacoes', 'GET', ['de' => '2026-10-01', 'ate' => '2026-10-16']));

    $this->assertSame([['2026-10', 2, '60,00 BRL', 'BRL']], $render['resumo']['conteudo']['#rows']);
    $this->assertArrayHasKey('#empty', $render['resumo']['conteudo']);
  }

  public function testSemDoacoesMostraTabelaVazia(): void {
    $consultas = $this->createMock(SalesReportQuery::class);
    $consultas->method('porPeriodo')->willReturn([]);

    $render = $this->controller($consultas)->report(Request::create('/painel-administrativo/loja/doacoes'));

    $this->assertSame([], $render['resumo']['conteudo']['#rows']);
    $this->assertSame(['max-age' => 0, 'contexts' => ['url.query_args']], $render['resumo']['conteudo']['#cache']);
  }

}
