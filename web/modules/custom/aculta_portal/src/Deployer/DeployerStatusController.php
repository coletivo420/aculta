<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Deployer;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Painel somente leitura do ACULTA Deployer. Usa o mesmo sistema de status do diagnóstico do
 * Portal (✔ OK, ⚠ Atenção, ✖ Erro). Não executa a ferramenta.
 */
final class DeployerStatusController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'aculta_portal.deployer_status_reader')]
    private readonly DeployerStatusReader $reader,
  ) {}

  public function page(): array {
    $data = $this->reader->read();
    $build = [
      '#cache' => ['max-age' => 0],
    ];
    if ($data === NULL) {
      $build['missing'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'notice-warning'],
        'text' => ['#plain_text' => $this->t('⚠ Atenção: relatório do deployer indisponível. Gere com "aculta-deployer report" na raiz do repositório e confira a leitura do arquivo var/deployer/status.json.')],
      ];
      return $build;
    }
    $env = $data['environment'] ?? NULL;
    $build['identity'] = [
      '#type' => 'table',
      '#header' => [$this->t('Ambiente'), $this->t('Endereço do site'), $this->t('Relatório gerado em'), $this->t('Ferramenta')],
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
      'import' => ['#type' => 'link', '#title' => $this->t('Credenciais do ambiente'), '#url' => Url::fromRoute('aculta_portal.secrets_import')],
    ];
    $build['not_included'] = ['#plain_text' => $this->t('Não incluído neste relatório: verificações de rede (sitemap e robots), que são comandos próprios da ferramenta.')];
    return $build;
  }

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

  private function legend(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'requirements-legend', 'aria-label' => (string) $this->t('Legenda de status')],
      'ok' => ['#plain_text' => (string) $this->t('✔ OK: sem pendência.')],
      'attention' => ['#plain_text' => (string) $this->t('⚠ Atenção: pendência não bloqueante.')],
      'error' => ['#plain_text' => (string) $this->t('✖ Erro: pendência bloqueante ou credencial obrigatória ausente.')],
    ];
  }

}
