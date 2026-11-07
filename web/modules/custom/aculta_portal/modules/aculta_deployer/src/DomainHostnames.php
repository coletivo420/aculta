<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Decide os hostnames dos registros de domínio para um ambiente.
 *
 * Função pura: não lê banco nem executa comandos. Quem lê e grava é o Cli, por um
 * comando externo configurado no ambiente (ver Cli::domainHostnamesSync).
 */
final class DomainHostnames {

  /**
   * Plano de alteração dos registros de domínio.
   *
   * @param array<int, array{domain_id: string, test_host?: string, production_host?: string}> $entries
   *   Entradas de domain_transition.entries em config/deploy.json.
   * @param string $env
   *   'test' ou 'production'.
   * @param array<string, ?string> $current
   *   Hostname atual de cada domain_id no banco (NULL se não lido).
   *
   * @return array<int, array{domain_id: string, target: string, current: ?string, action: string}>
   *   Ação por domínio: 'set' (gravar), 'ok' (já igual) ou 'unknown' (sem leitura).
   */
  public static function plan(array $entries, string $env, array $current): array {
    $key = $env === 'production' ? 'production_host' : 'test_host';
    $plan = [];
    foreach ($entries as $entry) {
      $id = (string) ($entry['domain_id'] ?? '');
      $target = (string) ($entry[$key] ?? '');
      if ($id === '' || $target === '') {
        continue;
      }
      $now = array_key_exists($id, $current) ? $current[$id] : NULL;
      $action = match (TRUE) {
        $now === NULL => 'unknown',
        $now === $target => 'ok',
        default => 'set',
      };
      $plan[] = ['domain_id' => $id, 'target' => $target, 'current' => $now, 'action' => $action];
    }
    return $plan;
  }

}
