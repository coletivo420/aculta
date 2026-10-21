<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Controller;

use Drupal\aculta_mailer\Dashboard\MailDashboard;
use Drupal\aculta_mailer\Diagnostics\MailDiagnostics;
use Drupal\aculta_mailer\Chart\MailTrendChart;
use Drupal\aculta_mailer\Diagnostics\SenderDomainCheck;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Painel de envios: um quadro por período, com contagens. Sem destinatário e sem conteúdo.
 */
final class MailDashboardController extends ControllerBase {

  /**
   * Rótulos em português dos estados do registro (MailStatus).
   */
  private const STATUS_LABELS = [
    'queued' => 'Na fila',
    'processing' => 'Em processamento',
    'sent' => 'Aceito',
    'delivered' => 'Entregue',
    'deferred' => 'Adiado',
    'bounced' => 'Devolvido',
    'complained' => 'Reclamação',
    'failed' => 'Falhou',
    'cancelled' => 'Suspenso',
  ];

  public function __construct(
    private readonly MailDashboard $dashboard,
    private readonly TimeInterface $time,
    private readonly MailDiagnostics $diagnostics,
    private readonly SenderDomainCheck $domainCheck,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_mailer.dashboard'),
      $container->get('datetime.time'),
      $container->get(MailDiagnostics::class),
      $container->get(SenderDomainCheck::class),
    );
  }

  /**
   * Página do painel: período e tipo escolhidos na query (`dias`, `tipo`) valem para gráfico e tabelas.
   */
  public function overview(Request $request): array {
    $now = $this->time->getRequestTime();
    $days = (int) $request->query->get('dias', 7);
    if (!in_array($days, MailDashboard::CHART_PERIODS, TRUE)) {
      $days = 7;
    }
    $filter = (string) $request->query->get('tipo', 'todos');
    if (!array_key_exists($filter, MailDashboard::FILTERS)) {
      $filter = 'todos';
    }
    $since = $now - $days * 86400;
    $today = intdiv($now, 86400);

    // Uma linha por dia UTC do período, em ordem; dias sem envio entram com zero.
    $labels = [];
    $series = [];
    $categories = [
      'confirmados' => [$this->t('Confirmados'), '#2e7d32'],
      'nao_confirmados' => [$this->t('Não confirmados'), '#616161'],
      'falha' => [$this->t('Com falha'), '#c62828'],
    ];
    $perCategory = [];
    foreach ($categories as $key => [$label, $color]) {
      $perCategory[$key] = $this->dashboard->dailyCounts($since, MailDashboard::FILTERS[$key]);
    }
    for ($day = $today - $days + 1; $day <= $today; $day++) {
      $labels[] = gmdate('d/m', $day * 86400);
    }
    $selected = $filter === 'todos' ? array_keys($categories) : [$filter];
    $table = [];
    foreach ($categories as $key => [$label, $color]) {
      if (!in_array($key, $selected, TRUE)) {
        continue;
      }
      $values = [];
      for ($day = $today - $days + 1; $day <= $today; $day++) {
        $values[] = $perCategory[$key][$day] ?? 0;
      }
      $series[] = ['label' => (string) $label, 'color' => $color, 'values' => $values];
      $table[] = array_merge([(string) $label], $values, [array_sum($values)]);
    }

    $summary = $this->dashboard->summary($since);
    $total = $filter === 'todos'
      ? $summary['total']
      : array_sum(array_map('array_sum', array_intersect_key($perCategory, [$filter => TRUE])));

    $build = [
      '#type' => 'container',
      'intro' => [
        '#markup' => '<p>' . $this->t('Aceito pelo sistema de e-mail não é o mesmo que entregue: a entrega depende dos provedores.') . '</p>',
      ],
      'controles' => $this->controls($days, $filter),
      'resumo' => [
        '#markup' => '<p><strong>' . $this->t('@total envio(s) no período (@tipo)', [
          '@total' => $total,
          '@tipo' => (string) $this->filterLabel($filter),
        ]) . '</strong></p>',
      ],
      'tendencia' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-mailer-tendencia']],
        'grafico' => [
          '#markup' => Markup::create(MailTrendChart::svg($labels, $series, (string) $this->t('Envios por dia nos últimos @days dias', ['@days' => $days]))),
        ],
        'valores' => $this->scrollable([
          '#type' => 'table',
          '#caption' => $this->t('Valores do gráfico, por dia'),
          '#header' => array_merge([$this->t('Série')], $labels, [$this->t('Total')]),
          '#rows' => $table,
        ]),
      ],
      'status_chart' => $this->chart($this->t('Estado'), $this->labelled($summary['by_status'], self::STATUS_LABELS)),
      'transport_chart' => $this->chart($this->t('Transporte'), $summary['by_transport']),
      'status' => $this->table($this->t('Estado'), $this->labelled($summary['by_status'], self::STATUS_LABELS)),
      'type' => $this->table($this->t('Tipo'), $summary['by_type']),
      'transport' => $this->table($this->t('Transporte'), $summary['by_transport']),
      'error' => $this->table($this->t('Motivo da falha'), $summary['by_error']),
    ];
    return $build;
  }

  /**
   * Formulário de seleção (GET, sem JavaScript): período e tipo, com botão de aplicar.
   */
  private function controls(int $days, string $filter): array {
    $periodOptions = '';
    foreach (MailDashboard::CHART_PERIODS as $option) {
      $periodOptions .= sprintf('<option value="%d"%s>%s</option>', $option, $option === $days ? ' selected' : '', Html::escape((string) $this->t('Últimos @days dias', ['@days' => $option])));
    }
    $typeOptions = '';
    foreach (array_keys(MailDashboard::FILTERS) as $option) {
      $typeOptions .= sprintf('<option value="%s"%s>%s</option>', Html::escape($option), $option === $filter ? ' selected' : '', Html::escape((string) $this->filterLabel($option)));
    }
    $action = Html::escape(Url::fromRoute('aculta_mailer.dashboard')->toString());
    return [
      '#markup' => Markup::create(sprintf(
        '<form method="get" action="%s" class="aculta-mailer-controles"><label>%s <select name="tipo">%s</select></label> <label>%s <select name="dias">%s</select></label> <button type="submit">%s</button></form>',
        $action,
        Html::escape((string) $this->t('Tipo de envio')),
        $typeOptions,
        Html::escape((string) $this->t('Período')),
        $periodOptions,
        Html::escape((string) $this->t('Aplicar')),
      )),
    ];
  }

  /**
   * Rótulo de um filtro de tipo.
   */
  private function filterLabel(string $filter): TranslatableMarkup {
    return match ($filter) {
      'confirmados' => $this->t('Confirmados'),
      'nao_confirmados' => $this->t('Não confirmados'),
      'falha' => $this->t('Com falha'),
      default => $this->t('Todos os e-mails'),
    };
  }

  /**
   * Relatório de falhas: texto completo do erro por envio, para o administrador.
   */
  public function failures(): array {
    $since = $this->time->getRequestTime() - 30 * 86400;
    $rows = [];
    foreach ($this->dashboard->recentFailures($since) as $failure) {
      $rows[] = [
        date('Y-m-d H:i', $failure['created']),
        $failure['type'],
        $failure['correlation_id'],
        $failure['transport'],
        $failure['attempts'],
        $failure['error_code'],
        $failure['error_message'],
        $failure['resendable'] ? [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Reenviar'),
            '#url' => Url::fromRoute('aculta_mailer.failure_resend', ['log_id' => $failure['id']]),
          ],
        ] : '-',
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Estado'),
            '#url' => Url::fromRoute('aculta_mailer.log_status', ['log_id' => $failure['id']]),
          ],
        ],
      ];
    }
    return [
      'intro' => ['#markup' => '<p>' . $this->t('Últimos 30 dias. O texto completo pode conter o endereço do destinatário: não compartilhe este relatório.') . '</p>'],
      'table' => $this->scrollable([
        '#type' => 'table',
        '#header' => [
          $this->t('Data'),
          $this->t('Tipo'),
          $this->t('Correlação'),
          $this->t('Transporte'),
          $this->t('Tentativas'),
          $this->t('Código'),
          $this->t('Texto completo'),
          $this->t('Reenvio'),
          $this->t('Estado'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma falha nos últimos 30 dias.'),
      ]),
    ];
  }

  /**
   * Exportação do registro em CSV (`;`, com BOM para o Excel), sem destinatário nem texto de erro.
   *
   * O período vem de `dias`, limitado aos períodos do painel; sem valor válido, 30 dias.
   */
  public function export(Request $request): Response {
    $days = $request->query->getInt('dias', 30);
    if (!in_array($days, MailDashboard::PERIODS, TRUE)) {
      $days = 30;
    }
    $handle = fopen('php://temp', 'w+');
    fputcsv($handle, [
      (string) $this->t('Data'),
      (string) $this->t('Tipo'),
      (string) $this->t('Estado'),
      (string) $this->t('Transporte'),
      (string) $this->t('Tentativas'),
      (string) $this->t('Código'),
      (string) $this->t('Correlação'),
    ], ';');
    foreach ($this->dashboard->exportRows($this->time->getRequestTime() - $days * 86400) as $row) {
      fputcsv($handle, [
        date('Y-m-d H:i', (int) $row['created']),
        $row['type'],
        self::STATUS_LABELS[$row['status']] ?? $row['status'],
        $row['transport'],
        $row['attempts'],
        $row['error_code'],
        $row['correlation_id'],
      ], ';');
    }
    rewind($handle);
    $csv = "\xEF\xBB\xBF" . (string) stream_get_contents($handle);
    fclose($handle);
    return new Response($csv, 200, [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="envios-' . $days . '-dias-' . date('Ymd-His', $this->time->getRequestTime()) . '.csv"',
      'X-Robots-Tag' => 'noindex, nofollow, noarchive',
    ]);
  }

  /**
   * Diagnóstico: transportes configurados (sem credenciais), último envio e falha, fila e cron.
   */
  public function diagnostics(): array {
    $report = $this->diagnostics->report();
    $transports = [];
    foreach ($report['transports'] as $id => $row) {
      // Brevo é API: não tem servidor, porta nem certificado. Mostrar isso em vez de ":0".
      $api = $row['plugin'] === 'brevo';
      $transports[] = [
        $id,
        $row['label'],
        $api ? $this->t('API (sem servidor)') : $row['host'] . ':' . $row['port'],
        $api ? $this->t('não se aplica') : ($row['verify_peer'] ? $this->t('sim') : $this->t('não')),
        $api ? $this->t('não se aplica') : ($row['user_set'] ? $this->t('preenchido') : $this->t('vazio')),
        $api ? $this->t('chave no arquivo de segredos') : ($row['pass_set'] ? $this->t('preenchida') : $this->t('vazia')),
        $row['last_sent'] ? date('Y-m-d H:i', $row['last_sent']) : $this->t('nunca'),
        $row['last_failure'] ? date('Y-m-d H:i', $row['last_failure']) : $this->t('nunca'),
      ];
    }
    return [
      'default' => [
        '#markup' => '<p>' . $this->t('Padrão: @default. Contingência: @fallback.', [
          '@default' => $report['default'],
          '@fallback' => $report['failover']['fallback'] ?? '-',
        ]) . '</p>',
      ],
      'transports' => $this->scrollable([
        '#type' => 'table',
        '#header' => [
          $this->t('ID'),
          $this->t('Nome'),
          $this->t('Servidor'),
          $this->t('Verifica certificado'),
          $this->t('Usuário'),
          $this->t('Senha'),
          $this->t('Último envio aceito'),
          $this->t('Última falha'),
        ],
        '#rows' => $transports,
        '#empty' => $this->t('Transportes indisponíveis: o módulo de transporte não está habilitado.'),
      ]),
      'domain' => $this->senderDomain(),
      'queue' => ['#markup' => '<p>' . $this->t('Mensagens na fila: @n.', ['@n' => $report['queue_items']]) . '</p>'],
      'cron' => [
        '#markup' => '<p>' . $this->t('Última execução do cron: @when.', [
          '@when' => $report['cron_last'] ? date('Y-m-d H:i', $report['cron_last']) : $this->t('nunca'),
        ]) . '</p>',
      ],
    ];
  }

  /**
   * SPF, DKIM e DMARC do domínio do remetente (`system.site:mail`).
   */
  private function senderDomain(): array {
    $sender = (string) $this->config('system.site')->get('mail');
    if (!str_contains($sender, '@')) {
      return ['#markup' => '<p>' . $this->t('Domínio do remetente não configurado.') . '</p>'];
    }
    $check = $this->domainCheck->check(substr(strrchr($sender, '@'), 1));
    $spf = match ($check['spf']) {
      SenderDomainCheck::SPF_OK => $this->t('encontrado'),
      SenderDomainCheck::SPF_MULTIPLE => $this->t('mais de um registro (inválido)'),
      default => $this->t('não encontrado'),
    };
    $dmarc = $check['dmarc'] === SenderDomainCheck::DMARC_MISSING
      ? $this->t('não encontrado ou sem política')
      : $this->t('política @policy', ['@policy' => $check['dmarc']]);
    $active = array_keys(array_filter($check['dkim']));
    $dkim = $active === []
      ? $this->t('nenhum seletor com chave ativa')
      : $this->t('ativo: @selectors', ['@selectors' => implode(', ', $active)]);
    return [
      'intro' => ['#markup' => '<p>' . $this->t('Autenticação do domínio remetente, pelo DNS. A validação do domínio no Brevo é conferida no painel do Brevo.') . '</p>'],
      'table' => $this->scrollable([
        '#type' => 'table',
        '#header' => [$this->t('Domínio'), $this->t('SPF'), $this->t('DKIM'), $this->t('DMARC')],
        '#rows' => [[$check['domain'], $spf, $dkim, $dmarc]],
      ]),
    ];
  }

  /**
   * Quadro de duas colunas: rótulo e contagem.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   Título da primeira coluna.
   * @param array $counts
   *   Contagem por valor.
   *
   * @return array
   *   Render array de tabela.
   */
  private function table(TranslatableMarkup $label, array $counts): array {
    $rows = [];
    foreach ($counts as $name => $count) {
      $rows[] = [$name, $count];
    }
    return $this->scrollable([
      '#type' => 'table',
      '#header' => [$label, $this->t('Quantidade')],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum registro.'),
    ]);
  }

  /**
   * Rótulos legíveis nas chaves de uma contagem; chave sem rótulo fica como está.
   */
  private function labelled(array $counts, array $labels): array {
    $out = [];
    foreach ($counts as $key => $count) {
      $out[(string) ($labels[$key] ?? $key)] = $count;
    }
    return $out;
  }

  /**
   * Barras de contagem com o elemento nativo progress: o valor aparece como texto, então não depende de cor.
   */
  private function chart(TranslatableMarkup $label, array $counts): array {
    if ($counts === []) {
      return ['#markup' => '<p>' . $this->t('Nenhum registro.') . '</p>'];
    }
    $max = max(1, max($counts));
    $items = [];
    foreach ($counts as $name => $count) {
      // O nome e o valor vão em texto, escapados; a barra é só apoio visual.
      $items[] = ['#markup' => Html::escape($name . ': ' . $count) . ' <progress value="' . (int) $count . '" max="' . $max . '"></progress>'];
    }
    return ['#theme' => 'item_list', '#title' => $label, '#items' => $items];
  }

  /**
   * Tabela dentro de um contêiner com rolagem horizontal (estilo em css/admin.css).
   */
  private function scrollable(array $table): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-aculta-mailer-table' => 'rolagem'],
      '#attached' => ['library' => ['aculta_mailer/admin']],
      'table' => $table,
    ];
  }

}
