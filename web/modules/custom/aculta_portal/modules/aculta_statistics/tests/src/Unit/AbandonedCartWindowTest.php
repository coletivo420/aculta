<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Reconciliation\AbandonedCartWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Janela de 24 horas dos carrinhos abandonados.
 */
#[CoversClass(AbandonedCartWindow::class)]
#[Group('aculta_statistics')]
final class AbandonedCartWindowTest extends TestCase {

  /**
   * A janela cobre o dia D-2 inteiro e termina antes de D-1 começar.
   */
  public function testWindowCoversTwoDaysBeforeCronDay(): void {
    $day = 20000;

    [$from, $to] = AbandonedCartWindow::bounds($day);

    $this->assertSame(($day - 2) * 86400, $from);
    $this->assertSame(($day - 1) * 86400 - 1, $to);
  }

  /**
   * Carrinho alterado no último instante da janela já está há mais de 24 horas parado no cron do dia D.
   */
  public function testLastWindowInstantIsAtLeastTwentyFourHoursBeforeCron(): void {
    $day = 20000;
    [, $to] = AbandonedCartWindow::bounds($day);
    $cronStart = $day * 86400;

    $this->assertGreaterThanOrEqual(86400, $cronStart - $to);
  }

}
