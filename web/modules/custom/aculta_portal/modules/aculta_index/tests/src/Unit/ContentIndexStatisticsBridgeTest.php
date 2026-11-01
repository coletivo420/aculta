<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_index\Statistics\ContentIndexStatisticsBridge;
use Drupal\aculta_index\Query\ContentIndexQueryInterface;
use Drupal\aculta_index\Query\ContentIndexQueryResult;
use Drupal\aculta_statistics\Bridge\AuthorizedEntityMetricAggregator;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\aculta_portal\Statistics\ContentEntityMetricAggregatorInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Optional Statistics behavior for Index consumers.
 */
#[CoversClass(ContentIndexStatisticsBridge::class)]
#[Group('aculta_index')]
final class ContentIndexStatisticsBridgeTest extends TestCase {

  /**
   * Missing Statistics returns unavailable without querying content.
   */
  public function testMissingStatisticsReturnsUnavailable(): void {
    $query = $this->createMock(ContentIndexQueryInterface::class);
    $entity = $this->createMock(ContentEntityInterface::class);
    $page = new ContentIndexQueryResult([$entity], 'next-page', new CacheableMetadata());
    $query->expects($this->once())
      ->method('query')
      ->with([], $this->isInstanceOf(AccountInterface::class), NULL, 100)
      ->willReturn($page);
    $bridge = new ContentIndexStatisticsBridge($query, NULL);
    $account = $this->createMock(AccountInterface::class);

    $result = $bridge->aggregateForQuery([], 'wiki.entries.read', 0, 0, $account);

    $this->assertFalse($result->available);
    $this->assertSame([$entity], $result->entities);
    $this->assertSame([], $result->totals);
    $this->assertSame('next-page', $result->nextCursor);
    $this->assertSame($page->cacheability, $result->cacheability);
  }

  /**
   * Returns the same authorized page used to request its metric totals.
   */
  public function testReturnsAuthorizedEntitiesWithCorrelatedTotals(): void {
    $account = $this->createMock(AccountInterface::class);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('wiki_entry');
    $entity->method('id')->willReturn('42');
    $page = new ContentIndexQueryResult([$entity], 'opaque-cursor', new CacheableMetadata());
    $query = $this->createMock(ContentIndexQueryInterface::class);
    $query->expects($this->once())
      ->method('query')
      ->with(['bundle' => 'wiki_entry'], $account, NULL, 10)
      ->willReturn($page);
    $statistics = $this->createMock(ContentEntityMetricAggregatorInterface::class);
    $statistics->expects($this->once())
      ->method('aggregateForAuthorizedEntities')
      ->with(
        'wiki.entries.read',
        100,
        101,
        [['entity_type' => 'node', 'bundle' => 'wiki_entry', 'entity_id' => '42']],
        $account,
      )
      ->willReturn([42 => 6]);

    $result = (new ContentIndexStatisticsBridge($query, $statistics))->aggregateForQuery(
      ['bundle' => 'wiki_entry'],
      'wiki.entries.read',
      100,
      101,
      $account,
      NULL,
      10,
    );

    $this->assertTrue($result->available);
    $this->assertSame([$entity], $result->entities);
    $this->assertSame([42 => 6], $result->totals);
    $this->assertSame('opaque-cursor', $result->nextCursor);
  }

  /**
   * Integrates with Statistics' real permission and metric allowlist.
   */
  public function testRealStatisticsAdapterAcceptsGranularPublicMetricPermission(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn(string $permission): bool => $permission === 'view content-level statistics',
    );
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('wiki_entry');
    $entity->method('id')->willReturn('53');
    $page = new ContentIndexQueryResult([$entity], NULL, new CacheableMetadata());
    $query = $this->createMock(ContentIndexQueryInterface::class);
    $query->method('query')->willReturn($page);
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())
      ->method('dimensionTotalsForDimensions')
      ->with('wiki.entries.read', 100, 104, ['53'])
      ->willReturn(['53' => 9]);
    $statistics = new AuthorizedEntityMetricAggregator(new MetricCatalog(), $storage);

    $result = (new ContentIndexStatisticsBridge($query, $statistics))->aggregateForQuery(
      ['bundle' => 'wiki_entry'],
      'wiki.entries.read',
      100,
      104,
      $account,
    );

    $this->assertSame([$entity], $result->entities);
    $this->assertSame([53 => 9], $result->totals);
    $this->assertTrue($result->available);
  }

  /**
   * A non-allowlisted/internal metric remains denied through the bridge.
   */
  public function testRealStatisticsAdapterRejectsInternalMetricsForContentPermission(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn(string $permission): bool => $permission === 'view content-level statistics',
    );
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('file');
    $entity->method('bundle')->willReturn('file');
    $entity->method('id')->willReturn('53');
    $query = $this->createMock(ContentIndexQueryInterface::class);
    $query->method('query')->willReturn(new ContentIndexQueryResult([$entity], NULL, new CacheableMetadata()));
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('dimensionTotalsForDimensions');
    $statistics = new AuthorizedEntityMetricAggregator(new MetricCatalog(), $storage);

    $this->expectException(AccessDeniedHttpException::class);
    (new ContentIndexStatisticsBridge($query, $statistics))->aggregateForQuery(
      ['entity_type' => 'file'],
      'media.files.requests',
      100,
      104,
      $account,
    );
  }

  /**
   * Statistics rejects a page whose source bundle does not match the metric.
   */
  public function testRealStatisticsAdapterRejectsIncompatibleQueryPage(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn(string $permission): bool => $permission === 'view content-level statistics',
    );
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('revista');
    $entity->method('id')->willReturn('53');
    $query = $this->createMock(ContentIndexQueryInterface::class);
    $query->method('query')->willReturn(new ContentIndexQueryResult([$entity], NULL, new CacheableMetadata()));
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('dimensionTotalsForDimensions');
    $statistics = new AuthorizedEntityMetricAggregator(new MetricCatalog(), $storage);

    $this->expectException(\InvalidArgumentException::class);
    (new ContentIndexStatisticsBridge($query, $statistics))->aggregateForQuery(
      ['bundle' => 'revista'],
      'wiki.entries.read',
      100,
      104,
      $account,
    );
  }

}
