<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Report\StatisticsSummaryLines;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Linhas do resumo do administrador a partir dos agregados.
 */
#[CoversClass(StatisticsSummaryLines::class)]
#[Group('aculta_statistics')]
final class StatisticsSummaryLinesTest extends TestCase {

  /**
   * Contagens somam; valores por moeda aparecem em formato brasileiro.
   */
  public function testCountsAndMoneyAreFormatted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('dimensions')->willReturnCallback(static function (string $metric): array {
      return $metric === 'commerce.revenue.gross' ? ['BRL'] : [];
    });
    $storage->method('total')->willReturnCallback(static function (string $metric, int $from, int $to, ?string $dim = NULL): int {
      return match ($metric) {
        'commerce.orders.paid' => 12,
        'commerce.revenue.gross' => $dim === 'BRL' ? 123456 : 0,
        default => 0,
      };
    });

    $lines = (new StatisticsSummaryLines($storage))->lines(0, 7 * 86400);

    $this->assertContains('Pedidos pagos: 12', $lines);
    $this->assertContains('Receita bruta (BRL): 1.234,56', $lines);
    $this->assertNotContains('Reembolsos (BRL): 0,00', $lines);
  }

  /**
   * Sem moeda registrada, não há linha de receita por moeda.
   */
  public function testNoCurrencyMeansNoMoneyLine(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('dimensions')->willReturn([]);
    $storage->method('total')->willReturn(0);

    $lines = (new StatisticsSummaryLines($storage))->lines(0, 86400);

    foreach ($lines as $line) {
      $this->assertStringNotContainsString('(', $line);
    }
  }

}
