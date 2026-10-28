<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Plugin\Commerce\PaymentGateway;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_price\Price;
use Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway\CheckoutPro;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\Tests\UnitTestCase;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPResponse;
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
   * Retries before remote lookup when the payment lock is busy.
   */
  public function testBusyPaymentLockRequestsRetryBeforeProviderLookup(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->never())->method('criar');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('aculta_pagamentos_mercadopago.payment.123', 300)
      ->willReturn(FALSE);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'lock', $lock);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $response = $gateway->onNotify(new Request(['topic' => 'payment', 'id' => '123']));

    $this->assertSame(503, $response->getStatusCode());
  }

  /**
   * Terminal status cannot persist without a matching Commerce payment.
   */
  public function testRejectedRemotePaymentDoesNotPersistWithoutExistingPayment(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $client = new class {

      /**
       * Returns the simulated rejected payment.
       */
      public function get(int $id): object {
        return (object) [
          'external_reference' => '42',
          'id' => '123',
          'status' => 'rejected',
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
    $lock->expects($this->once())
      ->method('acquire')
      ->with('aculta_pagamentos_mercadopago.payment.123', 300)
      ->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.payment.123');
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
          'id' => '123',
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
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->with('aculta_pagamentos_mercadopago.payment.123', 300)->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.payment.123');
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
   * Local states that may be completed after remote approval.
   */
  public static function statesToComplete(): array {
    return [
      'legacy authorization' => ['authorization'],
      'recorded pending payment' => ['pending'],
    ];
  }

  /**
   * Completes an existing payment only after the API confirms approval.
   */
  #[DataProvider('statesToComplete')]
  public function testApprovedRemotePaymentCompletesExistingPayment(string $local_state): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $parent_gateway->method('id')->willReturn('mercado_pago');
    $client = new class {

      /**
       * Returns the simulated approved payment.
       */
      public function get(int $id): object {
        return (object) [
          'external_reference' => '42',
          'id' => '123',
          'status' => 'approved',
          'transaction_amount' => '10.00',
          'currency_id' => 'BRL',
        ];
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->once())
      ->method('criar')
      ->with('pagamentos', $parent_gateway)
      ->willReturn($client);

    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn(42);
    $order->method('getTotalPrice')->willReturn(new Price('10.00', 'BRL'));
    $order_storage = $this->createMock(EntityStorageInterface::class);
    $order_storage->expects($this->once())->method('load')->with('42')->willReturn($order);

    $payment_state = $this->createMock(StateItemInterface::class);
    $payment_state->method('getId')->willReturn($local_state);
    $payment = $this->createMock(PaymentInterface::class);
    $payment->method('getState')->willReturn($payment_state);
    $payment->expects($this->once())->method('setRemoteState')->with('approved');
    $payment->expects($this->once())->method('setState')->with('completed');
    $payment->expects($this->exactly(2))->method('save');
    $query = $this->createMock(QueryInterface::class);
    $conditions = [];
    $query->expects($this->exactly(3))
      ->method('condition')
      ->willReturnCallback(static function (string $field, mixed $value) use (&$conditions, $query): QueryInterface {
        $conditions[$field] = $value;
        return $query;
      });
    $query->method('accessCheck')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([7]);
    $payment_storage = $this->createMock(EntityStorageInterface::class);
    $payment_storage->method('getQuery')->willReturn($query);
    $payment_storage->expects($this->once())->method('load')->with(7)->willReturn($payment);
    $payment_storage->expects($this->never())->method('create');
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->willReturnCallback(
      static fn (string $type): EntityStorageInterface => $type === 'commerce_order' ? $order_storage : $payment_storage,
    );

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('aculta_pagamentos_mercadopago.payment.123', 300)
      ->willReturn(TRUE);
    $lock->expects($this->once())
      ->method('release')
      ->with('aculta_pagamentos_mercadopago.payment.123');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'entityTypeManager', $entity_manager);
    $this->setGatewayProperty($gateway, 'lock', $lock);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $response = $gateway->onNotify(new Request(['topic' => 'payment', 'id' => '123']));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame([
      'order_id' => 42,
      'payment_gateway' => 'mercado_pago',
      'remote_id' => '123',
    ], $conditions);
  }

  /**
   * Records a pending remote payment without completing the Commerce payment.
   */
  public function testPendingNotificationRecordsPendingState(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $parent_gateway->method('id')->willReturn('mercado_pago');
    $payment_client = new class {

      /**
       * Returns the simulated pending payment.
       */
      public function get(int $id): object {
        return (object) [
          'external_reference' => '42',
          'id' => '123',
          'status' => 'in_process',
          'transaction_amount' => '10.00',
          'currency_id' => 'BRL',
        ];
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->once())
      ->method('criar')
      ->with('pagamentos', $parent_gateway)
      ->willReturn($payment_client);

    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn(42);
    $order->method('getTotalPrice')->willReturn(new Price('10.00', 'BRL'));
    $order_storage = $this->createMock(EntityStorageInterface::class);
    $order_storage->expects($this->once())->method('load')->with('42')->willReturn($order);

    $payment_state = $this->createMock(StateItemInterface::class);
    $payment_state->method('getId')->willReturn('authorization');
    $payment = $this->createMock(PaymentInterface::class);
    $payment->method('getState')->willReturn($payment_state);
    $payment->method('getRemoteState')->willReturn('pending');
    $payment->expects($this->once())->method('setState')->with('pending');
    $payment->expects($this->once())->method('setRemoteState')->with('in_process');
    $payment->expects($this->once())->method('save');

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([7]);
    $payment_storage = $this->createMock(EntityStorageInterface::class);
    $payment_storage->method('getQuery')->willReturn($query);
    $payment_storage->expects($this->once())->method('load')->with(7)->willReturn($payment);
    $payment_storage->expects($this->never())->method('create');

    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->willReturnCallback(
      static fn (string $type): EntityStorageInterface => $type === 'commerce_order' ? $order_storage : $payment_storage,
    );
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->with('aculta_pagamentos_mercadopago.payment.123', 300)->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.payment.123');
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
   * Voids an existing pending payment after a signed terminal provider update.
   */
  #[DataProvider('terminalPaymentStatuses')]
  public function testTerminalNotificationVoidsExistingPayment(string $remote_status): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $parent_gateway->method('id')->willReturn('mercado_pago');
    $payment_client = new class($remote_status) {

      public function __construct(private string $status) {}

      /** Returns the simulated terminal payment from Mercado Pago. */
      public function get(int $id): object {
        return (object) [
          'external_reference' => '42',
          'id' => '123',
          'status' => $this->status,
          'transaction_amount' => '10.00',
          'currency_id' => 'BRL',
        ];
      }

    };
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->once())->method('criar')->with('pagamentos', $parent_gateway)->willReturn($payment_client);
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn(42);
    $order->method('getTotalPrice')->willReturn(new Price('10.00', 'BRL'));
    $order_storage = $this->createMock(EntityStorageInterface::class);
    $order_storage->expects($this->once())->method('load')->with('42')->willReturn($order);

    $state = $this->createMock(StateItemInterface::class);
    $state->method('getId')->willReturn('pending');
    $commerce_payment = $this->createMock(PaymentInterface::class);
    $commerce_payment->method('getState')->willReturn($state);
    $commerce_payment->expects($this->once())->method('setRemoteState')->with($remote_status);
    $commerce_payment->expects($this->once())->method('setState')->with('authorization_voided');
    $commerce_payment->expects($this->exactly(2))->method('save');
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([7]);
    $payment_storage = $this->createMock(EntityStorageInterface::class);
    $payment_storage->method('getQuery')->willReturn($query);
    $payment_storage->expects($this->once())->method('load')->with(7)->willReturn($commerce_payment);
    $payment_storage->expects($this->never())->method('create');
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->willReturnCallback(
      static fn (string $type): EntityStorageInterface => $type === 'commerce_order' ? $order_storage : $payment_storage,
    );
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->with('aculta_pagamentos_mercadopago.payment.123', 300)->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.payment.123');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $factory);
    $this->setGatewayProperty($gateway, 'entityTypeManager', $entity_manager);
    $this->setGatewayProperty($gateway, 'lock', $lock);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $response = $gateway->onNotify(new Request(['topic' => 'payment', 'id' => '123']));

    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Verifies approval and avoids duplicate return processing.
   */
  public function testApprovedReturnWithExistingPaymentIsIdempotent(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $parent_gateway->method('id')->willReturn('mercado_pago');
    $payment_client = new class {

      /**
       * Returns the simulated approved payment linked to the merchant order.
       */
      public function get(int $id): object {
        return (object) [
          'id' => '123',
          'status' => 'approved',
          'transaction_amount' => '10.00',
          'currency_id' => 'BRL',
          'external_reference' => '42',
          'order' => (object) ['id' => '900'],
        ];
      }

    };
    $merchant_order_client = new class {

      /**
       * Returns the matching merchant order.
       */
      public function get(int $id): object {
        return (object) ['external_reference' => '42'];
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->exactly(2))
      ->method('criar')
      ->willReturnCallback(static fn (string $alias): object => $alias === 'pagamentos' ? $payment_client : $merchant_order_client);

    $query = $this->createMock(QueryInterface::class);
    $conditions = [];
    $query->expects($this->exactly(3))
      ->method('condition')
      ->willReturnCallback(static function (string $field, mixed $value) use (&$conditions, $query): QueryInterface {
        $conditions[$field] = $value;
        return $query;
      });
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([7]);
    $payment_storage = $this->createMock(EntityStorageInterface::class);
    $payment_storage->method('getQuery')->willReturn($query);
    $existing_state = $this->createMock(StateItemInterface::class);
    $existing_state->method('getId')->willReturn('completed');
    $existing_payment = $this->createMock(PaymentInterface::class);
    $existing_payment->method('getState')->willReturn($existing_state);
    $existing_payment->expects($this->never())->method('save');
    $payment_storage->expects($this->once())->method('load')->with(7)->willReturn($existing_payment);
    $payment_storage->expects($this->never())->method('create');
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->expects($this->once())
      ->method('getStorage')
      ->with('commerce_payment')
      ->willReturn($payment_storage);
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn(42);
    $order->method('getData')
      ->with('commerce_mercado_pago_checkout')
      ->willReturn(['preference_id' => 'pref-1']);
    $order->method('getTotalPrice')->willReturn(new Price('10.00', 'BRL'));
    $order->expects($this->never())->method('save');

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('aculta_pagamentos_mercadopago.payment.123', 300)
      ->willReturn(TRUE);
    $lock->expects($this->once())
      ->method('release')
      ->with('aculta_pagamentos_mercadopago.payment.123');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'entityTypeManager', $entity_manager);
    $this->setGatewayProperty($gateway, 'lock', $lock);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $result = $gateway->onReturn($order, new Request([
      'payment_id' => '123',
      'merchant_order_id' => '900',
      'status' => 'approved',
    ]));

    $this->assertNull($result);
    $this->assertSame([
      'order_id' => 42,
      'payment_gateway' => 'mercado_pago',
      'remote_id' => '123',
    ], $conditions);
  }

  /**
   * Refreshes remote state for an existing payment within pending workflow.
   */
  public function testPendingReturnUpdatesRemoteStateWithoutRegressingPayment(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $parent_gateway->method('id')->willReturn('mercado_pago');
    $payment_client = new class {

      /**
       * Returns the latest verified pending state.
       */
      public function get(int $id): object {
        return (object) [
          'id' => '123',
          'status' => 'authorized',
          'transaction_amount' => '10.00',
          'currency_id' => 'BRL',
          'external_reference' => '42',
          'order' => (object) ['id' => '900'],
        ];
      }

    };
    $merchant_order_client = new class {

      /**
       * Returns the matching merchant order.
       */
      public function get(int $id): object {
        return (object) ['external_reference' => '42'];
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->method('criar')->willReturnCallback(
      static fn (string $alias): object => $alias === 'pagamentos' ? $payment_client : $merchant_order_client,
    );

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([7]);
    $payment_storage = $this->createMock(EntityStorageInterface::class);
    $payment_storage->method('getQuery')->willReturn($query);
    $payment_state = $this->createMock(StateItemInterface::class);
    $payment_state->method('getId')->willReturn('pending');
    $payment = $this->createMock(PaymentInterface::class);
    $payment->method('getState')->willReturn($payment_state);
    $payment->method('getRemoteState')->willReturn('in_process');
    $payment->expects($this->once())->method('setRemoteState')->with('authorized');
    $payment->expects($this->never())->method('setState');
    $payment->expects($this->once())->method('save');
    $payment_storage->expects($this->once())->method('load')->with(7)->willReturn($payment);

    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->expects($this->once())->method('getStorage')->with('commerce_payment')->willReturn($payment_storage);
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn(42);
    $order->method('getData')->with('commerce_mercado_pago_checkout')->willReturn(['preference_id' => 'pref-1']);
    $order->method('getTotalPrice')->willReturn(new Price('10.00', 'BRL'));

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->with('aculta_pagamentos_mercadopago.payment.123', 300)->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.payment.123');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'entityTypeManager', $entity_manager);
    $this->setGatewayProperty($gateway, 'lock', $lock);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $gateway->onReturn($order, new Request([
      'payment_id' => '123',
      'merchant_order_id' => '900',
      'status' => 'pending',
    ]));
  }

  /**
   * A busy return lock prevents remote lookup until reconciliation completes.
   */
  public function testBusyReturnLockDoesNotQueryProvider(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('aculta_pagamentos_mercadopago.payment.123', 300)
      ->willReturn(FALSE);
    $lock->expects($this->never())->method('release');
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->never())->method('criar');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $this->setGatewayProperty($gateway, 'lock', $lock);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);
    $order = $this->createMock(OrderInterface::class);
    $order->method('getData')
      ->with('commerce_mercado_pago_checkout')
      ->willReturn(['preference_id' => 'pref-1']);

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Payment verification is already being processed.');
    $gateway->onReturn($order, new Request([
      'payment_id' => '123',
      'merchant_order_id' => '900',
      'status' => 'approved',
    ]));
  }

  /**
   * A notification never reconciles a different provider payment ID.
   */
  public function testNotificationRejectsProviderResponseWithDifferentPaymentId(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $payment_client = new class {

      /**
       * Returns an unexpected payment record for the requested ID.
       */
      public function get(int $id): object {
        return (object) ['id' => '456', 'status' => 'approved'];
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->once())
      ->method('criar')
      ->with('pagamentos', $parent_gateway)
      ->willReturn($payment_client);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('aculta_pagamentos_mercadopago.payment.123', 300)
      ->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.payment.123');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('Payment lookup for @id returned a conflicting ID.', ['@id' => '123']);
    $logger_factory->method('get')->willReturn($logger);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->expects($this->never())->method('getStorage');
    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'lock', $lock);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);
    $this->setGatewayProperty($gateway, 'entityTypeManager', $entity_manager);

    $response = $gateway->onNotify(new Request(['topic' => 'payment', 'id' => '123']));

    self::assertSame(503, $response->getStatusCode());
  }

  /**
   * A return rejects a different provider payment ID.
   */
  public function testReturnRejectsProviderResponseWithDifferentPaymentId(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $payment_client = new class {

      /**
       * Returns a different payment ID than requested.
       */
      public function get(int $id): object {
        return (object) ['id' => '456', 'status' => 'approved'];
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->once())
      ->method('criar')
      ->with('pagamentos', $parent_gateway)
      ->willReturn($payment_client);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Payment lookup for @id returned a conflicting ID.', ['@id' => '123']);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);
    $this->setSuccessfulPaymentLock($gateway);
    $order = $this->createMock(OrderInterface::class);
    $order->method('getData')
      ->with('commerce_mercado_pago_checkout')
      ->willReturn(['preference_id' => 'pref-1']);

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Could not verify payment with Mercado Pago.');
    $gateway->onReturn($order, new Request([
      'payment_id' => '123',
      'merchant_order_id' => '900',
      'status' => 'approved',
    ]));
  }

  /**
   * Requests webhook retry when the provider API lookup fails.
   */
  public function testNotificationReturnsRetryableStatusOnProviderFailure(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $client = new class {

      /**
       * Simulates a transient provider response.
       */
      public function get(int $id): object {
        throw new MPApiException('provider failure', new MPResponse(503, []));
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->once())
      ->method('criar')
      ->with('pagamentos', $parent_gateway)
      ->willReturn($client);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->expects($this->never())->method('getStorage');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('aculta_pagamentos_mercadopago.payment.123', 300)
      ->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.payment.123');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'entityTypeManager', $entity_manager);
    $this->setGatewayProperty($gateway, 'lock', $lock);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $response = $gateway->onNotify(new Request(['topic' => 'payment', 'id' => '123']));

    $this->assertSame(503, $response->getStatusCode());
  }

  /**
   * Returns a retryable response for transport exceptions without logging them.
   */
  public function testNotificationReturnsRetryableStatusOnTransportFailure(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $client = new class {

      /**
       * Simulates a timeout with private transport details.
       */
      public function get(int $id): object {
        throw new \RuntimeException('private provider response');
      }

    };
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->once())
      ->method('criar')
      ->with('pagamentos', $parent_gateway)
      ->willReturn($client);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Mercado Pago webhook lookup failed for payment @id.', ['@id' => '123']);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->with('aculta_pagamentos_mercadopago.payment.123', 300)->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.payment.123');
    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $factory);
    $this->setGatewayProperty($gateway, 'lock', $lock);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $response = $gateway->onNotify(new Request(['topic' => 'payment', 'id' => '123']));

    $this->assertSame(503, $response->getStatusCode());
  }

  /**
   * Shows a safe payment exception when API verification fails on return.
   */
  public function testReturnFailsClosedOnProviderApiFailure(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $client = new class {

      /**
       * Simulates a transient provider response.
       */
      public function get(int $id): object {
        throw new MPApiException('provider failure', new MPResponse(503, []));
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->once())
      ->method('criar')
      ->with('pagamentos', $parent_gateway)
      ->willReturn($client);
    $order = $this->createMock(OrderInterface::class);
    $order->method('getData')
      ->with('commerce_mercado_pago_checkout')
      ->willReturn(['preference_id' => 'pref-1']);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setSuccessfulPaymentLock($gateway);
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
   * Hides transport exception details when the return cannot be checked.
   */
  public function testReturnFailsClosedOnTransportFailure(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $client = new class {

      /**
       * Simulates an API timeout with private response details.
       */
      public function get(int $id): object {
        throw new \RuntimeException('private provider token');
      }

    };
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->once())
      ->method('criar')
      ->with('pagamentos', $parent_gateway)
      ->willReturn($client);
    $order = $this->createMock(OrderInterface::class);
    $order->method('getData')->with('commerce_mercado_pago_checkout')->willReturn(['preference_id' => 'pref-1']);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Mercado Pago API error verifying payment @id.', ['@id' => '123']);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setSuccessfulPaymentLock($gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $factory);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Could not verify payment with Mercado Pago.');
    $gateway->onReturn($order, new Request([
      'payment_id' => '123',
      'merchant_order_id' => '900',
      'status' => 'approved',
    ]));
  }

  /**
   * Normalizes a transport failure while verifying the returned merchant order.
   */
  public function testReturnHidesMerchantOrderTransportFailure(): void {
    $gateway = (new \ReflectionClass(CheckoutPro::class))->newInstanceWithoutConstructor();
    $parent_gateway = $this->createMock(PaymentGatewayInterface::class);
    $payment_client = new class {

      /**
       * Returns an approved payment tied to the expected Commerce order.
       */
      public function get(int $id): object {
        return (object) [
          'id' => '123',
          'status' => 'approved',
          'transaction_amount' => '10.00',
          'currency_id' => 'BRL',
          'external_reference' => '42',
        ];
      }

    };
    $merchant_order_client = new class {

      /**
       * Simulates a network error without exposing provider details.
       */
      public function get(int $id): object {
        throw new \RuntimeException('private transport response');
      }

    };
    $client_factory = $this->createMock(MercadoPagoClientFactory::class);
    $client_factory->expects($this->exactly(2))
      ->method('criar')
      ->willReturnCallback(static fn (string $alias): object => $alias === 'pagamentos' ? $payment_client : $merchant_order_client);
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn(42);
    $order->method('getData')->with('commerce_mercado_pago_checkout')->willReturn(['preference_id' => 'pref-1']);
    $order->method('getTotalPrice')->willReturn(new Price('10.00', 'BRL'));
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Mercado Pago API error fetching returned merchant order.');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $this->setGatewayProperty($gateway, 'parentEntity', $parent_gateway);
    $this->setSuccessfulPaymentLock($gateway);
    $this->setGatewayProperty($gateway, 'clientFactory', $client_factory);
    $this->setGatewayProperty($gateway, 'loggerFactory', $logger_factory);

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Could not verify payment order.');
    $gateway->onReturn($order, new Request([
      'payment_id' => '123',
      'merchant_order_id' => '900',
      'status' => 'approved',
    ]));
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
        return (object) ['id' => '123', 'status' => 'pending'];
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
    $this->setSuccessfulPaymentLock($gateway);
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
   * Configures the payment lock for a valid return flow.
   */
  private function setSuccessfulPaymentLock(CheckoutPro $gateway): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('aculta_pagamentos_mercadopago.payment.123', 300)
      ->willReturn(TRUE);
    $lock->expects($this->once())
      ->method('release')
      ->with('aculta_pagamentos_mercadopago.payment.123');
    $this->setGatewayProperty($gateway, 'lock', $lock);
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

  /**
   * Provides provider statuses that terminate a pending payment.
   *
   * @return array<string, array{0: string}>
   *   Terminal provider statuses.
   */
  public static function terminalPaymentStatuses(): array {
    return [
      'cancelled' => ['cancelled'],
      'rejected' => ['rejected'],
    ];
  }

}
