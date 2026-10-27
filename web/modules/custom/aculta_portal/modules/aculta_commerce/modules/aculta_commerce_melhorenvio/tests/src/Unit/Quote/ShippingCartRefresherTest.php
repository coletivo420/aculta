<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Quote;

use Drupal\aculta_commerce_melhorenvio\Quote\ShippingCartRefresher;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingQuoteStore;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulation;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Recálculo ao alterar o carrinho: só com CEP já guardado, com o mesmo CEP.
 */
#[CoversClass(ShippingCartRefresher::class)]
#[Group('aculta_commerce_melhorenvio')]
final class ShippingCartRefresherTest extends UnitTestCase {

  /**
   * Sem cálculo guardado, o carrinho não calcula por conta própria.
   */
  public function testNoStoredQuoteDoesNothing(): void {
    $simulator = $this->createMock(ShippingSimulator::class);
    $simulator->expects($this->never())->method('simulate');
    $store = $this->createMock(ShippingQuoteStore::class);
    $store->method('load')->willReturn(NULL);
    $store->expects($this->never())->method('save');
    (new ShippingCartRefresher($simulator, $store))->refresh();
  }

  /**
   * Com cálculo guardado, refaz para o mesmo CEP e grava o resultado novo.
   */
  public function testStoredQuoteIsRecalculatedForSamePostalCode(): void {
    $stored = ['postal_code' => '01310100', 'at' => 1, 'result' => ['state' => 'available']];
    $fresh = ShippingSimulation::unavailable(ShippingSimulation::REASON_NO_PACKAGE);
    $simulator = $this->createMock(ShippingSimulator::class);
    $simulator->expects($this->once())->method('simulate')->with('01310100', NULL)->willReturn($fresh);
    $store = $this->createMock(ShippingQuoteStore::class);
    $store->method('load')->willReturn($stored);
    $store->expects($this->once())->method('save')->with('01310100', $fresh);
    (new ShippingCartRefresher($simulator, $store))->refresh();
  }

}
