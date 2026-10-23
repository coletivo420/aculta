<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Lookup;

use Drupal\aculta_portal_cep\Local\CepLocalStore;
use Drupal\aculta_portal_cep\Settings\CepSources;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Consulta de CEP reutilizável: base local, ViaCEP e BrasilAPI, nesta ordem configurada (CepSources).
 *
 * É a única implementação da consulta. O endpoint AJAX (CepLookupController) e os módulos PHP (como o Melhor Envio)
 * chamam este serviço. Nenhum deles faz requisição HTTP à rota AJAX.
 *
 * Devolve o mesmo formato de Drupal Address: postal_code, address_line1, dependent_locality, locality e
 * administrative_area. Não guarda endereço: o resultado é só uma sugestão para quem chamou.
 */
final class CepLookupService {

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
  ) {}

  /**
   * Normaliza um CEP (com ou sem máscara) para 8 dígitos. NULL quando não tem exatamente 8 dígitos.
   */
  public function normalize(string $cep): ?string {
    $digits = preg_replace('/\D+/', '', $cep) ?? '';
    return strlen($digits) === 8 ? $digits : NULL;
  }

  /**
   * Endereço do CEP na primeira fonte ativa que o encontrar. NULL quando nenhuma acha ou o CEP é inválido.
   *
   * @return array<string, string>|null
   */
  public function lookup(string $cep): ?array {
    $cep = $this->normalize($cep);
    if ($cep === NULL) {
      return NULL;
    }
    // Fontes na ordem da configuração (CepSources): a padrão primeiro, depois as demais.
    foreach ($this->sources->ordered() as $source) {
      $address = $source === 'local' ? $this->local->lookup($cep) : $this->external($source, $cep);
      if ($address !== NULL) {
        return $address;
      }
    }
    return NULL;
  }

  /** Consulta externa (ViaCEP ou BrasilAPI) com cache por CEP. Só resultado positivo é guardado. */
  private function external(string $source, string $cep): ?array {
    $cacheId = 'aculta_portal:cep:' . $cep;
    if ($cached = $this->cache->get($cacheId)) {
      return $cached->data;
    }
    $address = $source === 'viacep' ? $this->fromViaCep($cep) : $this->fromBrasilApi($cep);
    if ($address !== NULL) {
      $this->cache->set($cacheId, $address, time() + self::CACHE_TTL);
    }
    return $address;
  }

  /** ViaCEP, com uma nova tentativa curta. NULL quando falha ou não encontra. */
  private function fromViaCep(string $cep): ?array {
    for ($attempt = 0; $attempt < 2; $attempt++) {
      try {
        $raw = json_decode((string) $this->http->request('GET', sprintf(self::VIACEP, $cep), ['timeout' => 5, 'http_errors' => FALSE])->getBody(), TRUE);
        if (is_array($raw) && empty($raw['erro'])) {
          return $this->mapped($cep, $raw['logradouro'] ?? '', $raw['bairro'] ?? '', $raw['localidade'] ?? '', $raw['uf'] ?? '');
        }
        if (is_array($raw) && !empty($raw['erro'])) {
          return NULL;
        }
      }
      catch (\Throwable) {
        // Tenta de novo; depois cai para a outra fonte.
      }
    }
    return NULL;
  }

  /** BrasilAPI (CEP v2). NULL quando falha ou não encontra. */
  private function fromBrasilApi(string $cep): ?array {
    try {
      $raw = json_decode((string) $this->http->request('GET', sprintf(self::BRASILAPI, $cep), ['timeout' => 5, 'http_errors' => FALSE])->getBody(), TRUE);
      if (is_array($raw) && !empty($raw['cep'])) {
        return $this->mapped($cep, $raw['street'] ?? '', $raw['neighborhood'] ?? '', $raw['city'] ?? '', $raw['state'] ?? '');
      }
    }
    catch (\Throwable) {
      // Sem fonte disponível.
    }
    return NULL;
  }

  /** @return array<string, string> */
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
