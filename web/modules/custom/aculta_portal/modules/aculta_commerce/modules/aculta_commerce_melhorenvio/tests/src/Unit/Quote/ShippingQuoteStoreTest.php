<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Quote;

use Drupal\aculta_commerce_melhorenvio\Quote\ShippingQuoteStore;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulation;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Cálculo guardado na sessão: vai do produto ao carrinho e expira.
 */
#[CoversClass(ShippingQuoteStore::class)]
#[Group('aculta_commerce_melhorenvio')]
final class ShippingQuoteStoreTest extends UnitTestCase {

  private int $now = 1_000_000;

  private function store(?Request $request): ShippingQuoteStore {
    $stack = new RequestStack();
    if ($request !== NULL) {
      $stack->push($request);
    }
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(fn() => $this->now);
    return new ShippingQuoteStore($stack, $time);
  }

  private function request(): Request {
    $request = new Request();
    $request->setSession(new Session(new MockArraySessionStorage()));
    return $request;
  }

  /**
   * O resultado gravado volta na leitura seguinte (mesma sessão).
   */
  public function testSavedQuoteIsLoaded(): void {
    $request = $this->request();
    $store = $this->store($request);
    $store->save('01310100', ShippingSimulation::unavailable(ShippingSimulation::REASON_FAILURE));
    $loaded = $store->load();
    $this->assertNotNull($loaded);
    $this->assertSame('01310100', $loaded['postal_code']);
    $this->assertSame(ShippingSimulation::STATE_UNAVAILABLE, $loaded['result']['state']);
  }

  /**
   * Cálculo além do TTL não é mais usado e sai da sessão.
   */
  public function testExpiredQuoteIsDropped(): void {
    $request = $this->request();
    $store = $this->store($request);
    $store->save('01310100', ShippingSimulation::unavailable(ShippingSimulation::REASON_FAILURE));
    $this->now += ShippingQuoteStore::TTL + 1;
    $this->assertNull($store->load());
  }

  /**
   * Sem sessão (linha de comando), nada é gravado e nada é lido.
   */
  public function testNoSessionIsNoop(): void {
    $store = $this->store(NULL);
    $store->save('01310100', ShippingSimulation::unavailable(ShippingSimulation::REASON_FAILURE));
    $this->assertNull($store->load());
  }

}
