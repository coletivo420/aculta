<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_portal\Statistics\MetricProviderInterface;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Provider\MetricProviderCollector;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Coleta de métricas calculadas na fonte.
 */
#[Group('aculta_statistics')]
final class MetricProviderCollectorTest extends TestCase {

  /**
   * Substitui o dia em vez de somar e ignora métricas fora do catálogo.
   */
  public function testCollectsDerivedMetricsWithReplaceDay(): void {
    $provider = new class implements MetricProviderInterface {

      public function provides(): array {
        return [
          'forum.topics.published_current',
          'forum.topics.created',
          'fora.do.catalogo',
        ];
      }

      public function value(string $metric, int $day): ?int {
        return $metric === 'forum.topics.published_current' ? 12 : 99;
      }

    };

    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('currentDay')->willReturn(100);
    $storage->expects(self::once())
      ->method('replaceDay')
      ->with('forum.topics.published_current', 100, ['' => 12]);

    $collector = new MetricProviderCollector(
      [$provider],
      new MetricCatalog(),
      $storage,
      $this->createMock(LoggerInterface::class),
    );

    self::assertSame(['forum.topics.published_current' => 12], $collector->collect());
  }

  /**
   * Valor nulo significa indisponível e não grava zero.
   */
  public function testNullValueIsNotRecorded(): void {
    $provider = new class implements MetricProviderInterface {

      public function provides(): array {
        return ['forum.topics.published_current'];
      }

      public function value(string $metric, int $day): ?int {
        return NULL;
      }

    };

    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('currentDay')->willReturn(100);
    $storage->expects(self::never())->method('replaceDay');

    $collector = new MetricProviderCollector(
      [$provider],
      new MetricCatalog(),
      $storage,
      $this->createMock(LoggerInterface::class),
    );

    self::assertSame([], $collector->collect());
  }

}
