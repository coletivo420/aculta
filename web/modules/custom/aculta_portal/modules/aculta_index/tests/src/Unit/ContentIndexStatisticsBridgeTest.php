<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_index\Query\ContentIndexQuery;
use Drupal\aculta_index\Statistics\ContentIndexStatisticsBridge;
use Drupal\Core\Session\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

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
    $query = (new \ReflectionClass(ContentIndexQuery::class))->newInstanceWithoutConstructor();
    $bridge = new ContentIndexStatisticsBridge($query, NULL);
    $account = $this->createMock(AccountInterface::class);

    $result = $bridge->aggregateForQuery([], 'wiki.entries.read', 0, 0, $account);

    $this->assertFalse($result->available);
    $this->assertSame([], $result->totals);
    $this->assertNull($result->nextCursor);
    $this->assertSame(0, $result->cacheability->getCacheMaxAge());
  }

}
