<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\EventSubscriber\CommerceOrderPaidSubscriber;
use Drupal\aculta_statistics\Hook\CommerceOrderDomainHooks;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_price\Price;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Pedido pago contado uma vez, com receita em centavos por moeda.
 */
#[CoversClass(CommerceOrderPaidSubscriber::class)]
#[Group('aculta_statistics')]
final class CommerceOrderPaidSubscriberTest extends TestCase {

  /**
   * O subscriber escuta o evento de pedido pago do Commerce.
   */
  public function testSubscribesToOrderPaid(): void {
    $this->assertSame(
      [OrderEvents::ORDER_PAID => 'onOrderPaid'],
      CommerceOrderPaidSubscriber::getSubscribedEvents(),
    );
  }

  /**
   * Pedido de R$ 10,50 conta um pedido e 1050 centavos em BRL.
   */
  public function testPaidOrderCountsOrderAndRevenueInCents(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $calls = [];
    $storage->method('record')->willReturnCallback(static function (string $metric, string $dimension, int $amount) use (&$calls): void {
      $calls[] = [$metric, $dimension, $amount];
    });

    $this->subscriber($storage)->onOrderPaid($this->event('10.50', 'BRL'));

    $this->assertContains(['commerce.orders.paid', 'BRL', 1], $calls);
    $this->assertContains(['commerce.revenue.gross', 'BRL', 1050], $calls);
    $this->assertContains(['commerce.orders.by_domain', 'sem_dominio|BRL', 1], $calls);
    $this->assertContains(['commerce.revenue.by_domain', 'sem_dominio|BRL', 1050], $calls);
  }

  /**
   * Cada item pago soma suas unidades pela variação de produto, sem dado de cliente.
   */
  public function testItemsAreCountedPerPurchasedVariation(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $calls = [];
    $storage->method('record')->willReturnCallback(static function (string $metric, string $dimension, int $amount) use (&$calls): void {
      $calls[] = [$metric, $dimension, $amount];
    });

    $this->subscriber($storage)->onOrderPaid($this->eventWithItems([[12, 2], [7, 1], [9, 0]]));

    $this->assertContains(['commerce.items.sold', '12', 2], $calls);
    $this->assertContains(['commerce.items.sold', '7', 1], $calls);
    $this->assertNotContains(['commerce.items.sold', '9', 0], $calls);
  }

  /**
   * Pedido com purpose gravado conta por purpose e moeda.
   */
  public function testOrderWithPurposeIsCountedByDomain(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $calls = [];
    $storage->method('record')->willReturnCallback(static function (string $metric, string $dimension, int $amount) use (&$calls): void {
      $calls[] = [$metric, $dimension, $amount];
    });
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn(9);
    $order->method('getTotalPrice')->willReturn(new Price('5.00', 'BRL'));
    $order->method('getItems')->willReturn([]);
    $order->method('getData')->with(CommerceOrderDomainHooks::DATA_KEY)->willReturn('main');

    $this->subscriber($storage)->onOrderPaid(new OrderEvent($order));

    $this->assertContains(['commerce.orders.by_domain', 'main|BRL', 1], $calls);
    $this->assertContains(['commerce.revenue.by_domain', 'main|BRL', 500], $calls);
  }

  /**
   * Pedido gratuito conta o pedido, sem receita.
   */
  public function testZeroTotalCountsOrderButNoRevenue(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $calls = [];
    $storage->method('record')->willReturnCallback(static function (string $metric, string $dimension, int $amount) use (&$calls): void {
      $calls[] = [$metric, $dimension, $amount];
    });

    $this->subscriber($storage)->onOrderPaid($this->event('0.00', 'BRL'));

    $this->assertContains(['commerce.orders.paid', 'BRL', 1], $calls);
    $this->assertNotContains('commerce.revenue.gross', array_column($calls, 0));
    $this->assertNotContains('commerce.revenue.by_domain', array_column($calls, 0));
  }

  /**
   * Pedido sem preço total não é contado.
   */
  public function testOrderWithoutTotalIsIgnored(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->subscriber($storage)->onOrderPaid($this->event(NULL, 'BRL'));
  }

  /**
   * Falha de gravação vira erro no log e não propaga para o checkout.
   */
  public function testStorageFailureDoesNotBreakCheckout(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('record')->willThrowException(new \RuntimeException('banco indisponível'));
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('error');

    $this->subscriber($storage, $logger)->onOrderPaid($this->event('10.00', 'BRL'));
    $this->addToAssertionCount(1);
  }

  /**
   * Cria o subscriber com o gravador real sobre o storage informado.
   */
  private function subscriber(AggregateStorageInterface $storage, ?LoggerInterface $logger = NULL): CommerceOrderPaidSubscriber {
    $logger ??= $this->createMock(LoggerInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);

    return new CommerceOrderPaidSubscriber(new StatisticsRecorder(new MetricCatalog(), $storage), $factory);
  }

  /**
   * Evento de pedido pago com itens (variação, quantidade) e total de 10,00 BRL.
   *
   * @param array<int, array{0: int, 1: int}> $items
   *   Pares de identificador de variação e quantidade.
   */
  private function eventWithItems(array $items): OrderEvent {
    $rows = [];
    foreach ($items as [$variationId, $quantity]) {
      $item = $this->createMock(OrderItemInterface::class);
      $item->method('getPurchasedEntityId')->willReturn((string) $variationId);
      $item->method('getQuantity')->willReturn((string) $quantity);
      $rows[] = $item;
    }
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn(7);
    $order->method('getTotalPrice')->willReturn(new Price('10.00', 'BRL'));
    $order->method('getItems')->willReturn($rows);

    return new OrderEvent($order);
  }

  /**
   * Evento de pedido pago com o total informado.
   */
  private function event(?string $number, string $currency): OrderEvent {
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn(7);
    $order->method('getTotalPrice')->willReturn($number === NULL ? NULL : new Price($number, $currency));
    $order->method('getItems')->willReturn([]);

    return new OrderEvent($order);
  }

}
