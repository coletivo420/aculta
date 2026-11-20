<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\aculta_portal\Sales\SalesReportFilters;
use Drupal\aculta_portal\Sales\SalesOrdersTableBuilder;
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
    private readonly SalesOrdersTableBuilder $pedidosTabela,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.sales_report_query'),
      $container->get('config.factory'),
      $container->get('commerce_price.currency_formatter'),
      $container->get('aculta_portal.sales_orders_table_builder'),
      $container->get('string_translation'),
    );
  }

  /**
   * Página do relatório de vendas.
   *
   * @param Request $request
   *   Current HTTP request.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function report(Request $request): array {
    $timezoneConfig = $this->configFactory->get('system.date')->get('timezone.default');
    $timezoneName = is_string($timezoneConfig) && $timezoneConfig !== '' ? $timezoneConfig : self::FUSO_PADRAO;
    $fuso = new \DateTimeZone($timezoneName);
    $filtros = SalesReportFilters::fromQuery($request->query->all(), $fuso, new \DateTimeImmutable('now', $fuso));

    if ($filtros->erros !== []) {
      $this->messenger()->addWarning($this->t('Os filtros de data foram ajustados para o padrão. Confira o intervalo.'));
    }

    return [
      '#theme' => 'portal_component',
      '#name' => 'sales-panel',
      '#slots' => ['content' => [
        'por_variacao' => $this->secao($this->t('Vendas por variação'), $this->tabelaPorVariacao($filtros)),
        'por_periodo' => $this->secao($this->t('Vendas por período'), $this->tabelaPorPeriodo($filtros)),
        'pedidos' => $this->secao($this->t('Pedidos e pagamentos'), $this->pedidosTabela->construir($filtros, SalesReportQuery::ESCOPO_PRODUTO, $this->paginaAtual($request), 'aculta_portal.relatorio_vendas', $this->filtrosDaUrl($request))),
      ]],
      '#cache' => ['max-age' => 0, 'contexts' => ['url.query_args']],
    ];
  }

  /**
   * Seção do painel: título semântico e conteúdo. Aparência decidida pelo tema (tokens sales-*).
   *
   * @param mixed $titulo
   *   Value used for $titulo.
   * @param array<array-key, mixed> $conteudo
   *   Value used for $conteudo.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  private function secao(mixed $titulo, array $conteudo): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'sales-section'],
      'titulo' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $titulo,
      ],
      'conteudo' => $conteudo,
    ];
  }

  /**
   * Número da página atual da listagem, a partir da URL.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Requisição atual.
   *
   * @return int
   *   Página solicitada, nunca negativa.
   */
  private function paginaAtual(Request $request): int {
    return max(0, (int) $request->query->get('pagina', 0));
  }

  /**
   * Filtros da URL repassados à paginação (sem o parâmetro de página).
   *
   * @return array<string, string>
   *   Filtros da URL, sem o parâmetro de página.
   */
  private function filtrosDaUrl(Request $request): array {
    $filtros = [];
    foreach ($request->query->all() as $chave => $valor) {
      if ($chave !== 'pagina' && is_scalar($valor)) {
        $filtros[(string) $chave] = (string) $valor;
      }
    }
    return $filtros;
  }

  /**
   * Tabela de vendas por variação, com total por moeda no rodapé.
   *
   * @param SalesReportFilters $filtros
   *   Value used for $filtros.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
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
      $moeda = $linha['moeda'];
      $totalAtual = $totais[$moeda] ?? '0';
      $valorLinha = is_numeric($linha['total']) ? (string) $linha['total'] : '0';
      $totais[$moeda] = bcadd($totalAtual, $valorLinha, 2);
    }
    foreach ($totais as $moeda => $total) {
      $linhas[] = ['', '', $this->t('Total'), $this->currencyFormatter->format($total, (string) $moeda)];
    }

    return [
      '#type' => 'table',
      '#attributes' => ['data-portal-ui' => 'sales-table'],
      '#caption' => $this->t('Vendas por variação'),
      '#header' => [$this->t('Produto'), $this->t('Variação'), $this->t('Quantidade'), $this->t('Total')],
      '#rows' => $linhas,
      '#empty' => $this->t('Nenhuma venda de produto concluída no intervalo.'),
    ];
  }

  /**
   * Tabela de vendas por período e moeda.
   *
   * @param SalesReportFilters $filtros
   *   Value used for $filtros.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
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
      '#attributes' => ['data-portal-ui' => 'sales-table'],
      '#caption' => $this->t('Vendas por período'),
      '#header' => [$this->t('Período'), $this->t('Pedidos'), $this->t('Total'), $this->t('Moeda')],
      '#rows' => $linhas,
      '#empty' => $this->t('Nenhuma venda de produto concluída no intervalo.'),
    ];
  }

}
