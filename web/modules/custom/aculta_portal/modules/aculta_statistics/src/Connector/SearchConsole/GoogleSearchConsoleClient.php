<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Connector\SearchConsole;

use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Cliente do Search Console (Search Analytics, somente leitura) com `google/apiclient` (ADR-0006).
 *
 * Pede cliques e impressões por dia. Não soma posição (a API já a agrega) e não envia nada do visitante.
 * Desligado sem propriedade e credencial. Erro vira NULL e aviso sem conteúdo da credencial.
 */
final class GoogleSearchConsoleClient {

  /**
   * Escopo somente leitura do Search Console.
   */
  public const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

  /**
   * @var null|callable(string, array): array
   */
  private $runner;

  /**
   * @param callable(string, array): array|null $runner
   *   Substituto da chamada à API (teste). NULL usa a biblioteca oficial.
   */
  public function __construct(
    private readonly SearchConsoleSettings $settings,
    private readonly LoggerChannelInterface $logger,
    ?callable $runner = NULL,
  ) {
    $this->runner = $runner;
  }

  /**
   * Indica se a propriedade e a credencial estão configuradas.
   */
  public function isEnabled(): bool {
    return $this->settings->siteUrl() !== NULL && $this->settings->serviceAccount() !== NULL;
  }

  /**
   * Série diária de cliques e impressões nos últimos `$startDate` a `$endDate` (Y-m-d, inclusive), ou NULL.
   *
   * @return array<string, array{clicks: int, impressions: int}>|null
   */
  public function report(string $startDate, string $endDate): ?array {
    $site = $this->settings->siteUrl();
    if ($site === NULL || $this->settings->serviceAccount() === NULL) {
      return NULL;
    }
    $request = ['startDate' => $startDate, 'endDate' => $endDate, 'dimensions' => ['date'], 'dataState' => 'all'];
    try {
      $rows = $this->runner !== NULL ? ($this->runner)($site, $request) : $this->callApi($site, $request);
    }
    catch (\Throwable $exception) {
      $this->logger->warning('Consulta ao Search Console falhou (@type). Os dados não foram atualizados.', ['@type' => $exception::class]);
      return NULL;
    }
    return $this->normalize($rows);
  }

  /**
   * Converte linhas (`date`, `clicks`, `impressions`) em `['Y-m-d' => ['clicks' => int, 'impressions' => int]]`.
   *
   * @param array<int, array<string, mixed>> $rows
   *   Linhas da API, já simplificadas.
   *
   * @return array<string, array{clicks: int, impressions: int}>
   */
  public function normalize(array $rows): array {
    $out = [];
    foreach ($rows as $row) {
      $day = (string) ($row['date'] ?? '');
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
        continue;
      }
      $out[$day] = ['clicks' => (int) ($row['clicks'] ?? 0), 'impressions' => (int) ($row['impressions'] ?? 0)];
    }
    ksort($out);
    return $out;
  }

  /**
   * Chamada real ao Search Analytics (query) com a biblioteca oficial.
   *
   * @param array<string, mixed> $request
   *   Intervalo, dimensão e estado dos dados.
   *
   * @return array<int, array<string, mixed>>
   */
  private function callApi(string $site, array $request): array {
    $client = new \Google\Client();
    $client->setAuthConfig($this->settings->serviceAccount());
    $client->addScope(self::SCOPE);
    $service = new \Google\Service\SearchConsole($client);

    $body = new \Google\Service\SearchConsole\SearchAnalyticsQueryRequest();
    $body->setStartDate($request['startDate']);
    $body->setEndDate($request['endDate']);
    $body->setDimensions($request['dimensions']);
    $body->setDataState($request['dataState']);

    $response = $service->searchanalytics->query($site, $body);
    $rows = [];
    foreach ($response->getRows() ?? [] as $row) {
      $keys = $row->getKeys() ?? [];
      $rows[] = [
        'date' => (string) ($keys[0] ?? ''),
        'clicks' => (int) $row->getClicks(),
        'impressions' => (int) $row->getImpressions(),
      ];
    }
    return $rows;
  }

}
