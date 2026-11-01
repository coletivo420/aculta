<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Lookup;

use Drupal\aculta_portal_cep\Local\CepLocalStore;
use Drupal\aculta_portal_cep\Settings\CepSources;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Consulta de CEP reutilizável com as fontes configuradas em CepSources.
 *
 * É a única implementação da consulta. O endpoint AJAX e os módulos PHP chamam
 * este serviço, sem fazer requisições HTTP à rota AJAX.
 *
 * Devolve campos compatíveis com Drupal Address e não persiste endereço: o
 * resultado é uma sugestão para quem chamou.
 */
final class CepLookupService implements CepLookupInterface {

  private const VIACEP = 'https://viacep.com.br/ws/%s/json/';
  private const BRASILAPI = 'https://brasilapi.com.br/api/cep/v2/%s';
  private const CACHE_TTL = 2592000;

  public function __construct(
    #[Autowire(service: 'aculta_portal_cep.local_store')]
    private readonly CepLocalStore $local,
    #[Autowire(service: 'http_client')]
    private readonly ClientInterface $http,
    #[Autowire(service: 'cache.data')]
    private readonly CacheBackendInterface $cache,
    #[Autowire(service: 'aculta_portal_cep.sources')]
    private readonly CepSources $sources,
    #[Autowire(service: 'logger.channel.default')]
    private readonly ?LoggerInterface $logger = NULL,
  ) {}

  /**
   * Normaliza um CEP com ou sem máscara para oito dígitos.
   *
   * Retorna NULL se o valor não tiver exatamente oito dígitos.
   */
  public function normalize(string $cep): ?string {
    if (!preg_match('/^[ \t]*(\d{5})[ \t]*-?[ \t]*(\d{3})[ \t]*$/D', $cep, $matches)) {
      return NULL;
    }
    return $matches[1] . $matches[2];
  }

  /**
   * Retorna o endereço da primeira fonte ativa que encontrar o CEP.
   *
   * @return array<string, string>|null
   *   Endereço encontrado ou NULL se inválido/não localizado.
   */
  public function lookup(string $cep): ?array {
    $cep = $this->normalize($cep);
    if ($cep === NULL) {
      return NULL;
    }
    // A fonte padrão vem primeiro, seguida pelas demais na ordem configurada.
    foreach ($this->sources->ordered() as $source) {
      $address = $source === 'local' ? $this->local->lookup($cep) : $this->external($source, $cep);
      if ($address !== NULL) {
        return $address;
      }
    }
    return NULL;
  }

  /**
   * Consulta uma fonte externa e guarda somente resultados positivos.
   *
   * O cache varia por fonte para evitar reutilização entre provedores.
   */
  private function external(string $source, string $cep): ?array {
    $cacheId = 'aculta_portal:cep:' . $source . ':' . $cep;
    if ($cached = $this->cache->get($cacheId)) {
      return $cached->data;
    }
    $address = $source === 'viacep' ? $this->fromViaCep($cep) : $this->fromBrasilApi($cep);
    if ($address !== NULL) {
      $this->cache->set($cacheId, $address, time() + self::CACHE_TTL);
    }
    return $address;
  }

  /**
   * Consulta o ViaCEP, com uma nova tentativa curta.
   *
   * @return array<string, string>|null
   *   Endereço encontrado ou NULL em caso de falha.
   */
  private function fromViaCep(string $cep): ?array {
    for ($attempt = 0; $attempt < 2; $attempt++) {
      try {
        $response = $this->http->request(
          'GET',
          sprintf(self::VIACEP, $cep),
          ['timeout' => 5, 'http_errors' => FALSE],
        );
        $raw = json_decode((string) $response->getBody(), TRUE);
        if (is_array($raw) && empty($raw['erro'])) {
          return $this->mapped($cep, $raw['logradouro'] ?? '', $raw['bairro'] ?? '', $raw['localidade'] ?? '', $raw['uf'] ?? '');
        }
        if (is_array($raw) && !empty($raw['erro'])) {
          return NULL;
        }
      }
      catch (\Throwable $exception) {
        // Tenta de novo; depois cai para a outra fonte.
        $this->logger?->warning('ViaCEP lookup failed on attempt @attempt: @exception.', [
          '@attempt' => $attempt + 1,
          '@exception' => get_class($exception),
        ]);
      }
    }
    return NULL;
  }

  /**
   * Consulta a BrasilAPI (CEP v2).
   *
   * @return array<string, string>|null
   *   Endereço encontrado ou NULL em caso de falha.
   */
  private function fromBrasilApi(string $cep): ?array {
    try {
      $response = $this->http->request(
        'GET',
        sprintf(self::BRASILAPI, $cep),
        ['timeout' => 5, 'http_errors' => FALSE],
      );
      $raw = json_decode((string) $response->getBody(), TRUE);
      if (is_array($raw) && !empty($raw['cep'])) {
        return $this->mapped($cep, $raw['street'] ?? '', $raw['neighborhood'] ?? '', $raw['city'] ?? '', $raw['state'] ?? '');
      }
    }
    catch (\Throwable $exception) {
      // Sem fonte disponível.
      $this->logger?->warning('BrasilAPI lookup failed: @exception.', [
        '@exception' => get_class($exception),
      ]);
    }
    return NULL;
  }

  /**
   * Converte os campos da resposta externa para o formato do módulo.
   *
   * @return array<string, string>
   *   Campos compatíveis com Drupal Address.
   */
  private function mapped(string $cep, string $street, string $district, string $city, string $state): array {
    return [
      'postal_code' => $cep,
      'address_line1' => trim($street),
      'dependent_locality' => trim($district),
      'locality' => trim($city),
      'administrative_area' => trim($state),
    ];
  }

}
