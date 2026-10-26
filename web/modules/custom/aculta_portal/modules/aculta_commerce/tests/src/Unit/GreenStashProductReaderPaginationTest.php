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

/**
 * Tests cursor behavior when selective access exhausts a bounded scan.
 *
 * @group aculta_commerce
 */
final class GreenStashProductReaderPaginationTest extends UnitTestCase {

  /**
   * Continues past 500 products the operator cannot manage.
   */
  public function testScanBudgetExposesContinuationCursor(): void {
    $batchIds = [];
    for ($start = 1000; $start > 500; $start -= 100) {
      $batchIds[] = range($start, $start - 99);
    }
    $productQuery = $this->createMock(QueryInterface::class);
    $productQuery->method('accessCheck')->willReturnSelf();
    $productQuery->method('condition')->willReturnSelf();
    $productQuery->method('sort')->willReturnSelf();
    $productQuery->method('range')->willReturnSelf();
    $productQuery->expects(self::exactly(6))
      ->method('execute')
      ->willReturnOnConsecutiveCalls(...[...$batchIds, [500]]);

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
    foreach (range(1000, 500) as $id) {
      $product = $this->createMock(ProductInterface::class);
      $product->method('id')->willReturn((string) $id);
      $product->method('bundle')->willReturn('produto_loja');
      $product->method('access')->willReturnCallback(
        static fn (string $operation, AccountInterface $actualAccount): bool =>
          $operation === 'view' || $id === 500,
      );
      if ($id === 500) {
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
    self::assertSame(501, $firstPage['next']);

    $secondPage = $reader->page($account, $firstPage['next']);
    self::assertSame([$products['500']], $secondPage['items']);
    self::assertNull($secondPage['next']);
  }

}
