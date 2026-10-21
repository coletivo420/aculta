<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Service;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Fachada dos cliques de links curtos sobre o `aculta_statistics`.
 *
 * O agregado é da central de estatísticas (`links.link.clicks`, dimensão = destino).
 * Este serviço só decide se a coleta está habilitada e lê os totais do destino.
 * Não guarda visitante, IP nem tabela própria (ADR-0004).
 */
final class LinkAnalytics {

  /**
   * Métrica do catálogo do aculta_statistics usada para cliques.
   */
  public const METRIC_CLICKS = 'links.link.clicks';

  /**
   * Prazo de leitura dos cliques, em dias UTC (igual ao prazo da métrica).
   */
  public const RETENTION_DAYS = 90;

  private const DAY_SECONDS = 86400;

  public function __construct(
    private readonly StatisticsRecorder $recorder,
    private readonly AggregateStorageInterface $storage,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Conta um clique no destino, quando o administrador habilitou a coleta.
   */
  public function recordClick(int $linkId): void {
    if ($linkId < 1 || !$this->isEnabled()) {
      return;
    }
    $this->recorder->record(self::METRIC_CLICKS, (string) $linkId);
  }

  /**
   * Indica se o administrador habilitou a coleta de cliques de links.
   */
  public function isEnabled(): bool {
    return $this->configFactory->get('aculta_links.settings')->get('analytics_enabled') === TRUE;
  }

  /**
   * Totais de cliques por destino, nos dias do prazo.
   *
   * @param int[] $linkIds
   *   Destinos já filtrados pelo acesso da entidade.
   *
   * @return int[]
   *   Destino para total de cliques. Destinos sem clique não aparecem.
   */
  public function clickCounts(array $linkIds): array {
    $linkIds = array_values(array_unique(array_filter(array_map('intval', $linkIds), static fn (int $id): bool => $id > 0)));
    $today = $this->storage->currentDay();
    $from = $today - self::RETENTION_DAYS + 1;

    $counts = [];
    foreach ($linkIds as $linkId) {
      $total = $this->storage->total(self::METRIC_CLICKS, $from, $today, (string) $linkId);
      if ($total > 0) {
        $counts[$linkId] = $total;
      }
    }
    return $counts;
  }

}
