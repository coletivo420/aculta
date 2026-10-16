<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\aculta_portal\Sales\SalesReportFilters;
use Drupal\aculta_portal\Sales\SalesReportQuery;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Relatório de vendas de produto da loja (permissão view commerce_order).
 *
 * Duas tabelas no mesmo intervalo e com os mesmos filtros: vendas por variação e vendas por período.
 * Doações não entram aqui (ver DonationReportController). Os valores vêm de SalesReportQuery, que lê as
 * tabelas do Commerce e não carrega pedidos na memória. Visual dos painéis fica para a fase seguinte.
 */
final class SalesReportController implements ContainerInjectionInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * Fuso usado quando o site não define um.
   */
  private const FUSO_PADRAO = 'America/Sao_Paulo';

  public function __construct(
    private readonly SalesReportQuery $consultas,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly CurrencyFormatterInterface $currencyFormatter,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.sales_report_query'),
      $container->get('config.factory'),
      $container->get('commerce_price.currency_formatter'),
      $container->get('string_translation'),
    );
  }

  /**
   * Página do relatório de vendas.
   */
  public function report(Request $request): array {
    $fuso = new \DateTimeZone($this->configFactory->get('system.date')->get('timezone.default') ?: self::FUSO_PADRAO);
    $filtros = SalesReportFilters::fromQuery($request->query->all(), $fuso, new \DateTimeImmutable('now', $fuso));

    if ($filtros->erros !== []) {
      $this->messenger()->addWarning($this->t('Os filtros de data foram ajustados para o padrão. Confira o intervalo.'));
    }

    return [
      'por_variacao' => $this->tabelaPorVariacao($filtros),
      'por_periodo' => $this->tabelaPorPeriodo($filtros),
      '#cache' => ['max-age' => 0, 'contexts' => ['url.query_args']],
    ];
  }

  /**
   * Tabela de vendas por variação, com total por moeda no rodapé.
   */
  private function tabelaPorVariacao(SalesReportFilters $filtros): array {
    $linhas = [];
    $totais = [];
    foreach ($this->consultas->porVariacao($filtros) as $linha) {
      $linhas[] = [
        $linha['produto'],
        $linha['variacao'],
        $linha['quantidade'],
        $this->currencyFormatter->format($linha['total'], $linha['moeda']),
      ];
      $totais[$linha['moeda']] = bcadd($totais[$linha['moeda']] ?? '0', $linha['total'], 2);
    }
    foreach ($totais as $moeda => $total) {
      $linhas[] = ['', '', $this->t('Total'), $this->currencyFormatter->format($total, (string) $moeda)];
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Vendas por variação'),
      '#header' => [$this->t('Produto'), $this->t('Variação'), $this->t('Quantidade'), $this->t('Total')],
      '#rows' => $linhas,
      '#empty' => $this->t('Nenhuma venda de produto concluída no intervalo.'),
    ];
  }

  /**
   * Tabela de vendas por período e moeda.
   */
  private function tabelaPorPeriodo(SalesReportFilters $filtros): array {
    $linhas = [];
    foreach ($this->consultas->porPeriodo($filtros, SalesReportQuery::ESCOPO_PRODUTO) as $linha) {
      $linhas[] = [
        $linha['periodo'],
        $linha['pedidos'],
        $this->currencyFormatter->format($linha['total'], $linha['moeda']),
        $linha['moeda'],
      ];
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Vendas por período'),
      '#header' => [$this->t('Período'), $this->t('Pedidos'), $this->t('Total'), $this->t('Moeda')],
      '#rows' => $linhas,
      '#empty' => $this->t('Nenhuma venda de produto concluída no intervalo.'),
    ];
  }

}
