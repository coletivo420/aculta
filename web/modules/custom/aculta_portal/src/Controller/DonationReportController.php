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
 * Relatório de doações para o gerente de loja (permissão administrar doacoes).
 *
 * Mostra pedidos e valor de doação por período e moeda, no mesmo intervalo e com os mesmos filtros do
 * relatório de vendas. Doações não entram no relatório de vendas de produto. Os valores vêm de
 * SalesReportQuery com ESCOPO_DOACAO. Visual dos painéis fica para a fase seguinte.
 */
final class DonationReportController implements ContainerInjectionInterface {

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
   * Página do relatório de doações.
   */
  public function report(Request $request): array {
    $fuso = new \DateTimeZone($this->configFactory->get('system.date')->get('timezone.default') ?: self::FUSO_PADRAO);
    $filtros = SalesReportFilters::fromQuery($request->query->all(), $fuso, new \DateTimeImmutable('now', $fuso));

    if ($filtros->erros !== []) {
      $this->messenger()->addWarning($this->t('Os filtros de data foram ajustados para o padrão. Confira o intervalo.'));
    }

    $linhas = [];
    foreach ($this->consultas->porPeriodo($filtros, SalesReportQuery::ESCOPO_DOACAO) as $linha) {
      $linhas[] = [
        $linha['periodo'],
        $linha['pedidos'],
        $this->currencyFormatter->format($linha['total'], $linha['moeda']),
        $linha['moeda'],
      ];
    }

    $tabela = [
      '#type' => 'table',
      '#attributes' => ['data-portal-ui' => 'sales-table'],
      '#header' => [
        $this->t('Período'),
        $this->t('Doações (pedidos)'),
        $this->t('Total'),
        $this->t('Moeda'),
      ],
      '#rows' => $linhas,
      '#empty' => $this->t('Nenhuma doação concluída no intervalo.'),
      '#cache' => ['max-age' => 0, 'contexts' => ['url.query_args']],
    ];
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'sales-panel'],
      'resumo' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'sales-section'],
        'titulo' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['data-portal-ui' => 'sales-heading'],
          '#value' => $this->t('Doações por período'),
        ],
        'conteudo' => $tabela,
      ],
      'pedidos' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'sales-section'],
        'titulo' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['data-portal-ui' => 'sales-heading'],
          '#value' => $this->t('Doações e pagamentos'),
        ],
        'conteudo' => $this->pedidosTabela->construir($filtros, SalesReportQuery::ESCOPO_DOACAO, $this->paginaAtual($request), 'aculta_portal.relatorio_doacoes', $this->filtrosDaUrl($request)),
      ],
    ];
  }

  private function paginaAtual(Request $request): int {
    return max(0, (int) $request->query->get('pagina', 0));
  }

  /**
   * Filtros da URL repassados à paginação (sem o parâmetro de página).
   *
   * @return array<string, string>
   */
  private function filtrosDaUrl(Request $request): array {
    return array_filter($request->query->all(), static fn ($valor, $chave): bool => $chave !== 'pagina' && is_scalar($valor), ARRAY_FILTER_USE_BOTH);
  }

}
