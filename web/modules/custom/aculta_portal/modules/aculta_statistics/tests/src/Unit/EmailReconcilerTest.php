<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Email\EmailFunnel;
use Drupal\aculta_statistics\Email\EmailProviderInterface;
use Drupal\aculta_statistics\Reconciliation\EmailReconciler;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Conciliação de e-mails: cada métrica de cada dia é substituída, inclusive quando vazia.
 */
#[CoversClass(EmailReconciler::class)]
#[Group('aculta_statistics')]
final class EmailReconcilerTest extends TestCase {

  /**
   * Para cada dia do intervalo, todas as métricas são substituídas; as ausentes vão vazias.
   */
  public function testEveryMetricOfEveryDayIsReplaced(): void {
    $provider = $this->createMock(EmailProviderInterface::class);
    $provider->method('countsForDay')->willReturnCallback(static function (int $day): array {
      $counts = array_fill_keys(EmailFunnel::METRICS, []);
      if ($day === 11) {
        $counts[EmailFunnel::METRIC_ACCEPTED] = ['smtp' => 3];
      }
      return $counts;
    });

    $replaced = [];
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->exactly(count(EmailFunnel::METRICS) * 2))
      ->method('replaceDay')
      ->willReturnCallback(static function (string $metric, int $day, array $totals) use (&$replaced): void {
        $replaced[$day][$metric] = $totals;
      });

    (new EmailReconciler($provider, $storage))->reconcile(10, 11);

    $this->assertSame(['smtp' => 3], $replaced[11][EmailFunnel::METRIC_ACCEPTED]);
    $this->assertSame([], $replaced[10][EmailFunnel::METRIC_ACCEPTED]);
    $this->assertSame([], $replaced[11][EmailFunnel::METRIC_DELIVERED]);
  }

}
