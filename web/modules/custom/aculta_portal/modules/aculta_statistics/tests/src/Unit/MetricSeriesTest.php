<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Report\MetricSeries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Período do painel (7, 14 ou 30 dias) e série diária com total.
 */
#[CoversClass(MetricSeries::class)]
#[Group('aculta_statistics')]
final class MetricSeriesTest extends TestCase {

  /**
   * Só 7, 14 e 30 são aceitos; qualquer outro valor volta ao padrão.
   */
  public function testPeriodIsRestrictedToTheSelectableSet(): void {
    $this->assertSame(14, MetricSeries::normalizePeriod('14'));
    $this->assertSame(30, MetricSeries::normalizePeriod(30));
    $this->assertSame(MetricSeries::DEFAULT_PERIOD, MetricSeries::normalizePeriod('90'));
    $this->assertSame(MetricSeries::DEFAULT_PERIOD, MetricSeries::normalizePeriod(NULL));
    $this->assertSame(MetricSeries::DEFAULT_PERIOD, MetricSeries::normalizePeriod(['x']));
  }

  /**
   * A série tem um ponto por dia, em ordem, terminando no dia informado, com o total do período.
   */
  public function testBuildCoversEveryDayInOrder(): void {
    $today = 20000;
    $series = MetricSeries::build($today, 3, static fn (int $day): int => $day - 19997);

    $this->assertSame([1, 2, 3], $series['values']);
    $this->assertSame(6, $series['total']);
    $this->assertCount(3, $series['labels']);
    $this->assertSame(gmdate('d/m', $today * 86400), end($series['labels']));
  }

  /**
   * Dia sem registro aparece com zero, para o gráfico não pular dias.
   */
  public function testMissingDaysAreZero(): void {
    $series = MetricSeries::build(20000, 7, static fn (int $day): int => $day === 19996 ? 5 : 0);

    $this->assertSame([0, 0, 5, 0, 0, 0, 0], $series['values']);
    $this->assertSame(5, $series['total']);
  }

}
