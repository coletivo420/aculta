<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Gravação de métricas pelo ponto único.
 */
#[CoversClass(StatisticsRecorder::class)]
#[Group('aculta_statistics')]
final class StatisticsRecorderTest extends TestCase {

  /**
   * Métrica do catálogo chega ao storage com a dimensão informada.
   */
  public function testKnownMetricReachesStorage(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())
      ->method('record')
      ->with('wiki.entries.read', '42', 1);

    (new StatisticsRecorder(new MetricCatalog(), $storage))->record('wiki.entries.read', '42');
  }

  /**
   * Métrica fora do catálogo não grava nada.
   */
  public function testUnknownMetricIsNotStored(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->expectException(\InvalidArgumentException::class);
    (new StatisticsRecorder(new MetricCatalog(), $storage))->record('wiki.page.views', '42');
  }

}
