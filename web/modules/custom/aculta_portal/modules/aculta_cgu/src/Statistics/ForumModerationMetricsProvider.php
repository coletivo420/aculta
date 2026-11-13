<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Statistics;

use Drupal\aculta_cgu\Service\ReportService;
use Drupal\aculta_portal\Statistics\MetricProviderInterface;
use Drupal\Core\Database\Connection;

/**
 * Indicadores de moderação do Fórum a partir da denúncia UGC.
 *
 * O owner real é o `aculta_cgu`: a entidade `aculta_cgu_report` guarda
 * situação, `created` e `decided_at`. Nenhum denunciante, alvo nominal,
 * parecer ou conteúdo sai daqui; apenas contagens e tempo agregado.
 *
 * Escopo: denúncias com purpose `forum`. A denúncia preserva o purpose
 * canônico registrado no ato; o indicador não revalida o título do alvo nem
 * transforma a métrica em acesso ao conteúdo.
 */
final class ForumModerationMetricsProvider implements MetricProviderInterface {

  /**
   * Tabela da denúncia UGC.
   */
  public const TABLE = 'aculta_cgu_report';

  /**
   * Estados ainda abertos.
   */
  private const OPEN_STATUSES = [
    ReportService::STATUS_PENDING,
    ReportService::STATUS_REVIEW,
  ];

  /**
   * Estados decididos.
   */
  private const RESOLVED_STATUSES = [
    ReportService::STATUS_UPHELD,
    ReportService::STATUS_REJECTED,
  ];

  /**
   * Coorte máxima do tempo de resolução, em dias.
   */
  private const RESOLUTION_COHORT_DAYS = 365;

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function provides(): array {
    return [
      'forum.moderation.reports_open',
      'forum.moderation.reports_resolved',
      'forum.moderation.resolution_median_minutes',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function value(string $metric, int $day): ?int {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      return NULL;
    }
    $windowEnd = ($day + 1) * 86400;
    return match ($metric) {
      'forum.moderation.reports_open' => $this->countOpen(),
      'forum.moderation.reports_resolved' => $this->countResolved($day * 86400, $windowEnd),
      'forum.moderation.resolution_median_minutes' => $this->resolutionMedian($windowEnd - self::RESOLUTION_COHORT_DAYS * 86400, $windowEnd),
      default => NULL,
    };
  }

  /**
   * Conteúdos distintos com denúncia ainda aberta no purpose forum.
   */
  private function countOpen(): int {
    $query = $this->database->select(self::TABLE, 'r');
    $query->addField('r', 'target_type');
    $query->addField('r', 'target_id');
    $query->condition('r.purpose', 'forum');
    $query->condition('r.status', self::OPEN_STATUSES, 'IN');
    $query->distinct();
    return count($query->execute()->fetchAll());
  }

  /**
   * Denúncias decididas cuja decisão ocorreu na janela.
   */
  private function countResolved(int $from, int $to): int {
    return (int) $this->database->select(self::TABLE, 'r')
      ->condition('r.purpose', 'forum')
      ->condition('r.status', self::RESOLVED_STATUSES, 'IN')
      ->condition('r.decided_at', $from, '>=')
      ->condition('r.decided_at', $to, '<')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Mediana de created -> decided_at, em minutos, na coorte.
   */
  private function resolutionMedian(int $from, int $to): ?int {
    $query = $this->database->select(self::TABLE, 'r');
    $query->fields('r', ['created', 'decided_at']);
    $query->condition('r.purpose', 'forum');
    $query->condition('r.status', self::RESOLVED_STATUSES, 'IN');
    $query->condition('r.decided_at', $from, '>=')
      ->condition('r.decided_at', $to, '<')
      ->condition('r.decided_at', 0, '>');
    $minutes = [];
    foreach ($query->execute() as $row) {
      $diff = (int) $row->decided_at - (int) $row->created;
      if ($diff >= 0) {
        $minutes[] = intdiv($diff, 60);
      }
    }
    if ($minutes === []) {
      return NULL;
    }
    sort($minutes);
    $count = count($minutes);
    $middle = intdiv($count, 2);
    return $count % 2 === 1
      ? $minutes[$middle]
      : (int) round(($minutes[$middle - 1] + $minutes[$middle]) / 2);
  }

}
