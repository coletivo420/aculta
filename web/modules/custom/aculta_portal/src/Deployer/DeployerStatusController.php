<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Deployer;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Painel somente leitura do ACULTA Deployer.
 *
 * Usa o mesmo sistema de status do diagnóstico do Portal (✔ OK, ⚠ Atenção,
 * ✖ Erro). Não executa a ferramenta.
 */
final class DeployerStatusController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'aculta_portal.deployer_status_reader')]
    private readonly DeployerStatusReader $reader,
  ) {}

  /**
   * Renderiza o painel de status do ACULTA Deployer.
   *
   * @return array
   *   Render array do painel.
   */
  public function page(): array {
    $data = $this->reader->read();
    $build = [
      '#cache' => ['max-age' => 0],
    ];
    if ($data === NULL) {
      $build['missing'] = [
        '#component' => 'aculta420_v2:status-messages',
        '#props' => ['type' => 'warning', 'messages' => [$this->t('⚠ Atenção: relatório do deployer indisponível. Gere com "aculta-deployer report" na raiz do repositório e confira a leitura do arquivo var/deployer/status.json.')]],
      ];
      return $build;
    }
    $env = $data['environment'] ?? NULL;
    $build['identity'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Ambiente'),
        $this->t('Endereço do site'),
        $this->t('Relatório gerado em'),
        $this->t('Ferramenta'),
      ],
      '#rows' => [[
        ['data' => ['#plain_text' => is_array($env) ? (string) ($env['environment'] ?? '—') : $this->t('não definido')]],
        ['data' => ['#plain_text' => is_array($env) ? (string) ($env['site'] ?? '—') : '—']],
        ['data' => ['#plain_text' => (string) ($data['generated_at'] ?? '—')]],
        ['data' => ['#plain_text' => (string) ($data['tool'] ?? '—')]],
      ]],
      '#attributes' => ['data-portal-ui' => 'requirements'],
    ];
    $build['intro'] = ['#plain_text' => $this->t('Mostra nomes e estados; valores de credenciais nunca são exibidos.')];
    $build['legend'] = $this->legend();

    $boundaryOk = ($data['boundaries']['ok'] ?? FALSE) === TRUE;
    $rows = [[
      ['data' => ['#plain_text' => $this->t('Fronteiras do repositório')]],
      ['data' => ['#plain_text' => $boundaryOk ? $this->t('Sem violações') : $this->t('@n violação(ões)', ['@n' => (int) ($data['boundaries']['violations'] ?? 0)])]],
      $this->statusCell($boundaryOk ? 'ok' : 'error'),
    ]];
    $build['boundaries'] = [
      '#type' => 'table',
      '#header' => [$this->t('Verificação'), $this->t('Detalhe'), $this->t('Status')],
      '#rows' => $rows,
      '#attributes' => ['data-portal-ui' => 'requirements'],
    ];

    $open = $data['registry']['entries'] ?? [];
    $registryRows = [];
    foreach ($open as $entry) {
      $blocking = ($entry['blocking'] ?? FALSE) === TRUE;
      $state = $blocking ? 'error' : 'attention';
      $registryRows[] = [
        ['data' => ['#plain_text' => (string) ($entry['id'] ?? '')]],
        ['data' => ['#plain_text' => (string) ($entry['kind'] ?? '')]],
        ['data' => ['#plain_text' => (string) ($entry['current'] ?? '')]],
        $this->statusCell($state, $blocking ? $this->t('Bloqueante') : $this->t('Não bloqueante')),
      ];
    }
    $build['registry'] = [
      '#type' => 'table',
      '#caption' => $this->t('Correções de deploy abertas (@n)', ['@n' => (int) ($data['registry']['open'] ?? 0)]),
      '#header' => [$this->t('Entrada'), $this->t('Tipo'), $this->t('Estado atual'), $this->t('Status')],
      '#rows' => $registryRows,
      '#empty' => $this->t('Nenhuma correção aberta.'),
      '#attributes' => ['data-portal-ui' => 'requirements'],
    ];

    $build['links'] = [
      '#type' => 'container',
      'import' => [
        '#type' => 'link',
        '#title' => $this->t('Credenciais do ambiente'),
        '#url' => Url::fromRoute('aculta_portal.secrets_import'),
      ],
    ];
    $build['runtime_sync'] = $this->buildRuntimeSyncSection();
    $build['not_included'] = ['#plain_text' => $this->t('Não incluído neste relatório: verificações de rede (sitemap e robots), que são comandos próprios da ferramenta.')];
    return $build;
  }

  /**
   * Monta a seção somente leitura de estado do Runtime Sync v2.
   *
   * @return array<string, mixed>
   *   Render array com SHAs, estado do cache, risco, gates e histórico.
   */
  private function buildRuntimeSyncSection(): array {
    $sync = $this->reader->readRuntimeSync();
    if ($sync === NULL) {
      return [
        '#component' => 'aculta420_v2:status-messages',
        '#props' => [
          'type' => 'warning',
          'messages' => [$this->t('⚠ Atenção: relatório do Runtime Sync v2 indisponível (var/runtime-sync/state.json). Execute a sincronização cotidiana ou o diagnóstico na raiz do checkout de desenvolvimento.')],
        ],
      ];
    }

    $checkoutSha = (string) ($sync['checkout_sha'] ?? 'UNKNOWN');
    $remoteSha = (string) ($sync['remote_sha'] ?? 'UNKNOWN');
    $validatedSha = (string) ($sync['last_validated_sha'] ?? 'UNKNOWN');
    $cacheSha = (string) ($sync['last_cache_success_sha'] ?? 'UNKNOWN');
    $pendingSha = (string) ($sync['pending_structural_sha'] ?? '—');
    $pendingRisk = (string) ($sync['pending_structural_risk'] ?? '—');

    $lastOp = is_array($sync['last_operation'] ?? NULL) ? $sync['last_operation'] : [];
    $outcome = (string) ($lastOp['outcome'] ?? 'UNKNOWN');
    $risk = (string) ($lastOp['risk'] ?? 'RX');
    $mode = (string) ($lastOp['mode'] ?? '—');
    $timestamp = (string) ($lastOp['timestamp'] ?? ($sync['updated_at'] ?? '—'));
    $summary = (string) ($lastOp['summary'] ?? '—');

    $cacheValidated = $validatedSha !== 'UNKNOWN' && $validatedSha === $checkoutSha && $cacheSha === $checkoutSha;
    $cacheState = $cacheValidated ? 'ok' : ($cacheSha !== 'UNKNOWN' ? 'attention' : 'error');
    $cacheLabel = $cacheValidated ? $this->t('Validado (@sha)', ['@sha' => substr($cacheSha, 0, 12)]) : $this->t('Pendente / desconhecido (@sha)', ['@sha' => $cacheSha === 'UNKNOWN' ? 'UNKNOWN' : substr($cacheSha, 0, 12)]);

    $outcomeState = match ($outcome) {
      'VALIDATED', 'NOOP', 'READY' => 'ok',
      'PLANNED', 'INDETERMINATE' => 'attention',
      default => 'error',
    };
    $riskState = match ($risk) {
      'R0', 'R1' => 'ok',
      'R2', 'R3' => 'attention',
      default => 'error',
    };

    $section = [
      '#type' => 'container',
    ];
    $section['summary_table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Runtime Sync v2 — estado de sincronização e cache'),
      '#header' => [
        $this->t('Indicador'),
        $this->t('Valor'),
        $this->t('Status'),
      ],
      '#rows' => [
        [
          ['data' => ['#plain_text' => $this->t('SHA do checkout local')]],
          ['data' => ['#plain_text' => $checkoutSha]],
          $this->statusCell($checkoutSha !== 'UNKNOWN' ? 'ok' : 'attention'),
        ],
        [
          ['data' => ['#plain_text' => $this->t('SHA remoto (origin/dev)')]],
          ['data' => ['#plain_text' => $remoteSha]],
          $this->statusCell($remoteSha === $checkoutSha && $remoteSha !== 'UNKNOWN' ? 'ok' : 'attention'),
        ],
        [
          ['data' => ['#plain_text' => $this->t('Último SHA validado por completo')]],
          ['data' => ['#plain_text' => $validatedSha]],
          $this->statusCell($validatedSha === $checkoutSha && $validatedSha !== 'UNKNOWN' ? 'ok' : 'error'),
        ],
        [
          ['data' => ['#plain_text' => $this->t('Situação do cache Drupal')]],
          ['data' => ['#plain_text' => $cacheLabel]],
          $this->statusCell($cacheState),
        ],
        [
          ['data' => ['#plain_text' => $this->t('Risco classificado')]],
          ['data' => ['#plain_text' => $risk]],
          $this->statusCell($riskState, $risk),
        ],
        [
          ['data' => ['#plain_text' => $this->t('Pendência estrutural')]],
          ['data' => ['#plain_text' => $pendingSha !== '' && $pendingSha !== '—' ? $pendingSha . ' (' . $pendingRisk . ')' : $this->t('Nenhuma pendência bloqueante registrada')]],
          $this->statusCell($pendingSha !== '' && $pendingSha !== '—' ? 'error' : 'ok'),
        ],
        [
          ['data' => ['#plain_text' => $this->t('Última execução (@mode em @ts)', ['@mode' => $mode, '@ts' => $timestamp])]],
          ['data' => ['#plain_text' => $summary]],
          $this->statusCell($outcomeState, $outcome),
        ],
      ],
      '#attributes' => ['data-portal-ui' => 'requirements'],
    ];

    $latest = is_array($sync['latest_report'] ?? NULL) ? $sync['latest_report'] : [];
    $gates = is_array($latest['gates'] ?? NULL) ? $latest['gates'] : [];
    $gateRows = [];
    foreach ($gates as $gate) {
      if (!is_array($gate)) {
        continue;
      }
      $res = (string) ($gate['result'] ?? 'FAIL');
      $gState = match ($res) {
        'PASS' => 'ok',
        'SKIP', 'DEFERRED', 'N/A' => 'attention',
        default => 'error',
      };
      $gateRows[] = [
        ['data' => ['#plain_text' => (string) ($gate['id'] ?? '')]],
        ['data' => ['#plain_text' => (string) ($gate['reason'] ?? '')]],
        $this->statusCell($gState, $res),
      ];
    }
    $section['gates'] = [
      '#type' => 'table',
      '#caption' => $this->t('Gates da última execução do Runtime Sync'),
      '#header' => [$this->t('Gate'), $this->t('Motivo / evidência'), $this->t('Resultado')],
      '#rows' => $gateRows,
      '#empty' => $this->t('Nenhum gate registrado no último relatório.'),
      '#attributes' => ['data-portal-ui' => 'requirements'],
    ];

    $history = is_array($sync['history'] ?? NULL) ? array_slice($sync['history'], 0, 10) : [];
    $historyRows = [];
    foreach ($history as $item) {
      if (!is_array($item)) {
        continue;
      }
      $hOutcome = (string) ($item['outcome'] ?? 'UNKNOWN');
      $hState = match ($hOutcome) {
        'VALIDATED', 'NOOP', 'READY' => 'ok',
        'PLANNED', 'INDETERMINATE' => 'attention',
        default => 'error',
      };
      $historyRows[] = [
        ['data' => ['#plain_text' => (string) ($item['timestamp'] ?? '—')]],
        ['data' => ['#plain_text' => (string) ($item['mode'] ?? '—')]],
        ['data' => ['#plain_text' => substr((string) ($item['checkout_sha'] ?? '—'), 0, 12)]],
        ['data' => ['#plain_text' => (string) ($item['risk'] ?? '—')]],
        ['data' => ['#plain_text' => (string) ($item['summary'] ?? '—')]],
        $this->statusCell($hState, $hOutcome),
      ];
    }
    $section['history'] = [
      '#type' => 'table',
      '#caption' => $this->t('Histórico resumido de operações do Runtime Sync'),
      '#header' => [
        $this->t('Instante'),
        $this->t('Modo'),
        $this->t('SHA local'),
        $this->t('Risco'),
        $this->t('Resumo'),
        $this->t('Resultado'),
      ],
      '#rows' => $historyRows,
      '#empty' => $this->t('Nenhuma operação registrada no histórico.'),
      '#attributes' => ['data-portal-ui' => 'requirements'],
    ];

    return $section;
  }

  /**
   * Monta a célula de status com símbolo e texto.
   *
   * @param string $state
   *   Estado: "ok", "attention" ou "error".
   * @param string|null $label
   *   Rótulo opcional; usa o padrão do estado quando NULL.
   *
   * @return array
   *   Célula de tabela formatada.
   */
  private function statusCell(string $state, $label = NULL): array {
    $symbol = match ($state) {
      'ok' => '✔',
      'attention' => '⚠',
      default => '✖',
    };
    $text = $label ?? match ($state) {
      'ok' => $this->t('OK'),
      'attention' => $this->t('Atenção'),
      default => $this->t('Erro'),
    };
    return [
      'data' => ['#plain_text' => $symbol . ' ' . $text],
      'data-portal-ui' => 'requirements-status status-' . $state,
    ];
  }

  /**
   * Monta a legenda dos símbolos de status.
   *
   * @return array
   *   Render array da legenda.
   */
  private function legend(): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'data-portal-ui' => 'requirements-legend',
        'aria-label' => (string) $this->t('Legenda de status'),
      ],
      'ok' => ['#plain_text' => (string) $this->t('✔ OK: sem pendência.')],
      'attention' => ['#plain_text' => (string) $this->t('⚠ Atenção: pendência não bloqueante.')],
      'error' => ['#plain_text' => (string) $this->t('✖ Erro: pendência bloqueante ou credencial obrigatória ausente.')],
    ];
  }

}
