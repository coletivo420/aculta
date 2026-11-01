<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Catalog\GreenStashProductReader;
use Drupal\aculta_commerce\Store\GreenStashStoreResolver;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests cursor behavior when selective access exhausts a bounded scan.
 */
#[Group('aculta_commerce')]
final class GreenStashProductReaderPaginationTest extends UnitTestCase {

  /**
   * Continues past 500 products the operator cannot manage.
   */
  #[DataProvider('continuationResults')]
  public function testScanBudgetOnlyExposesRealContinuation(bool $hasOlderProduct): void {
    $batchIds = [];
    for ($start = 1001; $start > 501; $start -= 100) {
      $batchIds[] = range($start, $start - 99);
    }
    $productQuery = $this->createMock(QueryInterface::class);
    $productQuery->method('accessCheck')->willReturnSelf();
    $productQuery->method('condition')->willReturnSelf();
    $productQuery->method('sort')->willReturnSelf();
    $productQuery->method('range')->willReturnSelf();
    $executeResults = [...$batchIds, $hasOlderProduct ? [501] : []];
    if ($hasOlderProduct) {
      $executeResults[] = [501];
    }
    $productQuery->expects(self::exactly(count($executeResults)))
      ->method('execute')
      ->willReturnOnConsecutiveCalls(...$executeResults);

    $store = $this->createMock(StoreInterface::class);
    $store->method('id')->willReturn('1');
    $store->method('access')->with('update', self::isInstanceOf(AccountInterface::class))->willReturn(TRUE);
    $storeQuery = $this->createMock(QueryInterface::class);
    $storeQuery->method('accessCheck')->willReturnSelf();
    $storeQuery->method('range')->willReturnSelf();
    $storeQuery->method('execute')->willReturn([1]);
    $storeStorage = $this->createMock(EntityStorageInterface::class);
    $storeStorage->method('getQuery')->willReturn($storeQuery);
    $storeStorage->method('load')->with(1)->willReturn($store);

    $products = [];
    foreach (range(1001, 501) as $id) {
      $product = $this->createMock(ProductInterface::class);
      $product->method('id')->willReturn((string) $id);
      $product->method('bundle')->willReturn('produto_loja');
      $product->method('access')->willReturnCallback(
        static fn (string $operation, AccountInterface $actualAccount): bool =>
          $operation === 'view' || $id === 501,
      );
      if ($id === 501) {
        $product->method('getStores')->willReturn([$store]);
      }
      $products[(string) $id] = $product;
    }
    $productStorage = $this->createMock(EntityStorageInterface::class);
    $productStorage->method('getQuery')->willReturn($productQuery);
    $productStorage->method('loadMultiple')->willReturnCallback(
      static fn (array $ids): array => array_intersect_key($products, array_flip($ids)),
    );

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['commerce_product', $productStorage],
      ['commerce_store', $storeStorage],
    ]);
    $reader = new GreenStashProductReader($entities, new GreenStashStoreResolver($entities));
    $account = $this->createMock(AccountInterface::class);

    $firstPage = $reader->page($account);
    self::assertSame([], $firstPage['items']);
    self::assertSame($hasOlderProduct ? 502 : NULL, $firstPage['next']);

    if ($hasOlderProduct) {
      $secondPage = $reader->page($account, $firstPage['next']);
      self::assertSame([$products['501']], $secondPage['items']);
      self::assertNull($secondPage['next']);
    }
  }

  /**
   * Supplies scan-budget cases with and without remaining entities.
   *
   * @return array<string, array{bool}>
   *   Whether an older entity exists after the bounded scan.
   */
  public static function continuationResults(): array {
    return [
      'older product exists' => [TRUE],
      'no older product' => [FALSE],
    ];
  }

}
