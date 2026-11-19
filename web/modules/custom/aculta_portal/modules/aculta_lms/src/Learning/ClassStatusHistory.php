<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\aculta_statistics\Storage\AggregateStorageInterface;

/**
 * Reads aggregated daily class-status snapshots from ACULTA Statistics.
 */
final class ClassStatusHistory {

  public const METRIC = 'lms.students.by_status';

  /**
   * Stored status dimensions for class snapshots.
   *
   * @var list<string>
   */
  public const SITUATIONS = [
    'students',
    'not_started',
    'in_progress',
    'pending_evaluation',
    'passed',
    'failed',
  ];

  public const PERIODS = [7, 14, 30];

  public function __construct(
    private readonly ?AggregateStorageInterface $storage,
  ) {}

  /**
   * Normalizes a requested period to an allowed option.
   */
  public static function normalizePeriod(mixed $period): int {
    $period = filter_var($period, FILTER_VALIDATE_INT);
    return in_array($period, self::PERIODS, TRUE) ? $period : 7;
  }

  /**
   * Returns a daily series for one class, without student-level dimensions.
   *
   * @return array{days: int, from: int, to: int, points: list<array{day: int, counts: array<string, int>}>, has_data: bool}|null
   *   Daily aggregate data, or NULL when Statistics is not available.
   */
  public function series(int $courseId, int $classId, int $days): ?array {
    if ($this->storage === NULL) {
      return NULL;
    }
    if ($courseId < 1 || $classId < 1) {
      throw new \InvalidArgumentException('Curso e turma devem possuir IDs positivos.');
    }
    $days = self::normalizePeriod($days);
    $to = $this->storage->currentDay();
    $from = $to - $days + 1;
    $dimensions = [];
    foreach (self::SITUATIONS as $situation) {
      $dimensions[] = $courseId . '/' . $classId . '|' . $situation;
    }
    $daily = $this->storage->dailyDimensionTotalsForDimensions(self::METRIC, $from, $to, $dimensions);

    $points = [];
    for ($day = $from; $day <= $to; $day++) {
      $counts = [];
      foreach (self::SITUATIONS as $situation) {
        $dimension = $courseId . '/' . $classId . '|' . $situation;
        $counts[$situation] = $daily[$day][$dimension] ?? 0;
      }
      $points[] = ['day' => $day, 'counts' => $counts];
    }
    return [
      'days' => $days,
      'from' => $from,
      'to' => $to,
      'points' => $points,
      'has_data' => $daily !== [],
    ];
  }

}
