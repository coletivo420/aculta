<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Controller;

use Drupal\aculta_mailer\Chart\MailTrendChart;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Catalog\MetricDefinition;
use Drupal\aculta_statistics\Connector\Ga4\Ga4ReportClient;
use Drupal\aculta_statistics\Report\MetricSeries;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Painel central de estatísticas: uma métrica por vez, período de 7, 14 ou 30 dias, e exportação CSV.
 *
 * Métricas com sensibilidade R (dado pessoal por aluno) ficam fora do painel agregado.
 */
final class StatisticsDashboardController extends ControllerBase {

  /**
   * Métrica de contagem por domínio: a dimensão é `purpose|moeda` e o filtro de domínio soma os dias do purpose.
   *
   * A receita por domínio não tem esse filtro: somaria centavos de moedas diferentes.
   */
  private const DOMAIN_COUNT_METRIC = 'commerce.orders.by_domain';

  public function __construct(
    private readonly MetricCatalog $catalog,
    private readonly AggregateStorageInterface $storage,
    private readonly TimeInterface $time,
    private readonly Ga4ReportClient $ga4,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_statistics.catalog'),
      $container->get('aculta_statistics.storage'),
      $container->get('datetime.time'),
      $container->get(Ga4ReportClient::class),
    );
  }

  /**
   * Página do painel: métrica e período escolhidos na query (`metrica`, `dias`).
   */
  public function overview(Request $request): array {
    $metric = $this->selectedMetric($request);
    $days = MetricSeries::normalizePeriod($request->query->get('dias'));
    $today = $this->today();
    $domain = $this->selectedDomain($request, $metric, $today, $days);
    $series = MetricSeries::build($today, $days, fn (int $day): int => $this->dailyTotal($metric->id, $day, $domain));

    $table = [];
    foreach ($series['labels'] as $index => $label) {
      $table[] = [$label, $series['values'][$index]];
    }

    return [
      '#type' => 'container',
      'controles' => $this->controls($metric, $days, $domain, $today),
      'resumo' => [
        '#markup' => '<p><strong>' . $this->t('@total @unidade no período (@days dias)', [
          '@total' => $series['total'],
          '@unidade' => $metric->unit,
          '@days' => $days,
        ]) . '</strong></p>',
      ],
      'grafico' => [
        '#markup' => Markup::create(MailTrendChart::svg($series['labels'], [
          ['label' => $metric->label, 'color' => '#1f4e79', 'values' => $series['values']],
        ], (string) $this->t('@label nos últimos @days dias', ['@label' => $metric->label, '@days' => $days]))),
      ],
      'valores' => [
        '#type' => 'table',
        '#caption' => $this->t('Valores por dia'),
        '#header' => [$this->t('Dia'), $this->t('Total')],
        '#rows' => $table,
      ],
      'exportar' => [
        '#type' => 'link',
        '#title' => $this->t('Exportar CSV'),
        '#url' => Url::fromRoute('aculta_statistics.dashboard_export', [], ['query' => array_filter(['metrica' => $metric->id, 'dias' => $days, 'dominio' => $domain])]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'integracoes' => [
        '#markup' => '<p>' . $this->t('Google Analytics (GA4): @estado.', ['@estado' => $this->ga4->isEnabled() ? $this->t('ligado') : $this->t('desligado (sem propriedade e credencial configuradas)')]) . '</p>',
      ],
      '#cache' => ['max-age' => 0, 'contexts' => ['url.query_args']],
    ];
  }

  /**
   * CSV do período: uma linha por dia e dimensão (dia, dimensão, total).
   */
  public function export(Request $request): Response {
    $metric = $this->selectedMetric($request);
    $days = MetricSeries::normalizePeriod($request->query->get('dias'));
    $today = $this->today();
    $domain = $this->selectedDomain($request, $metric, $today, $days);

    $lines = ["dia,dimensao,total"];
    for ($day = $today - $days + 1; $day <= $today; $day++) {
      foreach ($this->storage->dimensions($metric->id, $day) as $dimension) {
        if ($domain !== NULL && !str_starts_with($dimension, $domain . '|')) {
          continue;
        }
        $lines[] = gmdate('Y-m-d', $day * 86400) . ',' . $this->csvField($dimension) . ',' . $this->storage->total($metric->id, $day, $day, $dimension);
      }
    }

    $filename = $metric->id . '-' . $days . 'd.csv';
    return new Response(implode("\n", $lines) . "\n", Response::HTTP_OK, [
      'Content-Type' => 'text/csv; charset=utf-8',
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
  }

  /**
   * Métrica pedida se existir e não for restrita; senão a primeira métrica agregada do catálogo.
   */
  private function selectedMetric(Request $request): MetricDefinition {
    $requested = (string) $request->query->get('metrica', '');
    $aggregated = $this->aggregatedMetrics();
    return $aggregated[$requested] ?? reset($aggregated);
  }

  /**
   * Domínio pedido, se a métrica suporta filtro e o valor existe no período; senão NULL (todos).
   */
  private function selectedDomain(Request $request, MetricDefinition $metric, int $today, int $days): ?string {
    $requested = (string) $request->query->get('dominio', '');
    if ($metric->id !== self::DOMAIN_COUNT_METRIC || $requested === '') {
      return NULL;
    }
    return in_array($requested, $this->domainsIn($metric, $today, $days), TRUE) ? $requested : NULL;
  }

  /**
   * Purposes que aparecem na métrica no período, ordenados.
   *
   * @return string[]
   */
  private function domainsIn(MetricDefinition $metric, int $today, int $days): array {
    $purposes = [];
    for ($day = $today - $days + 1; $day <= $today; $day++) {
      foreach ($this->storage->dimensions($metric->id, $day) as $dimension) {
        $purposes[explode('|', $dimension, 2)[0]] = TRUE;
      }
    }
    $list = array_keys($purposes);
    sort($list);
    return $list;
  }

  /**
   * Total de um dia; com domínio, soma só as dimensões daquele purpose.
   */
  private function dailyTotal(string $metric, int $day, ?string $domain): int {
    if ($domain === NULL) {
      return $this->storage->total($metric, $day, $day);
    }
    $sum = 0;
    foreach ($this->storage->dimensions($metric, $day) as $dimension) {
      if (str_starts_with($dimension, $domain . '|')) {
        $sum += $this->storage->total($metric, $day, $day, $dimension);
      }
    }
    return $sum;
  }

  /**
   * Métricas do catálogo que o painel agregado mostra (sensibilidade P ou I), ordenadas pelo identificador.
   *
   * @return array<string, \Drupal\aculta_statistics\Catalog\MetricDefinition>
   */
  private function aggregatedMetrics(): array {
    $metrics = array_filter(
      $this->catalog->all(),
      static fn (MetricDefinition $definition): bool => $definition->sensitivity !== MetricDefinition::SENSITIVITY_RESTRICTED,
    );
    ksort($metrics);
    return $metrics;
  }

  /**
   * Formulário de seleção (GET, sem JavaScript): métrica e período, com botão de aplicar.
   */
  private function controls(MetricDefinition $selected, int $days, ?string $domain = NULL, int $today = 0): array {
    $metricOptions = '';
    foreach ($this->aggregatedMetrics() as $id => $definition) {
      $metricOptions .= sprintf('<option value="%s"%s>%s</option>', Html::escape($id), $id === $selected->id ? ' selected' : '', Html::escape($definition->label));
    }
    $periodOptions = '';
    foreach (MetricSeries::PERIODS as $option) {
      $periodOptions .= sprintf('<option value="%d"%s>%s</option>', $option, $option === $days ? ' selected' : '', Html::escape((string) $this->t('Últimos @days dias', ['@days' => $option])));
    }
    $domainControl = '';
    if ($selected->id === self::DOMAIN_COUNT_METRIC) {
      $domainOptions = sprintf('<option value="">%s</option>', Html::escape((string) $this->t('Todos os domínios')));
      foreach ($this->domainsIn($selected, $today, $days) as $purpose) {
        $domainOptions .= sprintf('<option value="%s"%s>%s</option>', Html::escape($purpose), $purpose === $domain ? ' selected' : '', Html::escape($purpose));
      }
      $domainControl = sprintf(' <label>%s <select name="dominio">%s</select></label>', Html::escape((string) $this->t('Domínio')), $domainOptions);
    }
    $action = Html::escape(Url::fromRoute('aculta_statistics.dashboard')->toString());
    return [
      '#markup' => Markup::create(sprintf(
        '<form method="get" action="%s" class="aculta-statistics-controles"><label>%s <select name="metrica">%s</select></label> <label>%s <select name="dias">%s</select></label>%s <button type="submit">%s</button></form>',
        $action,
        Html::escape((string) $this->t('Métrica')),
        $metricOptions,
        Html::escape((string) $this->t('Período')),
        $periodOptions,
        $domainControl,
        Html::escape((string) $this->t('Aplicar')),
      )),
    ];
  }

  /**
   * Dia UTC atual, a partir do relógio do Drupal.
   */
  private function today(): int {
    return intdiv($this->time->getRequestTime(), 86400);
  }

  /**
   * Campo CSV com aspas quando contém vírgula, aspas ou quebra de linha.
   */
  private function csvField(string $value): string {
    return preg_match('/[",\n]/', $value) === 1 ? '"' . str_replace('"', '""', $value) . '"' : $value;
  }

}
