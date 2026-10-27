<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Plugin\Commerce\PaymentGateway;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway\CheckoutPro;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Security regressions for untrusted Checkout Pro notifications.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class CheckoutProNotificationTest extends UnitTestCase {

  /**
   * Rejects malformed IDs before provider or storage calls.
   */
  #[DataProvider('invalidNotificationProvider')]
  public function testInvalidNotificationIsRejected(array $query): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->expects($this->once())
      ->method('get')
      ->with('Mercado Pago Notifications')
      ->willReturn($logger);

    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $property = new \ReflectionProperty(CheckoutPro::class, 'loggerFactory');
    $property->setValue($gateway, $logger_factory);

    $response = $gateway->onNotify(new Request($query));

    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame([], json_decode((string) $response->getContent(), TRUE));
  }

  /**
   * Does not persist a payment when the provider has not approved it.
   */
  public function testUnapprovedRemotePaymentDoesNotAcquireLockOrLoadPaymentStorage(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $client = new class {

      /**
       * Returns the simulated pending payment.
       */
      public function get(int $id): object {
        return (object) [
          'external_reference' => '42',
          'status' => 'pending',
        ];
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->once())
      ->method('criar')
      ->with('pagamentos', $parent_gateway)
      ->willReturn($client);

    $order = $this->createMock(OrderInterface::class);
    $order_storage = $this->createMock(EntityStorageInterface::class);
    $order_storage->expects($this->once())->method('load')->with('42')->willReturn($order);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->expects($this->once())
      ->method('getStorage')
      ->with('commerce_order')
      ->willReturn($order_storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->never())->method('acquire');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'entityTypeManager', $entity_manager);
    $this->setGatewayProperty($gateway, 'lock', $lock);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $response = $gateway->onNotify(new Request(['topic' => 'payment', 'id' => '123']));

    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Rejects conflicting payment and merchant order references.
   */
  public function testConflictingOrderReferencesAreNotAssociated(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $payment_client = new class {

      /**
       * Returns the simulated approved payment.
       */
      public function get(int $id): object {
        return (object) [
          'external_reference' => '42',
          'status' => 'approved',
          'order' => (object) ['id' => '900'],
        ];
      }

    };
    $merchant_order_client = new class {

      /**
       * Returns a merchant order with a conflicting order reference.
       */
      public function get(int $id): object {
        return (object) ['external_reference' => '43'];
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->exactly(2))
      ->method('criar')
      ->willReturnCallback(static fn (string $alias): object => $alias === 'pagamentos' ? $payment_client : $merchant_order_client);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->expects($this->never())->method('getStorage');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'entityTypeManager', $entity_manager);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $response = $gateway->onNotify(new Request(['topic' => 'payment', 'id' => '123']));

    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Rejects a browser return that claims approval when the API says pending.
   */
  public function testReturnDoesNotTrustBrowserApproval(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $client = new class {

      /**
       * Returns the simulated pending payment.
       */
      public function get(int $id): object {
        return (object) ['status' => 'pending'];
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->once())
      ->method('criar')
      ->with('pagamentos', $parent_gateway)
      ->willReturn($client);
    $order = $this->createMock(OrderInterface::class);
    $order->method('getData')->with('commerce_mercado_pago_checkout')->willReturn(['preference_id' => 'pref-1']);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $this->expectException(PaymentGatewayException::class);
    $gateway->onReturn($order, new Request([
      'payment_id' => '123',
      'merchant_order_id' => '900',
      'status' => 'approved',
    ]));
  }

  /**
   * Sets an injected property without constructing Drupal's plugin container.
   */
  private function setGatewayProperty(CheckoutPro $gateway, string $name, object $value): void {
    $property = new \ReflectionProperty(CheckoutPro::class, $name);
    $property->setValue($gateway, $value);
  }

  /**
   * Provides malformed or unsupported callback query values.
   *
   * @return iterable<string, array{0: array<string, mixed>}>
   *   Invalid notification query values.
   */
  public static function invalidNotificationProvider(): iterable {
    yield 'legacy IPN topic' => [['topic' => 'merchant_order', 'id' => '123']];
    yield 'missing id' => [['topic' => 'payment']];
    yield 'zero id' => [['topic' => 'payment', 'id' => '0']];
    yield 'non numeric id' => [['topic' => 'payment', 'id' => '12x']];
    yield 'array id' => [['topic' => 'payment', 'id' => ['123']]];
  }

}
