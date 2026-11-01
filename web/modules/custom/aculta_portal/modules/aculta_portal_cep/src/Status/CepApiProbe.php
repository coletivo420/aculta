<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Status;

use Drupal\aculta_portal_cep\Settings\CepSources;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Datetime\TimeInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Checks the configured CEP APIs and stores their latest results.
 *
 * Cron invokes the probe. Status pages read the stored results without making
 * network requests. The probe uses the same endpoint and timeout as lookups.
 */
final class CepApiProbe {

  /**
   * Reference CEP used to check provider availability.
   */
  public const PROBE_CEP = '01001000';

  public const STATE_KEY = 'aculta_portal_cep.api_status';

  /**
   * Minimum interval between automatic provider checks, in seconds.
   */
  public const INTERVAL = 86400;

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
    #[Autowire(service: 'aculta_portal_cep.sources')]
    private readonly CepSources $sources,
  ) {}

  /**
   * Checks enabled APIs and saves their results to state.
   */
  public function check(): array {
    $result = [];
    foreach (self::APIS as $id => $api) {
      // Fonte desativada na configuração não é consultada.
      if (!$this->sources->enabled($id)) {
        $result[$id] = [
          'label' => $api['label'],
          'ok' => FALSE,
          'disabled' => TRUE,
          'http_status' => NULL,
          'ms' => 0,
          'checked_at' => $this->time->getRequestTime(),
        ];
        continue;
      }
      $started = microtime(TRUE);
      $http_status = NULL;
      $ok = FALSE;
      try {
        $response = $this->http->request(
          'GET',
          sprintf($api['url'], self::PROBE_CEP),
          ['timeout' => 5, 'http_errors' => FALSE],
        );
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

  /**
   * Checks providers only when the configured interval has elapsed.
   *
   * @return array<string, array<string, mixed>>|null
   *   New provider results, or NULL when the last check is still current.
   */
  public function checkIfDue(): ?array {
    $last = $this->last();
    $checked = $last === [] ? 0 : max(array_map(static fn (array $api): int => (int) $api['checked_at'], $last));
    if ($checked > 0 && $this->time->getRequestTime() - $checked < self::INTERVAL) {
      return NULL;
    }
    return $this->check();
  }

  /**
   * Returns the most recent stored result for each API.
   *
   * @return array<string, array<string, mixed>>
   *   Stored provider results, or an empty array before the first check.
   */
  public function last(): array {
    return $this->state->get(self::STATE_KEY, []);
  }

}
