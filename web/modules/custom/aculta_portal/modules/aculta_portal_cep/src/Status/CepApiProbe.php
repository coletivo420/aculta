<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Status;

use Drupal\Core\State\StateInterface;
use Drupal\Component\Datetime\TimeInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Verifica as APIs de CEP (ViaCEP e BrasilAPI) com um CEP fixo e guarda o resultado no State.
 *
 * Roda no cron (CepStatusCronHooks). O status da página lê o último resultado; não consulta a rede. Usa o mesmo
 * endereço e o mesmo timeout da consulta do Portal (CepLookupController).
 */
final class CepApiProbe {

  /** CEP de referência da verificação (Praça da Sé, São Paulo). */
  public const PROBE_CEP = '01001000';

  public const STATE_KEY = 'aculta_portal_cep.api_status';

  private const APIS = [
    'viacep' => ['label' => 'ViaCEP', 'url' => 'https://viacep.com.br/ws/%s/json/', 'key' => 'logradouro'],
    'brasilapi' => ['label' => 'BrasilAPI', 'url' => 'https://brasilapi.com.br/api/cep/v2/%s', 'key' => 'street'],
  ];

  public function __construct(
    #[Autowire(service: 'http_client')]
    private readonly ClientInterface $http,
    #[Autowire(service: 'state')]
    private readonly StateInterface $state,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
  ) {}

  /** Consulta cada API e grava o resultado. Retorna o resultado gravado. */
  public function check(): array {
    $result = [];
    foreach (self::APIS as $id => $api) {
      $started = microtime(TRUE);
      $http_status = NULL;
      $ok = FALSE;
      try {
        $response = $this->http->request('GET', sprintf($api['url'], self::PROBE_CEP), ['timeout' => 5, 'http_errors' => FALSE]);
        $http_status = $response->getStatusCode();
        $data = json_decode((string) $response->getBody(), TRUE);
        $ok = $http_status === 200 && is_array($data) && !empty($data[$api['key']]);
      }
      catch (\Throwable) {
        // Sem resposta: registrado como falha.
      }
      $result[$id] = [
        'label' => $api['label'],
        'ok' => $ok,
        'http_status' => $http_status,
        'ms' => (int) round((microtime(TRUE) - $started) * 1000),
        'checked_at' => $this->time->getRequestTime(),
      ];
    }
    $this->state->set(self::STATE_KEY, $result);
    return $result;
  }

  /** Último resultado gravado pelo cron, por API (vazio antes da primeira verificação). */
  public function last(): array {
    return $this->state->get(self::STATE_KEY, []);
  }

}
