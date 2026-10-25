<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Privacy\CellSuppression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Célula pequena não aparece em painel público.
 */
#[CoversClass(CellSuppression::class)]
#[Group('aculta_statistics')]
final class CellSuppressionTest extends TestCase {

  /**
   * Abaixo do mínimo, nada é exibido.
   */
  public function testBelowMinimumIsSuppressed(): void {
    $this->assertNull(CellSuppression::publicCount(CellSuppression::MINIMUM_PUBLIC_COUNT - 1));
  }

  /**
   * No mínimo ou acima, o número é exibido.
   */
  public function testAtOrAboveMinimumIsShown(): void {
    $this->assertSame(CellSuppression::MINIMUM_PUBLIC_COUNT, CellSuppression::publicCount(CellSuppression::MINIMUM_PUBLIC_COUNT));
    $this->assertSame(12, CellSuppression::publicCount(12));
  }

  /**
   * O mínimo acordado é cinco alunos.
   */
  public function testMinimumIsFive(): void {
    $this->assertSame(5, CellSuppression::MINIMUM_PUBLIC_COUNT);
  }

}
