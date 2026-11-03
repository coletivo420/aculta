<?php

declare(strict_types=1);

namespace Drupal\aculta_superficies\Service;

/**
 * Classifica o DNS de um subdomínio em relação ao principal, sem consultar a rede.
 *
 * Entradas: registros já resolvidos, no formato {ips: string[], cnames: string[]}.
 * Saída: classe (ok, erro ou aviso) e o código do resultado.
 */
final class DnsClassificador {

  /**
   * @param array{ips: string[], cnames: string[]} $alvo
   *   Registros do host principal.
   * @param array{ips: string[], cnames: string[]} $sub
   *   Registros do subdomínio.
   *
   * @return array{classe: string, codigo: string, destino?: string}
   */
  public static function classificar(string $principal, array $alvo, array $sub): array {
    if ($alvo['ips'] === [] && $alvo['cnames'] === []) {
      return ['classe' => 'aviso', 'codigo' => 'principal_sem_resolucao'];
    }
    if ($sub['ips'] === [] && $sub['cnames'] === []) {
      return ['classe' => 'erro', 'codigo' => 'nao_resolve'];
    }
    if (in_array(strtolower($principal), array_map('strtolower', $sub['cnames']), TRUE)) {
      return ['classe' => 'ok', 'codigo' => 'cname_principal'];
    }
    if (array_intersect($sub['ips'], $alvo['ips']) !== []) {
      return ['classe' => 'ok', 'codigo' => 'mesmo_ip'];
    }
    $destino = $sub['cnames'] !== [] ? implode(', ', $sub['cnames']) : implode(', ', $sub['ips']);
    return ['classe' => 'erro', 'codigo' => 'outro_destino', 'destino' => $destino];
  }

}
