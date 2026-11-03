<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Xapi\XapiRetention;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers xAPI statement retention (D-68, ADR-041).
 */
#[CoversClass(XapiRetention::class)]
final class XapiRetentionTest extends UnitTestCase {

  // 2026-11-02T00:00:00Z.
  private const NOW = 1793577600;

  /**
   * Valor ausente cai no padrão de 6 meses.
   */
  public function testMissingValueUsesSixMonths(): void {
    $this->assertSame(6, XapiRetention::monthsFrom(NULL));
  }

  /**
   * Valor válido, inteiro ou texto numérico, é usado; fora da faixa cai no padrão.
   */
  public function testValidAndInvalidValues(): void {
    $this->assertSame(12, XapiRetention::monthsFrom('12'));
    $this->assertSame(XapiRetention::DEFAULT_MONTHS, XapiRetention::monthsFrom(0));
    $this->assertSame(XapiRetention::DEFAULT_MONTHS, XapiRetention::monthsFrom(XapiRetention::MAX_MONTHS + 1));
    $this->assertSame(XapiRetention::DEFAULT_MONTHS, XapiRetention::monthsFrom('seis'));
  }

  /**
   * Com 6 meses, o corte fica seis meses antes do instante atual.
   */
  public function testCutoffSubtractsMonthsInUtc(): void {
    $this->assertSame(1777680000, XapiRetention::cutoff(self::NOW, 6));
  }

}
