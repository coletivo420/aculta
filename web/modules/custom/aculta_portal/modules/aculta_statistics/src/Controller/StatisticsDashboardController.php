<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Controller;

use Drupal\aculta_mailer\Chart\MailTrendChart;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Catalog\MetricDefinition;
use Drupal\aculta_statistics\Connector\Ga4\Ga4ReportClient;
use Drupal\aculta_statistics\Connector\SearchConsole\GoogleSearchConsoleClient;
use Drupal\aculta_statistics\Report\MetricSeries;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\aculta_statistics\Wiki\WikiRevisionReport;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

  /**
   * Áreas do painel: rótulo e prefixo das métricas do catálogo que pertencem a cada área.
   */
  public const AREAS = [
    'vendas' => ['Vendas', 'commerce.'],
    'email' => ['E-mail', 'email.'],
    'lms' => ['Cursos', 'lms.'],
    'links' => ['Links', 'links.'],
    'forum' => ['Fórum', 'forum.'],
    'midia' => ['Mídia', 'media.'],
    'wiki' => ['Wiki', 'wiki.'],
  ];

  public function __construct(
    private readonly MetricCatalog $catalog,
    private readonly AggregateStorageInterface $storage,
    private readonly TimeInterface $time,
    private readonly Ga4ReportClient $ga4,
    private readonly GoogleSearchConsoleClient $searchConsole,
    private readonly WikiRevisionReport $wikiRevisions,
    private readonly DateFormatterInterface $dateFormatter,
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
      $container->get('aculta_statistics.gsc_client'),
      $container->get(WikiRevisionReport::class),
      $container->get('date.formatter'),
    );
  }

  /**
   * Página do painel: métrica e período escolhidos na query (`metrica`, `dias`).
   */
  public function overview(Request $request, ?string $area = NULL): array {
    if ($area !== NULL && !array_key_exists($area, self::AREAS)) {
      throw new NotFoundHttpException();
    }
    $metric = $this->selectedMetric($request, $area);
    $days = MetricSeries::normalizePeriod($request->query->get('dias'));
    $today = $this->today();
    $domain = $this->selectedDomain($request, $metric, $today, $days);
    $series = MetricSeries::build($today, $days, fn (int $day): int => $this->dailyTotal($metric->id, $day, $domain));
    $periodValue = $this->periodValue($metric, $today, $days, $domain);
    $previous = $this->previousPeriodValue($metric, $today, $days, $domain);
    $summaryLabel = $metric->isAdditive()
      ? $this->t('@total @unidade no período (@days dias)', [
        '@total' => $periodValue ?? $this->t('N/D'),
        '@unidade' => $metric->unit,
        '@days' => $days,
      ])
      : $this->t('Último valor: @total @unidade (retrato do dia; não somar no período)', [
        '@total' => $periodValue ?? $this->t('N/D'),
        '@unidade' => $metric->unit,
      ]);
    $comparisonMarkup = $previous === NULL || $periodValue === NULL
      ? (string) $this->t('Comparação indisponível: coleta insuficiente para o período anterior.')
      : (string) $this->t('Período anterior (mesmo tamanho): @anterior @unidade. Variação: @variacao. A variação mostra só a diferença entre períodos, não sua causa.', [
        '@anterior' => $previous,
        '@unidade' => $metric->unit,
        '@variacao' => $this->variation($periodValue, $previous),
      ]);

    $table = [];
    foreach ($series['labels'] as $index => $label) {
      $table[] = [$label, $series['values'][$index]];
    }

    $ga4State = $this->ga4->isEnabled()
      ? $this->t('ligado')
      : $this->t('desligado (sem propriedade e credencial configuradas)');
    $gscState = $this->searchConsole->isEnabled()
      ? $this->t('ligado')
      : $this->t('desligado (sem propriedade e credencial configuradas)');

    $build = [
      '#type' => 'container',
      '#attached' => ['library' => ['aculta_statistics/dashboard']],
      '#attributes' => ['data-portal-ui' => 'statistics-panel'],
      'areas' => $this->areaNavigation($area),
      'controles' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'statistics-controls'],
        'form' => $this->controls($metric, $days, $domain, $today, $area),
      ],
      'resumo' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'statistics-summary'],
        '#markup' => '<p><strong>' . $summaryLabel . '</strong></p>',
      ],
      'comparacao' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'statistics-comparison'],
        '#markup' => '<p>' . $comparisonMarkup . '</p>',
      ],
      'grafico' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'statistics-chart'],
        '#markup' => Markup::create(MailTrendChart::svg($series['labels'], [
          ['label' => $metric->label, 'color' => '#1f4e79', 'values' => $series['values']],
        ], (string) $this->t('@label nos últimos @days dias', ['@label' => $metric->label, '@days' => $days]))),
      ],
      'valores' => [
        '#type' => 'table',
        '#attributes' => ['data-portal-ui' => 'statistics-table'],
        '#caption' => $this->t('Valores por dia'),
        '#header' => [$this->t('Dia'), $this->t('Total')],
        '#rows' => $table,
      ],
      'exportar' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'statistics-note'],
        'link' => [
          '#type' => 'link',
          '#title' => $this->t('Exportar CSV'),
          '#url' => Url::fromRoute('aculta_statistics.dashboard_export', [], [
            'query' => array_filter([
              'metrica' => $metric->id,
              'dias' => $days,
              'dominio' => $domain,
            ]),
          ]),
        ],
      ],
      'integracoes' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'statistics-note'],
        '#markup' => '<p>' . $this->t('Google Analytics (GA4): @estado.', ['@estado' => $ga4State]) . '</p>'
          . '<p>' . $this->t('Search Console: @estado.', ['@estado' => $gscState]) . '</p>',
      ],
      '#cache' => ['max-age' => 0, 'contexts' => ['url.query_args']],
    ];

    // Relatórios do Fórum: cartões, saúde das respostas e moderação.
    if ($area === 'forum') {
      $build['forum'] = $this->forumPanels($today, $days, $series, $metric, $periodValue);
    }

    // O ranking é por verbete e só faz sentido na área Wiki.
    if ($area === 'wiki') {
      $build['ranking'] = $this->wikiRanking($today, $days);
      $build['revisoes'] = $this->wikiRevisionsTable();
    }

    // Atalho para os prazos, só para quem administra a retenção. A subaba do
    // menu já leva lá; o link no painel evita procurar o menu.
    if ($this->currentUser()->hasPermission('administer aculta_statistics')) {
      $build['retencao'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'statistics-note'],
        'link' => [
          '#type' => 'link',
          '#title' => $this->t('Prazos de retenção'),
          '#url' => Url::fromRoute('aculta_statistics.retention'),
        ],
      ];
    }

    return $build;
  }

  /**
   * Navegação entre as áreas do painel (item list com token, sem classes de design).
   */
  private function areaNavigation(?string $current): array {
    $items = [];
    $items[] = [
      '#type' => 'link',
      '#title' => $this->t('Todas as métricas'),
      '#url' => Url::fromRoute('aculta_statistics.dashboard'),
    ];
    foreach (self::AREAS as $id => [$label]) {
      $items[] = [
        '#type' => 'link',
        '#title' => $this->t('@label', ['@label' => $label]),
        '#url' => Url::fromRoute('aculta_statistics.area', ['area' => $id]),
      ];
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['data-portal-ui' => 'statistics-areas', 'data-area-atual' => $current ?? 'todas'],
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
   * CSV do ranking da Wiki: posição, título atual do verbete e leituras no período.
   */
  public function wikiRankingExport(Request $request): Response {
    $days = MetricSeries::normalizePeriod($request->query->get('dias'));
    $lines = ['posicao,verbete,leituras'];
    foreach ($this->wikiRankingEntries($this->today(), $days) as $position => $entry) {
      $lines[] = ($position + 1) . ',' . $this->csvField((string) $entry['node']->label()) . ',' . $entry['reads'];
    }

    $filename = 'wiki-ranking-' . $days . 'd.csv';
    return new Response(implode("\n", $lines) . "\n", Response::HTTP_OK, [
      'Content-Type' => 'text/csv; charset=utf-8',
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
  }

  /**
   * CSV do histórico de edição: uma linha por verbete, sem corte.
   */
  public function wikiRevisionExport(): Response {
    $lines = ['verbete,revisoes,publicadas,rascunhos,ultima_edicao'];
    foreach ($this->wikiRevisions->report(NULL)['entries'] as $entry) {
      $lines[] = $this->csvField((string) $entry['node']->label())
        . ',' . $entry['revisions']
        . ',' . $entry['published']
        . ',' . $entry['drafts']
        . ',' . ($entry['last_edit'] !== NULL ? gmdate('c', $entry['last_edit']) : '');
    }

    return new Response(implode("\n", $lines) . "\n", Response::HTTP_OK, [
      'Content-Type' => 'text/csv; charset=utf-8',
      'Content-Disposition' => 'attachment; filename="wiki-revisoes.csv"',
    ]);
  }

  /**
   * Edição e revisão por verbete, a partir do histórico de revisões do Node.
   *
   * É um retrato do histórico atual, não do período escolhido: revisões antigas
   * continuam contadas. Nada é gravado nem entra na retenção.
   */
  private function wikiRevisionsTable(): array {
    $report = $this->wikiRevisions->report();
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'statistics-wiki-revisions'],
    ];
    if ($report['entries'] === []) {
      $build['vazio'] = [
        '#markup' => '<p>' . $this->t('Nenhum verbete com histórico de revisão.') . '</p>',
      ];
      return $build;
    }

    $build['resumo'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'statistics-note'],
      '#markup' => '<p>' . $this->t('@entries verbetes com histórico, @revisions revisões, @drafts em rascunho. Retrato do histórico atual, independente do período.', [
        '@entries' => $report['total_entries'],
        '@revisions' => $report['total_revisions'],
        '@drafts' => $report['total_drafts'],
      ]) . '</p>',
    ];

    $rows = [];
    foreach ($report['entries'] as $entry) {
      $lastEdit = $entry['last_edit'] !== NULL
        ? $this->dateFormatter->format($entry['last_edit'], 'short')
        : (string) $this->t('Sem revisão registrada');
      $rows[] = [
        ['data' => ['#type' => 'link', '#title' => (string) $entry['node']->label(), '#url' => $entry['node']->toUrl()]],
        $entry['revisions'],
        $entry['published'],
        $entry['drafts'],
        $lastEdit,
      ];
    }
    $build['tabela'] = [
      '#type' => 'table',
      '#caption' => $this->t('Edição e revisão por verbete'),
      '#header' => [
        $this->t('Verbete'),
        $this->t('Revisões'),
        $this->t('Publicadas'),
        $this->t('Rascunhos'),
        $this->t('Última edição'),
      ],
      '#rows' => $rows,
    ];
    if ($report['limit'] !== NULL && $report['total_entries'] > $report['limit']) {
      $build['corte'] = [
        '#markup' => '<p>' . $this->t('Mostrando os @shown primeiros de @entries; o CSV traz todos.', [
          '@shown' => count($report['entries']),
          '@entries' => $report['total_entries'],
        ]) . '</p>',
      ];
    }
    $build['exportar'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'statistics-note'],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Exportar histórico (CSV)'),
        '#url' => Url::fromRoute('aculta_statistics.wiki_revision_export'),
      ],
    ];
    return $build;
  }

  /**
   * Ranking dos verbetes mais lidos no período, com o título resolvido na renderização.
   */
  private function wikiRanking(int $today, int $days): array {
    $entries = $this->wikiRankingEntries($today, $days);
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'statistics-wiki-ranking'],
    ];
    if ($entries === []) {
      $build['vazio'] = [
        '#markup' => '<p>' . $this->t('Nenhuma leitura de verbete no período.') . '</p>',
      ];
      return $build;
    }

    $rows = [];
    foreach ($entries as $position => $entry) {
      $rows[] = [
        $position + 1,
        ['data' => ['#type' => 'link', '#title' => (string) $entry['node']->label(), '#url' => $entry['node']->toUrl()]],
        $entry['reads'],
      ];
    }
    $build['tabela'] = [
      '#type' => 'table',
      '#caption' => $this->t('Verbetes mais lidos no período'),
      '#header' => [$this->t('Posição'), $this->t('Verbete'), $this->t('Leituras')],
      '#rows' => $rows,
    ];
    $build['exportar'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'statistics-note'],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Exportar ranking (CSV)'),
        '#url' => Url::fromRoute('aculta_statistics.wiki_ranking_export', [], ['query' => ['dias' => $days]]),
      ],
    ];
    return $build;
  }

  /**
   * Verbetes com leitura no período, do mais lido para o menos lido.
   *
   * A dimensão guardada é o ID do verbete. Os títulos são carregados agora:
   * verbete excluído não carrega e sai do ranking sem erro.
   *
   * @return array<int, array{node: \Drupal\node\NodeInterface, reads: int}>
   *   Verbete e leituras no período, na ordem do ranking.
   */
  private function wikiRankingEntries(int $today, int $days): array {
    if (!$this->entityTypeManager()->hasDefinition('node')) {
      return [];
    }
    $totals = [];
    foreach ($this->storage->dimensionTotals(WikiReadController::READ_METRIC, $today - $days + 1, $today) as $dimension => $reads) {
      // Dimensão numérica vira chave inteira no array do PHP.
      if (preg_match('/^[1-9][0-9]*$/', (string) $dimension) === 1) {
        $totals[(int) $dimension] = $reads;
      }
    }
    if ($totals === []) {
      return [];
    }

    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple(array_keys($totals));
    $entries = [];
    foreach ($totals as $nid => $reads) {
      $node = $nodes[$nid] ?? NULL;
      if ($node !== NULL && $node->bundle() === WikiReadController::BUNDLE) {
        $entries[] = ['node' => $node, 'reads' => $reads];
      }
    }
    return $entries;
  }

  /**
   * Métrica pedida se existir e não for restrita; senão a primeira métrica agregada do catálogo.
   */
  private function selectedMetric(Request $request, ?string $area = NULL): MetricDefinition {
    $requested = (string) $request->query->get('metrica', '');
    $aggregated = $this->aggregatedMetrics($area);
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
   *   Purposes presentes no período.
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
   * Valor do período: soma para contadores, último retrato para as demais.
   */
  private function periodValue(MetricDefinition $metric, int $today, int $days, ?string $domain): ?int {
    if ($metric->isAdditive()) {
      $total = 0;
      for ($day = $today - $days + 1; $day <= $today; $day++) {
        $total += $this->dailyTotal($metric->id, $day, $domain);
      }
      return $total;
    }
    return $this->derivedValueAt($metric, $today, $domain);
  }

  /**
   * Valor comparável do período anterior, sem somar retratos.
   */
  private function previousPeriodValue(MetricDefinition $metric, int $today, int $days, ?string $domain): ?int {
    if ($metric->isAdditive()) {
      $total = 0;
      for ($day = $today - 2 * $days + 1; $day <= $today - $days; $day++) {
        $total += $this->dailyTotal($metric->id, $day, $domain);
      }
      return $total;
    }
    return $this->derivedValueAt($metric, $today - $days, $domain);
  }

  /**
   * Valor de métrica de estado no dia; NULL quando não houve coleta.
   */
  private function derivedValueAt(MetricDefinition $metric, int $day, ?string $domain): ?int {
    if (!$this->storage->hasDay($metric->id, $day)) {
      return NULL;
    }
    return $this->dailyTotal($metric->id, $day, $domain);
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
   *   Métricas agregadas, indexadas pelo identificador.
   */
  private function aggregatedMetrics(?string $area = NULL): array {
    $prefix = $area === NULL ? NULL : self::AREAS[$area][1];
    $metrics = array_filter(
      $this->catalog->all(),
      static fn (MetricDefinition $definition): bool => $definition->sensitivity !== MetricDefinition::SENSITIVITY_RESTRICTED
        && ($prefix === NULL || str_starts_with($definition->id, $prefix)),
    );
    ksort($metrics);
    return $metrics;
  }

  /**
   * Relatórios do Fórum: indicadores, tendência, atividade, respostas e moderação.
   */
  private function forumPanels(int $today, int $days, array $series, MetricDefinition $metric, ?int $periodValue): array {
    $panels = $this->element('statistics-forum');

    $cards = [
      [
        'label' => $this->t('Tópicos publicados'),
        'value' => $this->forumValue('forum.topics.published_current', $today),
        'unit' => $this->t('tópicos'),
        'hint' => $this->t('Estoque atual do Fórum público.'),
      ],
      [
        'label' => $this->t('Respostas publicadas'),
        'value' => $this->forumValue('forum.replies.published_current', $today),
        'unit' => $this->t('respostas'),
        'hint' => $this->t('Comments legados e ForumPosts publicados.'),
      ],
      [
        'label' => $this->t('Pessoas ativas em 30 dias'),
        'value' => $this->forumValue('forum.participants.engaged_30d', $today),
        'unit' => $this->t('pessoas'),
        'hint' => $this->t('Autoria distinta, não visitas.'),
      ],
      [
        'label' => $this->t('Tópicos sem resposta'),
        'value' => $this->forumValue('forum.topics.unanswered', $today),
        'unit' => $this->t('tópicos'),
        'hint' => $this->t('Sem resposta pública de outra pessoa.'),
        'tone' => 'warning',
      ],
      [
        'label' => $this->t('Reações vigentes'),
        'value' => $this->forumValue('forum.reactions.active', $today),
        'unit' => $this->t('reações'),
        'hint' => $this->t('Somente conteúdo público.'),
      ],
    ];
    $cards_build = $this->element('statistics-forum-kpis');
    foreach ($cards as $index => $card) {
      $cards_build['card_' . $index] = $this->metricCard($card);
    }
    $panels['indicadores'] = $cards_build;

    $contentRanking = $this->forumContentRanking($today, $days);
    if ($contentRanking !== NULL) {
      $panels['conteudos'] = $contentRanking;
    }
    $panels['tendencia'] = $this->trendChart($series, $metric, $days, $periodValue);
    $panels['atividade'] = $this->activitySummary($today, $days);
    $panels['respostas'] = $this->responseHealth($today);
    $panels['moderacao'] = $this->moderationSummary($today);
    return $panels;
  }

  /**
   * Cartão de indicador com token do tema e valores escapados.
   */
  private function metricCard(array $card): array {
    $tone = $card['tone'] ?? 'neutral';
    $attributes = ['data-portal-ui' => 'statistics-metric-card'];
    if ($tone !== 'neutral') {
      $attributes['class'] = ['statistics-metric-card--' . $tone];
    }
    $unit = (string) $card['unit'];
    $value = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'statistics-metric-card__value'],
      'number' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['data-portal-ui' => 'statistics-metric-card__number'],
        '#value' => (string) $card['value'],
      ],
    ];
    if ($unit !== '') {
      $value['unit'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['data-portal-ui' => 'statistics-metric-card__unit'],
        '#value' => $unit,
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => $attributes,
      'label' => $this->paragraph(
        'statistics-metric-card__label',
        (string) $card['label'],
      ),
      'value' => $value,
      'hint' => $this->paragraph(
        'statistics-metric-card__hint',
        (string) $card['hint'],
      ),
    ];
  }

  /**
   * Detalhamento por tópico público, com acesso revalidado antes da consulta.
   *
   * A dimensão guardada é o ID do nó. Carregamos no máximo 100 IDs do período,
   * revalidamos `view` para cada entidade e só então exibimos o ranking.
   */
  private function forumContentRanking(int $today, int $days): ?array {
    if (!$this->currentUser()->hasPermission('view content-level statistics')
      && !$this->currentUser()->hasPermission('administer aculta_statistics')) {
      return NULL;
    }
    $totals = $this->storage->dimensionTotals(
      'forum.replies.created',
      $today - $days + 1,
      $today,
    );
    $ids = [];
    foreach ($totals as $dimension => $value) {
      if (preg_match('/^[1-9][0-9]*$/', (string) $dimension) === 1) {
        $ids[(int) $dimension] = (int) $value;
      }
    }
    $caption = (string) $this->t('Tópicos públicos com mais respostas no período');
    if ($ids === []) {
      return $this->element('statistics-category-ranking', [
        'empty' => $this->paragraph('statistics-category-ranking__empty', (string) $this->t('Sem dados no período.')),
      ]);
    }
    arsort($ids);
    $ids = array_slice($ids, 0, 100, TRUE);
    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple(array_keys($ids));
    $rows = [];
    $position = 0;
    foreach ($ids as $nid => $value) {
      $node = $nodes[$nid] ?? NULL;
      if ($node === NULL
        || $node->bundle() !== 'aculta_forum_topic'
        || !$node->access('view', $this->currentUser(), TRUE)->isAllowed()) {
        continue;
      }
      $position++;
      $rows[] = [
        $position,
        ['data' => ['#type' => 'link', '#title' => (string) $node->label(), '#url' => $node->toUrl()]],
        $this->formatNumber($value),
      ];
      if ($position >= 10) {
        break;
      }
    }
    return $this->element('statistics-category-ranking', [
      'table' => [
        '#type' => 'table',
        '#caption' => $caption,
        '#header' => [$this->t('Posição'), $this->t('Tópico'), $this->t('Respostas')],
        '#rows' => $rows,
        '#empty' => $this->t('Sem dados no período.'),
      ],
    ]);
  }

  /**
   * Série escolhida como tabela acessível de evolução diária.
   */
  private function trendChart(array $series, MetricDefinition $metric, int $days, ?int $periodValue): array {
    if ($periodValue === NULL && array_sum($series['values']) === 0) {
      return $this->emptyState(
        (string) $this->t('Ainda não há coleta deste indicador no período.'),
        (string) $this->t('A coleta começa no primeiro cron após a instalação.'),
      );
    }
    $rows = [];
    foreach ($series['labels'] as $index => $label) {
      $rows[] = [(string) $label, (string) $series['values'][$index]];
    }
    return $this->element('statistics-trend-chart', [
      'title' => $this->paragraph(
        'statistics-trend-chart__title',
        (string) $this->t('@label nos últimos @days dias', ['@label' => $metric->label, '@days' => $days]),
      ),
      'summary' => $this->paragraph(
        'statistics-trend-chart__summary',
        (string) $this->t('Atividade diária em UTC. Retratos não são somados.'),
      ),
      'table' => [
        '#type' => 'table',
        '#caption' => (string) $this->t('Evolução diária'),
        '#header' => [$this->t('Dia'), $this->t('Total')],
        '#rows' => $rows,
      ],
      'caption' => $this->paragraph(
        'statistics-trend-chart__caption',
        (string) $this->t('Fonte: ACULTA Statistics.'),
      ),
    ]);
  }

  /**
   * Resumo de criação no período.
   */
  private function activitySummary(int $today, int $days): array {
    return $this->summaryCard(
      (string) $this->t('Atividade do Fórum no período'),
      [
        [$this->t('Tópicos criados'), $this->periodSum('forum.topics.created', $today, $days)],
        [$this->t('Comments legados criados'), $this->periodSum('forum.comments.created', $today, $days)],
        [$this->t('ForumPosts criados'), $this->periodSum('forum.posts.created', $today, $days)],
        [$this->t('Respostas por tópico'), $this->periodSum('forum.replies.created', $today, $days)],
      ],
      (string) $this->t('Somente conteúdo público do Fórum; Revista, Wiki e LMS não entram nestes totais.'),
    );
  }

  /**
   * Saúde das respostas: mediana, p90, sem resposta e taxa.
   */
  private function responseHealth(int $today): array {
    $current = $this->storage->hasDay('forum.topics.published_current', $today)
      ? $this->storage->total('forum.topics.published_current', $today, $today)
      : NULL;
    $unanswered = $this->storage->hasDay('forum.topics.unanswered', $today)
      ? $this->storage->total('forum.topics.unanswered', $today, $today)
      : NULL;
    $rate = $current !== NULL && $current > 0 && $unanswered !== NULL
      ? round(($current - $unanswered) / $current * 100) . '%'
      : (string) $this->t('N/D');
    return $this->summaryCard(
      (string) $this->t('Saúde das respostas'),
      [
        [$this->t('Mediana até a primeira resposta'), $this->minutesValue('forum.response.first_median_minutes', $today)],
        [$this->t('Percentil 90'), $this->minutesValue('forum.response.first_p90_minutes', $today)],
        [$this->t('Sem resposta após 72 horas'), $this->forumValue('forum.response.unanswered_over_72h', $today)],
        [$this->t('Taxa de tópicos respondidos'), $rate],
      ],
      (string) $this->t('Coorte de tópicos criados nos últimos 365 dias; cálculo com estoque atual, não soma de percentuais diários.'),
    );
  }

  /**
   * Moderação: estado corrente sem identificar denunciantes ou alvos.
   */
  private function moderationSummary(int $today): array {
    return $this->summaryCard(
      (string) $this->t('Moderação do Fórum'),
      [
        [$this->t('Conteúdos com denúncia aberta'), $this->forumValue('forum.moderation.reports_open', $today)],
        [$this->t('Posts aguardando moderação'), $this->forumValue('forum.moderation.posts_pending', $today)],
      ],
      (string) $this->t('Denunciantes e alvos não são expostos. Denúncias resolvidas e tempo de resolução dependem de instrumentação do owner.'),
    );
  }

  /**
   * Cartão de resumo com tabela de indicador e valor.
   */
  private function summaryCard(string $title, array $rows, string $note = ''): array {
    $build = $this->element('statistics-summary-card', [
      'title' => $this->paragraph('statistics-summary-card__title', $title),
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Indicador'), $this->t('Valor')],
        '#rows' => $rows,
      ],
    ]);
    if ($note !== '') {
      $build['note'] = $this->paragraph('statistics-summary-card__note', $note);
    }
    return $build;
  }

  /**
   * Contêiner neutro identificado por token do tema.
   */
  private function element(string $token, array $children = []): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => $token],
    ];
    foreach ($children as $key => $child) {
      $build[$key] = $child;
    }
    return $build;
  }

  /**
   * Parágrafo com classe neutra e texto escapado.
   */
  private function paragraph(string $class, string $text): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['data-portal-ui' => $class],
      '#value' => $text,
    ];
  }

  /**
   * Valor do retrato do dia, ou N/D quando não houve coleta.
   */
  private function forumValue(string $metric, int $today): string {
    if (!$this->storage->hasDay($metric, $today)) {
      return (string) $this->t('N/D');
    }
    return $this->formatNumber($this->storage->total($metric, $today, $today));
  }

  /**
   * Minutos formatados para a saúde das respostas.
   */
  private function minutesValue(string $metric, int $today): string {
    if (!$this->storage->hasDay($metric, $today)) {
      return (string) $this->t('N/D');
    }
    return $this->formatNumber($this->storage->total($metric, $today, $today)) . ' ' . $this->t('min');
  }

  /**
   * Soma de um contador no período.
   */
  private function periodSum(string $metric, int $today, int $days): string {
    return $this->formatNumber($this->storage->total($metric, $today - $days + 1, $today));
  }

  /**
   * Número inteiro com separador de milhar.
   */
  private function formatNumber(int $value): string {
    return number_format($value, 0, ',', '.');
  }

  /**
   * Mensagem de estado vazio reutilizável.
   */
  private function emptyState(string $message, string $hint = ''): array {
    $build = $this->element('statistics-empty-state', [
      'message' => $this->paragraph('statistics-empty-state__message', $message),
    ]);
    if ($hint !== '') {
      $build['hint'] = $this->paragraph('statistics-empty-state__hint', $hint);
    }
    return $build;
  }

  /**
   * Formulário de seleção (GET, sem JavaScript): métrica e período, com botão de aplicar.
   */
  private function controls(MetricDefinition $selected, int $days, ?string $domain = NULL, int $today = 0, ?string $area = NULL): array {
    $metricOptions = '';
    foreach ($this->aggregatedMetrics($area) as $id => $definition) {
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
    $action = Html::escape($area === NULL ? Url::fromRoute('aculta_statistics.dashboard')->toString() : Url::fromRoute('aculta_statistics.area', ['area' => $area])->toString());
    return [
      '#markup' => Markup::create(sprintf(
        '<form method="get" action="%s" data-portal-ui="statistics-form"><label>%s <select name="metrica">%s</select></label> <label>%s <select name="dias">%s</select></label>%s <button type="submit">%s</button></form>',
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
   * Variação percentual entre o período atual e o anterior; "sem base" quando o anterior é zero.
   */
  private function variation(int $current, int $previous): string {
    if ($previous === 0) {
      return (string) $this->t('sem base de comparação');
    }
    $percent = (int) round(($current - $previous) / $previous * 100);
    return sprintf('%+d%%', $percent);
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
