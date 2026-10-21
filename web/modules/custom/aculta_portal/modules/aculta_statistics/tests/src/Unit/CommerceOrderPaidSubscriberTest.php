<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\EventSubscriber\CommerceOrderPaidSubscriber;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\commerce_order\Entity\OrderInterface;
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
    $storage->expects($this->exactly(2))->method('record')->willReturnCallback(
      static function (string $metric, string $dimension, int $amount): void {
        static $calls = [];
        $calls[] = [$metric, $dimension, $amount];
        if (count($calls) === 2) {
          self::assertSame([['commerce.orders.paid', 'BRL', 1], ['commerce.revenue.gross', 'BRL', 1050]], $calls);
        }
      },
    );

    $this->subscriber($storage)->onOrderPaid($this->event('10.50', 'BRL'));
  }

  /**
   * Pedido gratuito conta o pedido, sem receita.
   */
  public function testZeroTotalCountsOrderButNoRevenue(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())->method('record')->with('commerce.orders.paid', 'BRL', 1);

    $this->subscriber($storage)->onOrderPaid($this->event('0.00', 'BRL'));
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
   * Evento de pedido pago com o total informado.
   */
  private function event(?string $number, string $currency): OrderEvent {
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn(7);
    $order->method('getTotalPrice')->willReturn($number === NULL ? NULL : new Price($number, $currency));

    return new OrderEvent($order);
  }

}
