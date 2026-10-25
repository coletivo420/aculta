<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Reconciliation\ReconciliationDiff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Comparação entre a fonte oficial e os contadores.
 */
#[CoversClass(ReconciliationDiff::class)]
#[Group('aculta_statistics')]
final class ReconciliationDiffTest extends TestCase {

  /**
   * Totais iguais não geram divergência.
   */
  public function testEqualTotalsHaveNoDifference(): void {
    $totals = ['BRL' => ['orders' => 2, 'cents' => 3050]];

    $this->assertSame([], ReconciliationDiff::compare($totals, $totals));
  }

  /**
   * Pedido a mais no contador aparece com diferença positiva.
   */
  public function testCountedExtraOrderIsReported(): void {
    $diff = ReconciliationDiff::compare(
      ['BRL' => ['orders' => 1, 'cents' => 1000]],
      ['BRL' => ['orders' => 2, 'cents' => 2000]],
    );

    $this->assertSame(['BRL' => ['orders' => 1, 'cents' => 1000]], $diff);
  }

  /**
   * Moeda presente só no oficial aparece como falta no contador.
   */
  public function testMissingCurrencyIsReported(): void {
    $diff = ReconciliationDiff::compare(['USD' => ['orders' => 1, 'cents' => 500]], []);

    $this->assertSame(['USD' => ['orders' => -1, 'cents' => -500]], $diff);
  }

  /**
   * Diferença só em centavos é reportada, mesmo com a mesma quantidade de pedidos.
   */
  public function testCentsOnlyDifferenceIsReported(): void {
    $diff = ReconciliationDiff::compare(
      ['BRL' => ['orders' => 1, 'cents' => 1050]],
      ['BRL' => ['orders' => 1, 'cents' => 1000]],
    );

    $this->assertSame(['BRL' => ['orders' => 0, 'cents' => -50]], $diff);
  }

}
