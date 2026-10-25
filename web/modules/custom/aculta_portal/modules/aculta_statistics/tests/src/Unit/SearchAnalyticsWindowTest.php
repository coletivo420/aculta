<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Connector\SearchConsole\SearchAnalyticsWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Janela de datas das consultas ao Search Console.
 */
#[CoversClass(SearchAnalyticsWindow::class)]
#[Group('aculta_statistics')]
final class SearchAnalyticsWindowTest extends TestCase {

  /**
   * Janela inclusiva de N dias termina no dia de referência.
   */
  public function testWindowIsInclusiveAndEndsToday(): void {
    $window = SearchAnalyticsWindow::forDays(28, new \DateTimeImmutable('2026-10-20 15:30:00'));

    $this->assertSame(['startDate' => '2026-09-23', 'endDate' => '2026-10-20'], $window);
  }

  /**
   * Um dia é a própria data de referência.
   */
  public function testOneDayWindowIsSingleDate(): void {
    $window = SearchAnalyticsWindow::forDays(1, new \DateTimeImmutable('2026-10-20'));

    $this->assertSame(['startDate' => '2026-10-20', 'endDate' => '2026-10-20'], $window);
  }

  /**
   * Janelas fora do intervalo aceito são recusadas.
   */
  #[DataProvider('invalidWindows')]
  public function testRejectsWindowsOutsideRange(int $days): void {
    $this->expectException(\InvalidArgumentException::class);
    SearchAnalyticsWindow::forDays($days, new \DateTimeImmutable('2026-10-20'));
  }

  /**
   * Casos de janela inválida.
   *
   * @return array<string, array{int}>
   *   Janelas inválidas.
   */
  public static function invalidWindows(): array {
    return [
      'zero' => [0],
      'negativa' => [-7],
      'acima do limite' => [SearchAnalyticsWindow::MAX_DAYS + 1],
    ];
  }

}
