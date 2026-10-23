<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Catalog\GreenStashProductReader;
use Drupal\aculta_commerce\Store\GreenStashStoreResolver;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @group aculta_commerce
 */
final class GreenStashProductAccessTest extends UnitTestCase {

  public function testNonShopProductIsDenied(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $reader = new GreenStashProductReader($manager, new GreenStashStoreResolver($manager));
    $account = $this->createMock(AccountInterface::class);
    $product = $this->createMock(EntityInterface::class);
    $product->method('bundle')->willReturn('produto_curso');
    self::assertFalse($reader->canManage($product, $account));
  }

  public function testNoEntityAccessIsDenied(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $reader = new GreenStashProductReader($manager, new GreenStashStoreResolver($manager));
    $account = $this->createMock(AccountInterface::class);
    $product = $this->createMock(EntityInterface::class);
    $product->method('bundle')->willReturn('produto_loja');
    $product->method('access')->willReturn(FALSE);
    self::assertFalse($reader->canManage($product, $account));
  }

}
