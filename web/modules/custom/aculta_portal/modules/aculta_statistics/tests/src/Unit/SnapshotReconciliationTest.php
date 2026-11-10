<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_portal\Statistics\MetricProviderInterface;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Provider\SnapshotReconciliation;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Lock\LockBackendInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Reconciliação de retratos derivados.
 */
#[Group('aculta_statistics')]
final class SnapshotReconciliationTest extends TestCase {

  /**
   * Dry-run detecta divergência sem gravar.
   */
  public function testDryRunReportsDivergenceWithoutWriting(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('hasDay')->willReturn(TRUE);
    $storage->method('total')->willReturn(3);
    $storage->expects(self::never())->method('replaceDay');

    $report = $this->reconciliation($storage, 5)->run(100, 100);

    self::assertSame('dry_run', $report['status']);
    self::assertSame(1, $report['divergent']);
    self::assertSame(3, $report['entries'][0]['stored']);
    self::assertSame(5, $report['entries'][0]['computed']);
  }

  /**
   * Apply substitui o dia em vez de somar.
   */
  public function testApplyReplacesDay(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('hasDay')->willReturn(TRUE);
    $storage->method('total')->willReturn(3);
    $storage->expects(self::once())
      ->method('replaceDay')
      ->with('forum.topics.published_current', 100, ['' => 5]);

    $reconciliation = $this->reconciliation($storage, 5);
    $first = $reconciliation->run(100, 100, TRUE);
    self::assertSame(1, $first['updated']);
    self::assertSame('applied', $first['status']);
  }

  /**
   * Valor indisponível na fonte não vira zero.
   */
  public function testUnavailableValueIsNotWritten(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects(self::never())->method('replaceDay');

    $report = $this->reconciliation($storage, NULL)->run(100, 100, TRUE);

    self::assertSame(1, $report['unavailable']);
    self::assertSame(0, $report['updated']);
  }

  /**
   * Zero sem linha é igual a zero; não força gravação vazia.
   */
  public function testZeroWithoutRowIsMatch(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('hasDay')->willReturn(FALSE);
    $storage->expects(self::never())->method('replaceDay');

    $report = $this->reconciliation($storage, 0, 'forum.participants.new_contributors')->run(100, 100);

    self::assertSame(1, $report['matched']);
    self::assertSame(0, $report['missing']);
  }

  /**
   * Intervalo acima de 90 dias é recusado.
   */
  public function testRangeIsBounded(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->reconciliation($this->createMock(AggregateStorageInterface::class), 1)->run(0, 91);
  }

  /**
   * Cria a reconciliação com um provedor stub.
   */
  private function reconciliation(AggregateStorageInterface $storage, ?int $value, string $metric = 'forum.topics.published_current'): SnapshotReconciliation {
    $provider = new class($value, $metric) implements MetricProviderInterface {

      public function __construct(private readonly ?int $value, private readonly string $metric) {}

      public function provides(): array {
        return [$this->metric];
      }

      public function value(string $metric, int $day): ?int {
        return $this->value;
      }

    };
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return new SnapshotReconciliation(
      [$provider],
      new MetricCatalog(),
      $storage,
      $lock,
      $this->createMock(LoggerInterface::class),
    );
  }

}
