<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Catalog\GreenStashProductReader;
use Drupal\aculta_commerce\Store\GreenStashStoreResolver;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Green Stash product and store access boundaries.
 */
#[Group('aculta_commerce')]
final class GreenStashProductAccessTest extends UnitTestCase {

  /**
   * Denies products outside the commercial shop bundle.
   */
  public function testNonShopProductIsDenied(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $reader = new GreenStashProductReader($manager, new GreenStashStoreResolver($manager));
    $account = $this->createMock(AccountInterface::class);
    $product = $this->createMock(EntityInterface::class);
    $product->method('bundle')->willReturn('produto_curso');
    self::assertFalse($reader->canManage($product, $account));
  }

  /**
   * Denies a product when native Entity Access rejects management.
   */
  public function testNoEntityAccessIsDenied(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $reader = new GreenStashProductReader($manager, new GreenStashStoreResolver($manager));
    $account = $this->createMock(AccountInterface::class);
    $product = $this->createMock(EntityInterface::class);
    $product->method('bundle')->willReturn('produto_loja');
    $product->method('access')->willReturn(FALSE);
    self::assertFalse($reader->canManage($product, $account));
  }

  /**
   * Allows a shop product when entity and store access both allow editing.
   */
  public function testManagedShopProductRequiresEditableStore(): void {
    $store = $this->createMock(StoreInterface::class);
    $store->method('id')->willReturn('1');
    $store->expects(self::once())
      ->method('access')
      ->with('update', self::isInstanceOf(AccountInterface::class))
      ->willReturn(TRUE);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('range')->with(0, 2)->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storeStorage = $this->createMock(EntityStorageInterface::class);
    $storeStorage->method('getQuery')->willReturn($query);
    $storeStorage->method('load')->with('1')->willReturn($store);

    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('commerce_store')->willReturn($storeStorage);

    $reader = new GreenStashProductReader($manager, new GreenStashStoreResolver($manager));
    $account = $this->createMock(AccountInterface::class);
    $product = $this->createMock(ProductInterface::class);
    $product->method('bundle')->willReturn('produto_loja');
    $product->expects(self::exactly(2))
      ->method('access')
      ->willReturnCallback(static function (string $operation, AccountInterface $actualAccount) use ($account): bool {
        self::assertSame($account, $actualAccount);
        self::assertContains($operation, ['view', 'update']);
        return TRUE;
      });
    $product->method('getStores')->willReturn([$store]);

    self::assertTrue($reader->canManage($product, $account));
  }

  /**
   * Denies a product associated with a different store.
   */
  public function testProductInDifferentStoreIsDenied(): void {
    $managedStore = $this->createMock(StoreInterface::class);
    $managedStore->method('id')->willReturn('1');
    $managedStore->method('access')->with('update', self::isInstanceOf(AccountInterface::class))->willReturn(TRUE);

    $productStore = $this->createMock(StoreInterface::class);
    $productStore->method('id')->willReturn('2');

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('range')->with(0, 2)->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storeStorage = $this->createMock(EntityStorageInterface::class);
    $storeStorage->method('getQuery')->willReturn($query);
    $storeStorage->method('load')->with('1')->willReturn($managedStore);

    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('commerce_store')->willReturn($storeStorage);

    $reader = new GreenStashProductReader($manager, new GreenStashStoreResolver($manager));
    $account = $this->createMock(AccountInterface::class);
    $product = $this->createMock(ProductInterface::class);
    $product->method('bundle')->willReturn('produto_loja');
    $product->method('access')->willReturn(TRUE);
    $product->method('getStores')->willReturn([$productStore]);

    self::assertFalse($reader->canManage($product, $account));
  }

  /**
   * Denies management when the operator cannot update the sole store.
   */
  public function testStoreWithoutUpdateAccessIsDenied(): void {
    $store = $this->createMock(StoreInterface::class);
    $store->method('id')->willReturn('1');
    $store->method('access')->with('update', self::isInstanceOf(AccountInterface::class))->willReturn(FALSE);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('range')->with(0, 2)->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storeStorage = $this->createMock(EntityStorageInterface::class);
    $storeStorage->method('getQuery')->willReturn($query);
    $storeStorage->method('load')->with('1')->willReturn($store);

    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('commerce_store')->willReturn($storeStorage);

    $reader = new GreenStashProductReader($manager, new GreenStashStoreResolver($manager));
    $account = $this->createMock(AccountInterface::class);
    $product = $this->createMock(ProductInterface::class);
    $product->method('bundle')->willReturn('produto_loja');
    $product->method('access')->willReturn(TRUE);
    $product->method('getStores')->willReturn([$store]);

    self::assertFalse($reader->canManage($product, $account));
  }

  /**
   * Denies management when more than one store makes ownership ambiguous.
   */
  public function testAmbiguousStoreConfigurationIsDenied(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('range')->with(0, 2)->willReturnSelf();
    $query->method('execute')->willReturn([1, 2]);

    $storeStorage = $this->createMock(EntityStorageInterface::class);
    $storeStorage->method('getQuery')->willReturn($query);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('commerce_store')->willReturn($storeStorage);

    $reader = new GreenStashProductReader($manager, new GreenStashStoreResolver($manager));
    $account = $this->createMock(AccountInterface::class);
    $product = $this->createMock(ProductInterface::class);
    $product->method('bundle')->willReturn('produto_loja');
    $product->method('access')->willReturn(TRUE);
    $product->method('getStores')->willReturn([]);

    self::assertFalse($reader->canManage($product, $account));
  }

}
