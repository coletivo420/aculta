<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Connector\Ga4;

use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Cliente GA4 Data API (runReport) com a biblioteca oficial `google/apiclient` (ADR-0006).
 *
 * Somente leitura: pede a série diária de sessões e usuários ativos da propriedade. Não envia nada do visitante.
 * Desligado enquanto faltarem propriedade ou credencial válidas. Erros viram NULL e um aviso sem conteúdo da
 * credencial. A chamada à API fica em `callApi()`; em teste, `$runner` substitui essa chamada.
 */
final class GoogleGa4ReportClient implements Ga4ReportClient {

  /**
   * Escopo somente leitura da Analytics Data API.
   */
  public const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

  /**
   * Quando informado, recebe (ID da propriedade, corpo do pedido) e devolve linhas no formato de `normalize()`.
   *
   * @var null|callable(string, array): array
   */
  private $runner;

  /**
   * @param callable(string, array): array|null $runner
   *   Substituto da chamada à API (teste). NULL usa a biblioteca oficial.
   */
  public function __construct(
    private readonly Ga4Settings $settings,
    private readonly LoggerChannelInterface $logger,
    ?callable $runner = NULL,
  ) {
    $this->runner = $runner;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return $this->settings->propertyId() !== NULL && $this->settings->serviceAccount() !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function report(int $fromDay, int $toDay): ?array {
    $propertyId = $this->settings->propertyId();
    if ($propertyId === NULL || $this->settings->serviceAccount() === NULL) {
      return NULL;
    }
    $request = [
      'dateRanges' => [['startDate' => gmdate('Y-m-d', $fromDay * 86400), 'endDate' => gmdate('Y-m-d', $toDay * 86400)]],
      'dimensions' => ['date'],
      'metrics' => ['sessions', 'activeUsers'],
    ];
    try {
      $rows = $this->runner !== NULL ? ($this->runner)($propertyId, $request) : $this->callApi($propertyId, $request);
    }
    catch (\Throwable $exception) {
      // Só o tipo da exceção: a mensagem pode conter detalhes da credencial ou da resposta.
      $this->logger->warning('Consulta ao GA4 falhou (@type). Os dados não foram atualizados.', ['@type' => $exception::class]);
      return NULL;
    }
    return $this->normalize($rows);
  }

  /**
   * Converte as linhas da API em `['Y-m-d' => ['sessions' => int, 'activeUsers' => int]]`.
   *
   * @param array<int, array<string, mixed>> $rows
   *   Linhas com `date` (AAAAMMDD), `sessions` e `activeUsers` como texto ou número.
   *
   * @return array<string, array{sessions: int, activeUsers: int}>
   */
  public function normalize(array $rows): array {
    $out = [];
    foreach ($rows as $row) {
      $raw = (string) ($row['date'] ?? '');
      if (preg_match('/^\d{8}$/', $raw) !== 1) {
        continue;
      }
      $day = substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2);
      $out[$day] = [
        'sessions' => (int) ($row['sessions'] ?? 0),
        'activeUsers' => (int) ($row['activeUsers'] ?? 0),
      ];
    }
    ksort($out);
    return $out;
  }

  /**
   * Chamada real à Analytics Data API (runReport) com a biblioteca oficial. Devolve linhas simples.
   *
   * @param array<string, mixed> $request
   *   Corpo do pedido (intervalo, dimensões e métricas).
   *
   * @return array<int, array<string, string>>
   */
  private function callApi(string $propertyId, array $request): array {
    $client = new \Google\Client();
    $client->setAuthConfig($this->settings->serviceAccount());
    $client->addScope(self::SCOPE);
    $service = new \Google\Service\AnalyticsData($client);

    $body = new \Google\Service\AnalyticsData\RunReportRequest();
    $range = new \Google\Service\AnalyticsData\DateRange();
    $range->setStartDate($request['dateRanges'][0]['startDate']);
    $range->setEndDate($request['dateRanges'][0]['endDate']);
    $body->setDateRanges([$range]);
    $body->setDimensions(array_map(static fn (string $name): \Google\Service\AnalyticsData\Dimension => (new \Google\Service\AnalyticsData\Dimension())->setName($name), $request['dimensions']));
    $body->setMetrics(array_map(static fn (string $name): \Google\Service\AnalyticsData\Metric => (new \Google\Service\AnalyticsData\Metric())->setName($name), $request['metrics']));

    $response = $service->properties->runReport('properties/' . $propertyId, $body);
    $rows = [];
    foreach ($response->getRows() ?? [] as $row) {
      $line = ['date' => (string) $row->getDimensionValues()[0]->getValue()];
      $metrics = $row->getMetricValues();
      $line['sessions'] = (string) ($metrics[0]->getValue() ?? '0');
      $line['activeUsers'] = (string) ($metrics[1]->getValue() ?? '0');
      $rows[] = $line;
    }
    return $rows;
  }

}
