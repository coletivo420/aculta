<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Consulta de CEP do Portal, com os campos do endereço do Commerce (address module).
 *
 * Campos devolvidos: postal_code, address_line1 (logradouro), dependent_locality (bairro), locality (cidade),
 * administrative_area (UF). O complemento do ViaCEP (faixa de numeração, "lado par") não é usado: é do logradouro,
 * não do usuário. Fontes: ViaCEP (com uma nova tentativa) e, se falhar ou não encontrar, BrasilAPI. Resultado em
 * cache por CEP. Só responde a requisição AJAX; nunca em cache de página ou proxy.
 */
final class CepLookupController extends ControllerBase {

  private const VIACEP = 'https://viacep.com.br/ws/%s/json/';
  private const BRASILAPI = 'https://brasilapi.com.br/api/cep/v2/%s';
  private const CACHE_TTL = 2592000;

  public function __construct(
    #[Autowire(service: 'http_client')]
    private readonly ClientInterface $http,
    #[Autowire(service: 'cache.data')]
    private readonly CacheBackendInterface $cache,
  ) {}

  public function lookup(string $cep, Request $request): JsonResponse {
    if (!$request->isXmlHttpRequest()) {
      throw new AccessDeniedHttpException();
    }
    $cep = preg_replace('/\D+/', '', $cep) ?? '';
    $cacheId = 'aculta_portal:cep:' . $cep;
    if ($cached = $this->cache->get($cacheId)) {
      return $this->respond($cached->data);
    }
    $address = $this->fromViaCep($cep) ?? $this->fromBrasilApi($cep);
    if ($address === NULL) {
      $response = new JsonResponse(['ok' => FALSE, 'message' => (string) $this->t('Não encontramos este CEP. Confira o número ou preencha o endereço manualmente.')], 404);
      $response->headers->set('Cache-Control', 'private, no-store');
      return $response;
    }
    $this->cache->set($cacheId, $address, time() + self::CACHE_TTL);
    return $this->respond($address);
  }

  private function respond(array $address): JsonResponse {
    $response = new JsonResponse(['ok' => TRUE, 'data' => $address]);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('Vary', 'X-Requested-With, Cookie');
    return $response;
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
