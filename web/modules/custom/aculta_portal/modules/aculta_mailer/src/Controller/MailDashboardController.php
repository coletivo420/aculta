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
   *
   * @return array<string, mixed>
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
    /** @var array<string, array<int, int>> $perCategory */
    $perCategory = [];
    foreach (array_keys($categories) as $key) {
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

    /** @var array<string, mixed> $summary */
    $summary = $this->dashboard->summary($since);
    $total = $filter === 'todos'
      ? (isset($summary['total']) && is_numeric($summary['total']) ? (int) $summary['total'] : 0)
      : array_sum(array_map('array_sum', array_intersect_key($perCategory, [$filter => TRUE])));

    /** @var array<string, int> $byStatus */
    $byStatus = isset($summary['by_status']) && is_array($summary['by_status']) ? $summary['by_status'] : [];
    /** @var array<string, int> $byTransport */
    $byTransport = isset($summary['by_transport']) && is_array($summary['by_transport']) ? $summary['by_transport'] : [];
    /** @var array<string, int> $byType */
    $byType = isset($summary['by_type']) && is_array($summary['by_type']) ? $summary['by_type'] : [];
    /** @var array<string, int> $byError */
    $byError = isset($summary['by_error']) && is_array($summary['by_error']) ? $summary['by_error'] : [];

    $labelledStatus = $this->labelled($byStatus, self::STATUS_LABELS);

    $build = [
      '#type' => 'container',
      'intro' => [
        '#markup' => '<p>' . $this->t('Aceito pelo sistema de e-mail não é o mesmo que entregue: a entrega depende dos provedores.') . '</p>',
      ],
      'controles' => $this->controls($days, $filter),
      'resumo' => [
        '#markup' => '<p><strong>' . $this->t('@total envio(s) no período (@tipo)', [
          '@total' => (string) $total,
          '@tipo' => (string) $this->filterLabel($filter),
        ]) . '</strong></p>',
      ],
      'tendencia' => [
        '#type' => 'container',
        '#attributes' => ['data-aculta-mailer-tendencia' => 'grafico'],
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
      'status_chart' => $this->chart($this->t('Estado'), $labelledStatus),
      'transport_chart' => $this->chart($this->t('Transporte'), $byTransport),
      'status' => $this->table($this->t('Estado'), $labelledStatus),
      'type' => $this->table($this->t('Tipo'), $byType),
      'transport' => $this->table($this->t('Transporte'), $byTransport),
      'error' => $this->table($this->t('Motivo da falha'), $byError),
    ];
    return $build;
  }

  /**
   * Formulário de seleção (GET, sem JavaScript): período e tipo, com botão de aplicar.
   *
   * @return array<string, mixed>
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
   *
   * @return array<string, mixed>
   */
  public function failures(): array {
    $since = $this->time->getRequestTime() - 30 * 86400;
    $rows = [];
    /** @var iterable<mixed> $failures */
    $failures = $this->dashboard->recentFailures($since);
    foreach ($failures as $failure) {
      if (!is_array($failure)) {
        continue;
      }
      $created = isset($failure['created']) && is_numeric($failure['created']) ? (int) $failure['created'] : 0;
      $type = isset($failure['type']) && is_scalar($failure['type']) ? (string) $failure['type'] : '';
      $correlationId = isset($failure['correlation_id']) && is_scalar($failure['correlation_id']) ? (string) $failure['correlation_id'] : '';
      $transport = isset($failure['transport']) && is_scalar($failure['transport']) ? (string) $failure['transport'] : '';
      $attempts = isset($failure['attempts']) && is_scalar($failure['attempts']) ? (string) $failure['attempts'] : '0';
      $errorCode = isset($failure['error_code']) && is_scalar($failure['error_code']) ? (string) $failure['error_code'] : '';
      $errorMessage = isset($failure['error_message']) && is_scalar($failure['error_message']) ? (string) $failure['error_message'] : '';
      $resendable = !empty($failure['resendable']);
      $id = isset($failure['id']) && is_scalar($failure['id']) ? (string) $failure['id'] : '';

      $rows[] = [
        date('Y-m-d H:i', $created),
        $type,
        $correlationId,
        $transport,
        $attempts,
        $errorCode,
        $errorMessage,
        $resendable ? [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Reenviar'),
            '#url' => Url::fromRoute('aculta_mailer.failure_resend', ['log_id' => $id]),
          ],
        ] : '-',
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Estado'),
            '#url' => Url::fromRoute('aculta_mailer.log_status', ['log_id' => $id]),
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
    if ($handle === FALSE) {
      throw new \RuntimeException('Não foi possível abrir o fluxo temporário para exportação de CSV.');
    }
    fputcsv($handle, [
      (string) $this->t('Data'),
      (string) $this->t('Tipo'),
      (string) $this->t('Estado'),
      (string) $this->t('Transporte'),
      (string) $this->t('Tentativas'),
      (string) $this->t('Código'),
      (string) $this->t('Correlação'),
    ], ';');
    /** @var iterable<array<string, mixed>> $rows */
    $rows = $this->dashboard->exportRows($this->time->getRequestTime() - $days * 86400);
    foreach ($rows as $row) {
      $created = isset($row['created']) && is_numeric($row['created']) ? (int) $row['created'] : 0;
      $type = isset($row['type']) && is_scalar($row['type']) ? (string) $row['type'] : '';
      $statusRaw = isset($row['status']) && is_scalar($row['status']) ? (string) $row['status'] : '';
      $status = self::STATUS_LABELS[$statusRaw] ?? $statusRaw;
      $transport = isset($row['transport']) && is_scalar($row['transport']) ? (string) $row['transport'] : '';
      $attempts = isset($row['attempts']) && is_scalar($row['attempts']) ? (string) $row['attempts'] : '0';
      $errorCode = isset($row['error_code']) && is_scalar($row['error_code']) ? (string) $row['error_code'] : '';
      $correlationId = isset($row['correlation_id']) && is_scalar($row['correlation_id']) ? (string) $row['correlation_id'] : '';

      fputcsv($handle, [
        date('Y-m-d H:i', $created),
        $type,
        $status,
        $transport,
        $attempts,
        $errorCode,
        $correlationId,
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
   *
   * @return array<string, mixed>
   */
  public function diagnostics(): array {
    /** @var array<string, mixed> $report */
    $report = $this->diagnostics->report();
    $transports = [];
    /** @var array<string, array<string, mixed>> $reportTransports */
    $reportTransports = isset($report['transports']) && is_array($report['transports']) ? $report['transports'] : [];
    foreach ($reportTransports as $id => $row) {
      $plugin = isset($row['plugin']) && is_scalar($row['plugin']) ? (string) $row['plugin'] : '';
      // Brevo é API: não tem servidor, porta nem certificado. Mostrar isso em vez de ":0".
      $api = $plugin === 'brevo';
      $label = isset($row['label']) && is_scalar($row['label']) ? (string) $row['label'] : (string) $id;
      $host = isset($row['host']) && is_scalar($row['host']) ? (string) $row['host'] : '';
      $port = isset($row['port']) && is_scalar($row['port']) ? (string) $row['port'] : '';
      $verifyPeer = !empty($row['verify_peer']);
      $userSet = !empty($row['user_set']);
      $passSet = !empty($row['pass_set']);
      $lastSent = isset($row['last_sent']) && is_numeric($row['last_sent']) ? (int) $row['last_sent'] : 0;
      $lastFailure = isset($row['last_failure']) && is_numeric($row['last_failure']) ? (int) $row['last_failure'] : 0;

      $transports[] = [
        $id,
        $label,
        $api ? $this->t('API (sem servidor)') : $host . ':' . $port,
        $api ? $this->t('não se aplica') : ($verifyPeer ? $this->t('sim') : $this->t('não')),
        $api ? $this->t('não se aplica') : ($userSet ? $this->t('preenchido') : $this->t('vazio')),
        $api ? $this->t('chave no arquivo de segredos') : ($passSet ? $this->t('preenchida') : $this->t('vazia')),
        $lastSent > 0 ? date('Y-m-d H:i', $lastSent) : $this->t('nunca'),
        $lastFailure > 0 ? date('Y-m-d H:i', $lastFailure) : $this->t('nunca'),
      ];
    }
    $defaultTransport = isset($report['default']) && is_scalar($report['default']) ? (string) $report['default'] : '';
    $failover = isset($report['failover']) && is_array($report['failover']) ? $report['failover'] : [];
    $fallback = isset($failover['fallback']) && is_scalar($failover['fallback']) ? (string) $failover['fallback'] : '-';
    $queueItems = isset($report['queue_items']) && is_numeric($report['queue_items']) ? (int) $report['queue_items'] : 0;
    $cronLast = isset($report['cron_last']) && is_numeric($report['cron_last']) ? (int) $report['cron_last'] : 0;

    return [
      'default' => [
        '#markup' => '<p>' . $this->t('Padrão: @default. Contingência: @fallback.', [
          '@default' => $defaultTransport,
          '@fallback' => $fallback,
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
      'queue' => ['#markup' => '<p>' . $this->t('Mensagens na fila: @n.', ['@n' => (string) $queueItems]) . '</p>'],
      'cron' => [
        '#markup' => '<p>' . $this->t('Última execução do cron: @when.', [
          '@when' => $cronLast > 0 ? date('Y-m-d H:i', $cronLast) : $this->t('nunca'),
        ]) . '</p>',
      ],
    ];
  }

  /**
   * SPF, DKIM e DMARC do domínio do remetente (`system.site:mail`).
   *
   * @return array<string, mixed>
   */
  private function senderDomain(): array {
    $mailConfig = $this->config('system.site')->get('mail');
    $sender = is_scalar($mailConfig) ? (string) $mailConfig : '';
    if (!str_contains($sender, '@')) {
      return ['#markup' => '<p>' . $this->t('Domínio do remetente não configurado.') . '</p>'];
    }
    $domainPart = substr(strrchr($sender, '@') ?: '', 1);
    /** @var array<string, mixed> $check */
    $check = $this->domainCheck->check($domainPart);
    $spfVal = isset($check['spf']) && is_scalar($check['spf']) ? (string) $check['spf'] : '';
    $spf = match ($spfVal) {
      SenderDomainCheck::SPF_OK => $this->t('encontrado'),
      SenderDomainCheck::SPF_MULTIPLE => $this->t('mais de um registro (inválido)'),
      default => $this->t('não encontrado'),
    };
    $dmarcVal = isset($check['dmarc']) && is_scalar($check['dmarc']) ? (string) $check['dmarc'] : '';
    $dmarc = $dmarcVal === SenderDomainCheck::DMARC_MISSING
      ? $this->t('não encontrado ou sem política')
      : $this->t('política @policy', ['@policy' => $dmarcVal]);
    /** @var array<string, bool> $dkimArray */
    $dkimArray = isset($check['dkim']) && is_array($check['dkim']) ? $check['dkim'] : [];
    $active = array_keys(array_filter($dkimArray));
    $dkim = $active === []
      ? $this->t('nenhum seletor com chave ativa')
      : $this->t('ativo: @selectors', ['@selectors' => implode(', ', $active)]);
    $domain = isset($check['domain']) && is_scalar($check['domain']) ? (string) $check['domain'] : '';
    return [
      'intro' => ['#markup' => '<p>' . $this->t('Autenticação do domínio remetente, pelo DNS. A validação do domínio no Brevo é conferida no painel do Brevo.') . '</p>'],
      'table' => $this->scrollable([
        '#type' => 'table',
        '#header' => [$this->t('Domínio'), $this->t('SPF'), $this->t('DKIM'), $this->t('DMARC')],
        '#rows' => [[$domain, $spf, $dkim, $dmarc]],
      ]),
    ];
  }

  /**
   * Quadro de duas colunas: rótulo e contagem.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   Título da primeira coluna.
   * @param array<string, int> $counts
   *   Contagem por valor.
   *
   * @return array<string, mixed>
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
   *
   * @param array<string, int> $counts
   * @param array<string, string> $labels
   *
   * @return array<string, int>
   */
  private function labelled(array $counts, array $labels): array {
    $out = [];
    foreach ($counts as $key => $count) {
      $out[$labels[$key] ?? (string) $key] = $count;
    }
    return $out;
  }

  /**
   * Barras de contagem com o elemento nativo progress: o valor aparece como texto, então não depende de cor.
   *
   * @param array<string, int> $counts
   *
   * @return array<string, mixed>
   */
  private function chart(TranslatableMarkup $label, array $counts): array {
    if ($counts === []) {
      return ['#markup' => '<p>' . $this->t('Nenhum registro.') . '</p>'];
    }
    $max = max(1, max($counts));
    $items = [];
    foreach ($counts as $name => $count) {
      // O nome e o valor vão em texto, escapados; a barra é só apoio visual.
      $items[] = ['#markup' => Html::escape($name . ': ' . (string) $count) . ' <progress value="' . (int) $count . '" max="' . $max . '"></progress>'];
    }
    return ['#theme' => 'item_list', '#title' => $label, '#items' => $items];
  }

  /**
   * Tabela dentro de um contêiner com rolagem horizontal (estilo em css/admin.css).
   *
   * @param array<string, mixed> $table
   *
   * @return array<string, mixed>
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
