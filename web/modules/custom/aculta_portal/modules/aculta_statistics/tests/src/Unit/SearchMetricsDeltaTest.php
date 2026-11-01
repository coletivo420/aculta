<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Connector\SearchConsole\SearchMetricsDelta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Variações entre períodos do Search Console.
 */
#[CoversClass(SearchMetricsDelta::class)]
#[Group('aculta_statistics')]
final class SearchMetricsDeltaTest extends TestCase {

  /**
   * Sem período anterior não há comparação.
   */
  public function testNoPreviousPeriodReturnsNull(): void {
    $this->assertNull(SearchMetricsDelta::between(self::metrics(), NULL));
  }

  /**
   * Variações relativas, em pontos percentuais e em posição.
   */
  public function testComputesRelativeAndAbsoluteChanges(): void {
    $delta = SearchMetricsDelta::between(
      ['clicks' => 150, 'impressions' => 1000, 'ctr' => 0.15, 'position' => 8.0],
      ['clicks' => 100, 'impressions' => 2000, 'ctr' => 0.05, 'position' => 10.0],
    );

    $this->assertSame(50.0, $delta['clicks']['percent']);
    $this->assertSame(-50.0, $delta['impressions']['percent']);
    $this->assertEqualsWithDelta(10.0, $delta['ctr']['points'], 0.0001);
    $this->assertSame(-2.0, $delta['position']['value']);
  }

  /**
   * Anterior zero com atual positivo é indefinido, não "+100%".
   */
  public function testPercentFromZeroIsUndefined(): void {
    $this->assertNull(SearchMetricsDelta::percent(40.0, 0.0));
  }

  /**
   * Zero para zero é estável.
   */
  public function testPercentZeroToZeroIsZero(): void {
    $this->assertSame(0.0, SearchMetricsDelta::percent(0.0, 0.0));
  }

  /**
   * Métricas de um período.
   *
   * @return array{clicks: int, impressions: int, ctr: float, position: float}
   *   Valores de exemplo.
   */
  private static function metrics(): array {
    return ['clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 5.0];
  }

}
