<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoChargebackNotificationService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_portal\Contract\PaymentOperationsInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests signed chargeback lookup and exact payment association.
 */
#[CoversClass(MercadoPagoChargebackNotificationService::class)]
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoChargebackNotificationServiceTest extends UnitTestCase {

  /**
   * Builds the service with provider and Commerce entities isolated.
   *
   * @param list<string> $paymentIds
   *   IDs returned by the exact remote-payment lookup.
   * @param object $chargeback
   *   Simulated provider case.
   * @param string|null $paymentStatus
   *   Refreshed status; NULL disables successful payment refresh.
   *
   * @return array{MercadoPagoChargebackNotificationService, PaymentInterface, PaymentOperationsInterface}
   *   Service, local payment and reconciliation contract.
   */
  private function buildService(array $paymentIds, object $chargeback, ?string $paymentStatus = 'charged_back'): array {
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('id')->willReturn('mercado_pago');
    $gateway->method('status')->willReturn(TRUE);

    $gatewayStorage = $this->createMock(EntityStorageInterface::class);
    $gatewayStorage->method('load')->with('mercado_pago')->willReturn($gateway);

    $payment = $this->createMock(PaymentInterface::class);
    $payment->method('getRemoteId')->willReturn('456789');
    $payment->method('getPaymentGatewayId')->willReturn('mercado_pago');
    $paymentStorage = $this->createMock(EntityStorageInterface::class);
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($paymentIds);
    $paymentStorage->method('getQuery')->willReturn($query);
    $paymentStorage->method('load')->with('payment-1')->willReturn($payment);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn (string $entityType): EntityStorageInterface => match ($entityType) {
      'commerce_payment_gateway' => $gatewayStorage,
      'commerce_payment' => $paymentStorage,
      default => throw new \InvalidArgumentException('Unexpected entity type.'),
    });

    $clients = $this->getMockBuilder(MercadoPagoClientFactory::class)
      ->onlyMethods(['criar'])
      ->getMock();
    $clients->method('criar')->willReturnCallback(static function (string $alias) use ($chargeback): object {
      if ($alias === 'usuarios') {
        return new MercadoPagoChargebackNotificationServiceTestClient((object) ['id' => 12345]);
      }
      return new MercadoPagoChargebackNotificationServiceTestClient($chargeback);
    });

    $operations = $this->createMock(PaymentOperationsInterface::class);
    if ($paymentStatus !== NULL) {
      $operations->expects(self::once())->method('refresh')->with($payment)->willReturn($paymentStatus);
    }
    else {
      $operations->expects(self::never())->method('refresh');
    }
    $logger = $this->createMock(LoggerChannelInterface::class);
    $service = new MercadoPagoChargebackNotificationService($entityTypeManager, $clients, $operations, $logger);
    return [$service, $payment, $operations];
  }

  /**
   * Uses the provider case payment ID and seller header, then refreshes safely.
   */
  public function testFetchesCaseWithSellerHeaderAndRefreshesExactPayment(): void {
    $chargeback = (object) ['id' => '987654', 'payment_id' => 456789, 'status' => 'open'];
    [$service] = $this->buildService(['payment-1'], $chargeback);

    $service->processar('987654');
  }

  /**
   * Refuses missing or duplicate local payment associations.
   *
   * @param list<string> $paymentIds
   *   Query results.
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('ambiguousPaymentAssociations')]
  public function testAmbiguousPaymentAssociationIsRejected(array $paymentIds): void {
    $chargeback = (object) ['id' => '987654', 'payment_id' => 456789, 'status' => 'open'];
    [$service] = $this->buildService($paymentIds, $chargeback, NULL);

    try {
      $service->processar('987654');
      self::fail('Expected ambiguous payment association to be rejected.');
    }
    catch (MercadoPagoApiException $exception) {
      self::assertSame(409, $exception->codigoHttp());
    }
  }

  /**
   * Data provider for ambiguous associations.
   *
   * @return array<string, array{list<string>}>
   *   Query result cases.
   */
  public static function ambiguousPaymentAssociations(): array {
    return [
      'missing' => [[]],
      'duplicate' => [['payment-1', 'payment-2']],
    ];
  }

  /**
   * Rejects a provider case whose returned ID differs from the signed ID.
   */
  public function testMismatchedProviderCaseIdIsRejected(): void {
    [$service] = $this->buildService([], (object) ['id' => 'other', 'payment_id' => 456789], NULL);

    try {
      $service->processar('987654');
      self::fail('Expected mismatched chargeback ID to be rejected.');
    }
    catch (MercadoPagoApiException $exception) {
      self::assertSame(502, $exception->codigoHttp());
    }
  }

  /**
   * Provider transport errors become retryable safe failures.
   */
  public function testProviderFailureDoesNotExposeRemoteException(): void {
    [$service] = $this->buildService([], new \RuntimeException('PRIVATE_RESPONSE_SENTINEL'), NULL);

    try {
      $service->processar('987654');
      self::fail('Expected provider failure to be normalized.');
    }
    catch (MercadoPagoApiException $exception) {
      self::assertSame(502, $exception->codigoHttp());
      self::assertStringNotContainsString('PRIVATE_RESPONSE_SENTINEL', $exception->getMessage());
    }
  }

}
