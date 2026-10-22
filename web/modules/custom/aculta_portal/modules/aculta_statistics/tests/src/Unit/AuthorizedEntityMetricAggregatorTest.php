<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Bridge\AuthorizedEntityMetricAggregator;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Session\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Access and ID allowlist rules for Content Index Statistics requests.
 */
#[CoversClass(AuthorizedEntityMetricAggregator::class)]
#[Group('aculta_statistics')]
final class AuthorizedEntityMetricAggregatorTest extends TestCase {

  /**
   * Granular permission forwards only the selected public Wiki dimensions.
   */
  public function testContentPermissionAggregatesAllowlistedPublicIds(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())
      ->method('dimensionTotalsForDimensions')
      ->with('wiki.entries.read', 100, 102, ['42', '43'])
      ->willReturn([42 => 5]);
    $account = $this->account(['view content-level statistics']);

    $result = $this->aggregator($storage)->aggregateForAuthorizedEntities(
      'wiki.entries.read',
      100,
      102,
      [
        ['entity_type' => 'node', 'bundle' => 'wiki_entry', 'entity_id' => '42'],
        ['entity_type' => 'node', 'bundle' => 'wiki_entry', 'entity_id' => '43'],
      ],
      $account,
    );

    $this->assertSame([42 => 5], $result);
  }

  /**
   * Granular permission cannot read internal file activity metrics.
   */
  public function testContentPermissionCannotReadInternalMetrics(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('dimensionTotalsForDimensions');
    $account = $this->account(['view content-level statistics']);

    $this->expectException(AccessDeniedHttpException::class);
    $this->aggregator($storage)->aggregateForAuthorizedEntities(
      'media.files.requests',
      100,
      102,
      [['entity_type' => 'file', 'bundle' => 'file', 'entity_id' => '42']],
      $account,
    );
  }

  /**
   * A caller without either reporting permission is denied.
   */
  public function testCallerWithoutStatisticsPermissionIsDenied(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('dimensionTotalsForDimensions');

    $this->expectException(AccessDeniedHttpException::class);
    $this->aggregator($storage)->aggregateForAuthorizedEntities(
      'wiki.entries.read',
      100,
      102,
      [],
      $this->account([]),
    );
  }

  /**
   * Creates the bridge with the real metric catalog and a storage double.
   */
  private function aggregator(AggregateStorageInterface $storage): AuthorizedEntityMetricAggregator {
    return new AuthorizedEntityMetricAggregator(new MetricCatalog(), $storage);
  }

  /**
   * Creates an account double with the supplied permission set.
   *
   * @param list<string> $permissions
   *   Permission IDs granted to the account.
   */
  private function account(array $permissions): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->willReturnCallback(static fn(string $permission): bool => in_array($permission, $permissions, TRUE));
    return $account;
  }

}
