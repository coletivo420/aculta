<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Geocoding;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Geocodificação do endereço de um local, no servidor (Geocoding API, ADR-014).
 *
 * A chave vem do Key `google_maps_api_key` (provisionada em aculta_secrets, nunca no código).
 * Sem chave, não há chamada externa: o local fica sem coordenadas e a lista textual continua.
 * O endereço não vai para o log: só o status da resposta.
 */
final class GeocodingService {

  /**
   * Endpoint da Geocoding API.
   */
  private const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

  /**
   * Construtor.
   */
  public function __construct(
    private readonly ClientInterface $http,
    private readonly KeyRepositoryInterface $chaves,
    private readonly LoggerChannelFactoryInterface $logs,
  ) {}

  /**
   * Coordenadas de um endereço em texto, ou NULL se não há chave, não encontrou ou falhou.
   *
   * @return array{lat: float, lng: float}|null
   */
  public function geocodificar(string $endereco): ?array {
    $chave = $this->chave();
    if ($chave === NULL || trim($endereco) === '') {
      return NULL;
    }
    try {
      $resposta = $this->http->request('GET', self::ENDPOINT, [
        'query' => ['address' => $endereco, 'region' => 'br', 'key' => $chave],
        'timeout' => 10,
      ]);
      $dados = json_decode((string) $resposta->getBody(), TRUE);
    }
    catch (\Throwable $erro) {
      $this->logs->get('aculta_events')->warning('Geocodificação falhou: @classe.', ['@classe' => $erro::class]);
      return NULL;
    }
    return $this->interpretar(is_array($dados) ? $dados : []);
  }

  /**
   * Interpreta a resposta da Geocoding API. Função pura, testável sem rede.
   *
   * @return array{lat: float, lng: float}|null
   */
  public function interpretar(array $dados): ?array {
    if (($dados['status'] ?? '') !== 'OK') {
      $this->logs->get('aculta_events')->notice('Geocodificação sem resultado. Status: @status.', ['@status' => (string) ($dados['status'] ?? 'desconhecido')]);
      return NULL;
    }
    $local = $dados['results'][0]['geometry']['location'] ?? NULL;
    if (!is_array($local) || !isset($local['lat'], $local['lng'])) {
      return NULL;
    }
    return ['lat' => (float) $local['lat'], 'lng' => (float) $local['lng']];
  }

  /**
   * Chave da API, ou NULL quando não provisionada neste ambiente.
   */
  public function chave(): ?string {
    $chave = $this->chaves->getKey('google_maps_api_key');
    $valor = $chave?->getKeyValue();
    return is_string($valor) && $valor !== '' ? $valor : NULL;
  }

}
