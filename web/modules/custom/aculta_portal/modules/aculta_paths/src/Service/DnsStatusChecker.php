<?php

declare(strict_types=1);

namespace Drupal\aculta_paths\Service;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Verifica se o DNS de um subdomínio aponta para o mesmo destino do principal.
 *
 * Regra: o subdomínio é OK quando tem CNAME para o host principal ou quando
 * um dos seus IPs A é também um IP do principal. Resultado em cache curto.
 */
final class DnsStatusChecker {

  use StringTranslationTrait;

  private const TTL = 300;

  public function __construct(
    private readonly DomainPurposeManager $purposes,
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * Retorna a situação do DNS de um host, com cache curto.
   *
   * @param string $host
   *   Host cuja situação de DNS será verificada.
   *
   * @return array{classe: string, texto: string}
   *   Classe (ok, erro ou aviso) e texto descritivo da situação.
   */
  public function status(string $host): array {
    $cid = 'aculta_paths:dns:' . $host;
    $cache = $this->cache->get($cid);
    if ($cache && is_array($cache->data)) {
      return $cache->data;
    }
    $resultado = $this->verificar($host);
    $this->cache->set($cid, $resultado, time() + self::TTL);
    return $resultado;
  }

  /**
   * Classifica o DNS do host em relação ao domínio principal.
   *
   * @param string $host
   *   Host a verificar.
   *
   * @return array{classe: string, texto: string}
   *   Classe (ok, erro ou aviso) e texto descritivo da situação.
   */
  private function verificar(string $host): array {
    $principal = $this->purposes->getDomain(DomainSurfaceMap::MAIN)?->getHostname();
    if ($principal === NULL || $principal === '') {
      return ['classe' => 'aviso', 'texto' => (string) $this->t('Não verificado: o principal não tem Domain.')];
    }
    $r = DnsClassificador::classificar($principal, $this->registros($principal), $this->registros($host));
    $texto = match ($r['codigo']) {
      'principal_sem_resolucao' => $this->t('Não verificado: o principal não resolve por DNS a partir deste servidor.'),
      'nao_resolve' => $this->t('Não resolve: nenhum registro DNS encontrado.'),
      'cname_principal' => $this->t('OK: CNAME aponta para o principal.'),
      'mesmo_ip' => $this->t('OK: aponta para o mesmo servidor do principal.'),
      default => $this->t('Aponta para outro destino: @destino.', ['@destino' => $r['destino'] ?? '']),
    };
    return ['classe' => $r['classe'], 'texto' => (string) $texto];
  }

  /**
   * Coleta os registros A e CNAME de um nome.
   *
   * @param string $nome
   *   Nome DNS consultado.
   *
   * @return array{ips: string[], cnames: string[]}
   *   Endereços IP e destinos de CNAME encontrados.
   */
  private function registros(string $nome): array {
    $ips = [];
    $cnames = [];
    $registros = @dns_get_record($nome, DNS_A | DNS_CNAME);
    if (!is_array($registros)) {
      return ['ips' => [], 'cnames' => []];
    }
    foreach ($registros as $registro) {
      if (($registro['type'] ?? '') === 'A' && isset($registro['ip'])) {
        $ips[] = (string) $registro['ip'];
      }
      if (($registro['type'] ?? '') === 'CNAME' && isset($registro['target'])) {
        $cnames[] = strtolower(rtrim((string) $registro['target'], '.'));
      }
    }
    return ['ips' => array_values(array_unique($ips)), 'cnames' => array_values(array_unique($cnames))];
  }

}
