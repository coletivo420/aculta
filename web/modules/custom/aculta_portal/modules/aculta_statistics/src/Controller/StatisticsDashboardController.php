<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Controller;

use Drupal\aculta_mailer\Chart\MailTrendChart;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Catalog\MetricDefinition;
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

  public function __construct(
    private readonly MetricCatalog $catalog,
    private readonly AggregateStorageInterface $storage,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_statistics.catalog'),
      $container->get('aculta_statistics.storage'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Página do painel: métrica e período escolhidos na query (`metrica`, `dias`).
   */
  public function overview(Request $request): array {
    $metric = $this->selectedMetric($request);
    $days = MetricSeries::normalizePeriod($request->query->get('dias'));
    $today = $this->today();
    $series = MetricSeries::build($today, $days, fn (int $day): int => $this->storage->total($metric->id, $day, $day));

    $table = [];
    foreach ($series['labels'] as $index => $label) {
      $table[] = [$label, $series['values'][$index]];
    }

    return [
      '#type' => 'container',
      'controles' => $this->controls($metric, $days),
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
        '#url' => Url::fromRoute('aculta_statistics.dashboard_export', [], ['query' => ['metrica' => $metric->id, 'dias' => $days]]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
    ];
  }

  /**
   * CSV do período: uma linha por dia e dimensão (dia, dimensão, total).
   */
  public function export(Request $request): Response {
    $metric = $this->selectedMetric($request);
    $days = MetricSeries::normalizePeriod($request->query->get('dias'));
    $today = $this->today();

    $lines = ["dia,dimensao,total"];
    for ($day = $today - $days + 1; $day <= $today; $day++) {
      foreach ($this->storage->dimensions($metric->id, $day) as $dimension) {
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
  private function controls(MetricDefinition $selected, int $days): array {
    $metricOptions = '';
    foreach ($this->aggregatedMetrics() as $id => $definition) {
      $metricOptions .= sprintf('<option value="%s"%s>%s</option>', Html::escape($id), $id === $selected->id ? ' selected' : '', Html::escape($definition->label));
    }
    $periodOptions = '';
    foreach (MetricSeries::PERIODS as $option) {
      $periodOptions .= sprintf('<option value="%d"%s>%s</option>', $option, $option === $days ? ' selected' : '', Html::escape((string) $this->t('Últimos @days dias', ['@days' => $option])));
    }
    $action = Html::escape(Url::fromRoute('aculta_statistics.dashboard')->toString());
    return [
      '#markup' => Markup::create(sprintf(
        '<form method="get" action="%s" class="aculta-statistics-controles"><label>%s <select name="metrica">%s</select></label> <label>%s <select name="dias">%s</select></label> <button type="submit">%s</button></form>',
        $action,
        Html::escape((string) $this->t('Métrica')),
        $metricOptions,
        Html::escape((string) $this->t('Período')),
        $periodOptions,
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
