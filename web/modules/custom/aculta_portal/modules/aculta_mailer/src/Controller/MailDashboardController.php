<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Controller;

use Drupal\aculta_mailer\Dashboard\MailDashboard;
use Drupal\aculta_mailer\Diagnostics\MailDiagnostics;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_mailer.dashboard'),
      $container->get('datetime.time'),
      $container->get(MailDiagnostics::class),
    );
  }

  /**
   * Página do painel.
   */
  public function overview(): array {
    $now = $this->time->getRequestTime();
    $build = [
      '#type' => 'container',
      'intro' => [
        '#markup' => '<p>' . $this->t('Aceito pelo sistema de e-mail não é o mesmo que entregue: a entrega depende dos provedores.') . '</p>',
      ],
    ];
    foreach (MailDashboard::PERIODS as $days) {
      $summary = $this->dashboard->summary($now - $days * 86400);
      $build['period_' . $days] = [
        '#type' => 'details',
        '#title' => new TranslatableMarkup('Últimos @days dia(s): @total envio(s)', [
          '@days' => $days,
          '@total' => $summary['total'],
        ]),
        '#open' => $days === 1,
        'status' => $this->table($this->t('Estado'), $this->labelled($summary['by_status'], self::STATUS_LABELS)),
        'type' => $this->table($this->t('Tipo'), $summary['by_type']),
        'transport' => $this->table($this->t('Transporte'), $summary['by_transport']),
        'error' => $this->table($this->t('Motivo da falha'), $summary['by_error']),
      ];
    }
    return $build;
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
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma falha nos últimos 30 dias.'),
      ]),
    ];
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
      'queue' => ['#markup' => '<p>' . $this->t('Mensagens na fila: @n.', ['@n' => $report['queue_items']]) . '</p>'],
      'cron' => [
        '#markup' => '<p>' . $this->t('Última execução do cron: @when.', [
          '@when' => $report['cron_last'] ? date('Y-m-d H:i', $report['cron_last']) : $this->t('nunca'),
        ]) . '</p>',
      ],
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
   * Tabela dentro de um contêiner com rolagem horizontal (estilo em css/admin.css).
   */
  private function scrollable(array $table): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-mailer-scroll']],
      '#attached' => ['library' => ['aculta_mailer/admin']],
      'table' => $table,
    ];
  }

}
