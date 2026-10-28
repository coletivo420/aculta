<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Plugin\Commerce\PaymentGateway;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_price\Price;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway\CheckoutPro;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\Tests\UnitTestCase;
use MercadoPago\Client\Common\RequestOptions;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers safe, serialized and idempotent Checkout Pro refunds.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class CheckoutProRefundTest extends UnitTestCase {

  /**
   * Sends a partial refund with environment-scoped client and stable key.
   */
  public function testPartialRefundUsesScopedClientAndStableIdempotencyKey(): void {
    $gateway = $this->gateway();
    $payment = $this->payment();
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->exactly(2))->method('load')->with(12)->willReturn($payment);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->expects($this->exactly(2))->method('getStorage')->with('commerce_payment')->willReturn($storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->exactly(2))->method('acquire')->with('aculta_pagamentos_mercadopago.refund.999', 120)->willReturn(TRUE);
    $lock->expects($this->exactly(2))->method('release')->with('aculta_pagamentos_mercadopago.refund.999');
    $provider = new class() {

      /** @var \MercadoPago\Client\Common\RequestOptions|null */
      public array $options = [];

      /**
       * Simulates an approved partial refund.
       */
      public function refund(int $payment_id, float $amount, ?RequestOptions $options = NULL): array {
        $this->options[] = $options;
        return ['id' => 501, 'status' => 'approved', 'payment_id' => $payment_id, 'amount' => $amount];
      }

    };
    $gateway_entity = $this->createMock(PaymentGatewayInterface::class);
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->exactly(2))->method('criar')->with('reembolsos', $gateway_entity)->willReturn($provider);
    $this->inject($gateway, 'parentEntity', $gateway_entity);
    $this->inject($gateway, 'entityTypeManager', $entity_manager);
    $this->inject($gateway, 'lock', $lock);
    $this->inject($gateway, 'clientFactory', $factory);
    $this->injectLogger($gateway);

    $gateway->refundPayment($payment, new Price('25.00', 'BRL'));
    $gateway->refundPayment($payment, new Price('25.00', 'BRL'));

    $keys = array_map(static fn (RequestOptions $options): ?string => $options->getCustomHeaders()['x-idempotency-key'] ?? NULL, $provider->options);
    $this->assertCount(2, $keys);
    $this->assertIsString($keys[0]);
    $this->assertSame($keys[0], $keys[1]);
    $this->assertMatchesRegularExpression('/^aculta-refund-[a-f0-9]{64}$/', $keys[0]);
    $this->assertSame(0, $provider->options[0]->getMaxRetries());
  }

  /**
   * Does not persist a refund until Mercado Pago reports it approved.
   */
  public function testPendingProviderRefundDoesNotChangeCommercePayment(): void {
    $gateway = $this->gateway();
    $payment = $this->payment(FALSE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('load')->with(12)->willReturn($payment);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->with('commerce_payment')->willReturn($storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release');
    $provider = new class() {

      /**
       * Simulates an asynchronously processing refund.
       */
      public function refund(int $payment_id, float $amount, ?RequestOptions $options = NULL): object {
        return (object) ['status' => 'in_process', 'payment_id' => $payment_id];
      }

    };
    $gateway_entity = $this->createMock(PaymentGatewayInterface::class);
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->once())->method('criar')->with('reembolsos', $gateway_entity)->willReturn($provider);
    $this->inject($gateway, 'parentEntity', $gateway_entity);
    $this->inject($gateway, 'entityTypeManager', $entity_manager);
    $this->inject($gateway, 'lock', $lock);
    $this->inject($gateway, 'clientFactory', $factory);
    $this->injectLogger($gateway);

    $this->expectException(PaymentGatewayException::class);
    $gateway->refundPayment($payment, new Price('25.00', 'BRL'));
  }

  /**
   * Rejects incomplete or unrelated refund resources without mutating Commerce.
   */
  #[DataProvider('invalidApprovedRefundResponses')]
  public function testApprovedRefundMustIdentifyRefundAndOriginalPayment(array $response): void {
    $gateway = $this->gateway();
    $payment = $this->payment(FALSE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('load')->with(12)->willReturn($payment);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->with('commerce_payment')->willReturn($storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release');
    $provider = new class($response) {

      /**
       * Creates the response fixture used by this provider stub.
       */
      public function __construct(private array $response) {}

      /**
       * Returns the configured provider response for the refund request.
       */
      public function refund(int $payment_id, float $amount, ?RequestOptions $options = NULL): object {
        return (object) $this->response;
      }

    };
    $gateway_entity = $this->createMock(PaymentGatewayInterface::class);
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->once())->method('criar')->with('reembolsos', $gateway_entity)->willReturn($provider);
    $this->inject($gateway, 'parentEntity', $gateway_entity);
    $this->inject($gateway, 'entityTypeManager', $entity_manager);
    $this->inject($gateway, 'lock', $lock);
    $this->inject($gateway, 'clientFactory', $factory);
    $this->injectLogger($gateway);

    $this->expectException(PaymentGatewayException::class);
    $gateway->refundPayment($payment, new Price('25.00', 'BRL'));
  }

  /**
   * Supplies malformed approved refund responses.
   *
   * @return array<string, array{0: array<string, mixed>}>
   *   Refund response fixtures.
   */
  public static function invalidApprovedRefundResponses(): array {
    return [
      'missing refund ID' => [['status' => 'approved', 'payment_id' => 999]],
      'missing payment ID' => [['id' => 501, 'status' => 'approved']],
      'different payment ID' => [['id' => 501, 'status' => 'approved', 'payment_id' => 1000]],
      'invalid refund ID' => [['id' => 0, 'status' => 'approved', 'payment_id' => 999]],
    ];
  }

  /**
   * Uses the full-refund endpoint and records the completed balance.
   */
  public function testFullRefundUsesTotalEndpoint(): void {
    $gateway = $this->gateway();
    $payment = $this->payment(TRUE, 1, 'refunded', '100');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('load')->with(12)->willReturn($payment);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->with('commerce_payment')->willReturn($storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release');
    $provider = new class() {

      /**
       * Simulates an approved total refund.
       */
      public function refundTotal(int $payment_id, ?RequestOptions $options = NULL): object {
        return (object) ['id' => 502, 'status' => 'approved', 'payment_id' => $payment_id, 'amount' => 100.0];
      }

    };
    $gateway_entity = $this->createMock(PaymentGatewayInterface::class);
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->once())->method('criar')->with('reembolsos', $gateway_entity)->willReturn($provider);
    $this->inject($gateway, 'parentEntity', $gateway_entity);
    $this->inject($gateway, 'entityTypeManager', $entity_manager);
    $this->inject($gateway, 'lock', $lock);
    $this->inject($gateway, 'clientFactory', $factory);
    $this->injectLogger($gateway);

    $gateway->refundPayment($payment);
  }

  /**
   * Accepts a partial refund at three-digit BHD precision.
   */
  public function testPartialRefundUsesThreeDigitCurrencyPrecision(): void {
    $gateway = $this->gateway();
    $payment = $this->payment(TRUE, 1, 'partially_refunded', '1.234', 'BHD', '5.678');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('load')->with(12)->willReturn($payment);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->with('commerce_payment')->willReturn($storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release');
    $provider = new class() {

      /** @var float|null */
      public ?float $amount = NULL;

      /**
       * Simulates an approved BHD partial refund.
       */
      public function refund(int $payment_id, float $amount, ?RequestOptions $options = NULL): object {
        $this->amount = $amount;
        return (object) ['id' => 503, 'status' => 'approved', 'payment_id' => $payment_id, 'amount' => $amount];
      }

    };
    $gateway_entity = $this->createMock(PaymentGatewayInterface::class);
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->once())->method('criar')->with('reembolsos', $gateway_entity)->willReturn($provider);
    $this->inject($gateway, 'parentEntity', $gateway_entity);
    $this->inject($gateway, 'entityTypeManager', $entity_manager);
    $this->inject($gateway, 'lock', $lock);
    $this->inject($gateway, 'clientFactory', $factory);
    $this->injectLogger($gateway);

    $gateway->refundPayment($payment, new Price('1.234', 'BHD'));

    $this->assertSame(1.234, $provider->amount);
  }

  /**
   * Hides private provider exception details from logs and Commerce errors.
   */
  public function testProviderFailureIsLoggedWithoutResponseDetails(): void {
    $gateway = $this->gateway();
    $payment = $this->payment(FALSE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('load')->with(12)->willReturn($payment);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->with('commerce_payment')->willReturn($storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release');
    $provider = new class() {

      /**
       * Simulates a provider error containing private response data.
       */
      public function refund(int $payment_id, float $amount, ?RequestOptions $options = NULL): object {
        throw new \RuntimeException('private response token');
      }

    };
    $gateway_entity = $this->createMock(PaymentGatewayInterface::class);
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->once())->method('criar')->with('reembolsos', $gateway_entity)->willReturn($provider);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Mercado Pago refund request failed for payment @remote_id.', ['@remote_id' => '999']);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $this->inject($gateway, 'parentEntity', $gateway_entity);
    $this->inject($gateway, 'entityTypeManager', $entity_manager);
    $this->inject($gateway, 'lock', $lock);
    $this->inject($gateway, 'clientFactory', $factory);
    $this->inject($gateway, 'loggerFactory', $logger_factory);

    $this->expectExceptionMessage('An error occurred while refunding the payment.');
    $gateway->refundPayment($payment, new Price('25.00', 'BRL'));
  }

  /**
   * Does not call the provider when another request owns the refund lock.
   */
  public function testConcurrentRefundIsRejectedBeforeProviderCall(): void {
    $gateway = $this->gateway();
    $payment = $this->payment(FALSE);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->willReturn(FALSE);
    $lock->expects($this->never())->method('release');
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->never())->method('criar');
    $this->inject($gateway, 'lock', $lock);
    $this->inject($gateway, 'clientFactory', $factory);
    $this->injectLogger($gateway);

    $this->expectException(PaymentGatewayException::class);
    $gateway->refundPayment($payment, new Price('25.00', 'BRL'));
  }

  /**
   * Creates a gateway whose policy permits the focused refund fixture.
   */
  private function gateway(): CheckoutPro {
    $class = new class() extends CheckoutPro {

      /**
       * Avoids initializing global SDK state in this isolated test gateway.
       */
      public function __construct() {}

      /**
       * Permits refunds in this isolated gateway contract test.
       */
      public function canRefundPayment(PaymentInterface $payment) {
        return TRUE;
      }

    };
    return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
  }

  /**
   * Creates a persisted completed payment fixture with a remote ID.
   */
  private function payment(bool $expect_mutation = TRUE, int $mutation_count = 2, string $expected_state = 'partially_refunded', string $expected_refunded_amount = '25', string $currency_code = 'BRL', string $total_amount = '100.00'): PaymentInterface {
    $state = $this->createMock(StateItemInterface::class);
    $state->method('getId')->willReturn('completed');
    $payment = $this->createMock(PaymentInterface::class);
    $payment->method('id')->willReturn(12);
    $payment->method('getRemoteId')->willReturn('999');
    $payment->method('getState')->willReturn($state);
    $payment->method('getAmount')->willReturn(new Price($total_amount, $currency_code));
    $payment->method('getRefundedAmount')->willReturn(new Price('0', $currency_code));
    $payment->method('getBalance')->willReturn(new Price($total_amount, $currency_code));
    $payment->method('getOrder')->willReturn($this->createMock(OrderInterface::class));
    if ($expect_mutation) {
      $payment->expects($this->exactly($mutation_count))->method('setState')->with($expected_state);
      $payment->expects($this->exactly($mutation_count))->method('setRefundedAmount')->with($this->callback(
        static fn (Price $price): bool => $price->getNumber() === $expected_refunded_amount && $price->getCurrencyCode() === $currency_code,
      ));
      $payment->expects($this->exactly($mutation_count))->method('setRemoteState')->with('approved');
      $payment->expects($this->exactly($mutation_count))->method('save');
    }
    else {
      $payment->expects($this->never())->method('setState');
      $payment->expects($this->never())->method('save');
    }
    return $payment;
  }

  /**
   * Sets a Drupal injected property without building a container.
   */
  private function inject(CheckoutPro $gateway, string $name, object $value): void {
    (new \ReflectionProperty(CheckoutPro::class, $name))->setValue($gateway, $value);
  }

  /**
   * Injects the logger factory used by the gateway operation.
   */
  private function injectLogger(CheckoutPro $gateway): void {
    $logger = $this->createMock(LoggerInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    $this->inject($gateway, 'loggerFactory', $factory);
  }

}
