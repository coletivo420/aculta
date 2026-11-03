<?php

declare(strict_types=1);

namespace Drupal\aculta_superficies\Service;

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
   * @return array{classe: string, texto: string}
   *   classe: ok, erro ou aviso.
   */
  public function status(string $host): array {
    $cid = 'aculta_superficies:dns:' . $host;
    $cache = $this->cache->get($cid);
    if ($cache && is_array($cache->data)) {
      return $cache->data;
    }
    $resultado = $this->verificar($host);
    $this->cache->set($cid, $resultado, time() + self::TTL);
    return $resultado;
  }

  /**
   * @return array{classe: string, texto: string}
   */
  private function verificar(string $host): array {
    $principal = $this->purposes->getDomain(DomainSurfaceMap::MAIN)?->getHostname();
    if ($principal === NULL || $principal === '') {
      return ['classe' => 'aviso', 'texto' => (string) $this->t('Não verificado: o principal não tem Domain.')];
    }
    $alvo = $this->registros($principal);
    if ($alvo['ips'] === [] && $alvo['cnames'] === []) {
      return ['classe' => 'aviso', 'texto' => (string) $this->t('Não verificado: o principal não resolve por DNS a partir deste servidor.')];
    }
    $sub = $this->registros($host);
    if ($sub['ips'] === [] && $sub['cnames'] === []) {
      return ['classe' => 'erro', 'texto' => (string) $this->t('Não resolve: nenhum registro DNS encontrado.')];
    }
    if (in_array(strtolower($principal), $sub['cnames'], TRUE)) {
      return ['classe' => 'ok', 'texto' => (string) $this->t('OK: CNAME aponta para o principal.')];
    }
    if (array_intersect($sub['ips'], $alvo['ips']) !== []) {
      return ['classe' => 'ok', 'texto' => (string) $this->t('OK: aponta para o mesmo servidor do principal.')];
    }
    $destino = $sub['cnames'] !== [] ? implode(', ', $sub['cnames']) : implode(', ', $sub['ips']);
    return ['classe' => 'erro', 'texto' => (string) $this->t('Aponta para outro destino: @destino.', ['@destino' => $destino])];
  }

  /**
   * @return array{ips: string[], cnames: string[]}
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
