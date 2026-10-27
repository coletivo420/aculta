<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use PHPUnit\Framework\MockObject\MockObject;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoPaymentService;
use Drupal\aculta_pagamentos_mercadopago\Sales\MercadoPagoPaymentPolicy;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\aculta_portal\Exception\PaymentOperationException;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Atualização e cancelamento, usando um dublê do cliente do SDK.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoPaymentServiceTest extends UnitTestCase {

  /**
   * Sets up the test fixture.
   */
  protected function setUp(): void {
    parent::setUp();
    // Mensagens TranslatableMarkup precisam de um serviço de tradução.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Creates a fake SDK client that records cancellation operations.
   */
  private function cliente(object $remoto, ?object &$registro = NULL, ?object $resposta_cancelamento = NULL): object {
    $registro = new \stdClass();
    $registro->cancelado = FALSE;
    return new class($remoto, $registro, $resposta_cancelamento) {

      public function __construct(private object $remoto, private object $registro, private ?object $respostaCancelamento) {}

      /**
       * Performs the get operation.
       */
      public function get(int $id): object {
        return $this->remoto;
      }

      /**
       * Performs the cancel operation.
       */
      public function cancel(int $id): object {
        $this->registro->cancelado = TRUE;
        return $this->respostaCancelamento ?? (object) ['id' => $this->remoto->id, 'status' => 'cancelled'];
      }

    };
  }

  /**
   * Performs the pagamento operation.
   */
  private function pagamento(string $estado, string $remoteId = '123456789'): PaymentInterface&MockObject {
    $pagamento = $this->createMock(PaymentInterface::class);
    $pagamento->method('id')->willReturn(1);
    $pagamento->method('getRemoteId')->willReturn($remoteId);
    $pagamento->method('getAmount')->willReturn(new Price('120.00', 'BRL'));
    $pagamento->method('getPaymentGateway')->willReturn($this->createMock(PaymentGatewayInterface::class));
    $etapa = new class($estado) {

      public function __construct(private string $id) {}

      /**
       * Performs the get id operation.
       */
      public function getId(): string {
        return $this->id;
      }

    };
    $pagamento->method('getState')->willReturn($etapa);
    return $pagamento;
  }

  /**
   * Performs the servico operation.
   */
  private function servico(object $cliente, ?PaymentInterface $pagamento = NULL, ?LockBackendInterface $lock = NULL): MercadoPagoPaymentService {
    $fabrica = $this->createMock(MercadoPagoClientFactory::class);
    $fabrica->method('criar')->willReturn($cliente);
    $log = $this->createMock(LoggerChannelInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($pagamento);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('commerce_payment')->willReturn($storage);
    if ($lock === NULL) {
      $lock = $this->createMock(LockBackendInterface::class);
      $lock->method('acquire')->willReturn(TRUE);
    }
    return new MercadoPagoPaymentService($fabrica, new MercadoPagoPaymentPolicy(), $log, $manager, $lock);
  }

  /**
   * Tests the aprovado em authorization vira completed e nao cancela behavior.
   */
  public function testApprovedAuthorizationBecomesCompletedWithoutCancel(): void {
    $pagamento = $this->pagamento('authorization');
    $pagamento->expects($this->once())->method('setState')->with('completed');
    $pagamento->expects($this->once())->method('save');
    $cliente = $this->cliente((object) [
      'id' => 123456789,
      'status' => 'approved',
      'transaction_amount' => 120.0,
      'currency_id' => 'BRL',
      'transaction_amount_refunded' => 0,
    ]);
    $status = $this->servico($cliente, $pagamento)->refresh($pagamento);
    $this->assertSame('approved', $status);
  }

  /**
   * Tests the estorno total registra valor estornado e estado behavior.
   */
  public function testFullRefundRecordsAmountAndState(): void {
    $pagamento = $this->pagamento('completed');
    $pagamento->expects($this->once())->method('setRefundedAmount')->with($this->callback(
      static fn (Price $p): bool => $p->getNumber() === '120.00' && $p->getCurrencyCode() === 'BRL'
    ));
    $pagamento->expects($this->once())->method('setState')->with('refunded');
    $cliente = $this->cliente((object) [
      'id' => 123456789,
      'status' => 'refunded',
      'transaction_amount' => 120.0,
      'currency_id' => 'BRL',
      'transaction_amount_refunded' => 120.0,
    ]);
    $this->servico($cliente, $pagamento)->atualizar($pagamento);
  }

  /**
   * Tests the cancelar pendente usa void e libera a authorization behavior.
   */
  public function testCancelPendingUsesVoidAndReleasesAuthorization(): void {
    $pagamento = $this->pagamento('authorization');
    $pagamento->expects($this->once())->method('setState')->with('authorization_voided');
    $registro = NULL;
    $cliente = $this->cliente((object) [
      'id' => 123456789,
      'status' => 'pending',
      'transaction_amount' => 120.0,
      'currency_id' => 'BRL',
    ], $registro);
    $this->servico($cliente, $pagamento)->cancel($pagamento);
    $this->assertTrue($registro->cancelado);
  }

  /**
   * Tests the cancelar aprovado eh recusado sem chamar o sdk behavior.
   */
  public function testCancelApprovedIsRejectedWithoutCallingSdk(): void {
    $pagamento = $this->pagamento('completed');
    $registro = NULL;
    $cliente = $this->cliente((object) [
      'id' => 123456789,
      'status' => 'approved',
      'transaction_amount' => 120.0,
      'currency_id' => 'BRL',
    ], $registro);
    $this->expectException(\DomainException::class);
    try {
      $this->servico($cliente, $pagamento)->cancelar($pagamento);
    }
    finally {
      $this->assertFalse($registro->cancelado);
    }
  }

  /**
   * Reconciles a cancellation accepted remotely before a local save failure.
   */
  public function testAlreadyCancelledRemotePaymentReconcilesWithoutSecondCancel(): void {
    $pagamento = $this->pagamento('authorization');
    $pagamento->expects($this->once())->method('setRemoteState')->with('cancelled');
    $pagamento->expects($this->once())->method('setState')->with('authorization_voided');
    $pagamento->expects($this->once())->method('save');
    $registro = NULL;
    $cliente = $this->cliente((object) [
      'id' => 123456789,
      'status' => 'cancelled',
      'transaction_amount' => 120.0,
      'currency_id' => 'BRL',
    ], $registro);

    $this->servico($cliente, $pagamento)->cancelar($pagamento);

    $this->assertFalse($registro->cancelado);
  }

  /**
   * Refuses to reconcile a provider response that identifies another payment.
   */
  public function testRefreshRejectsMismatchedProviderPaymentId(): void {
    $pagamento = $this->pagamento('authorization');
    $pagamento->expects($this->never())->method('setRemoteState');
    $pagamento->expects($this->never())->method('setState');
    $pagamento->expects($this->never())->method('save');
    $cliente = $this->cliente((object) [
      'id' => 99,
      'status' => 'approved',
      'transaction_amount' => 120.0,
      'currency_id' => 'BRL',
    ]);

    $this->expectException(PaymentOperationException::class);
    $this->servico($cliente, $pagamento)->refresh($pagamento);
  }

  /**
   * Does not expose transport exception details to the payment operations API.
   */
  public function testRefreshHidesTransportErrorDetails(): void {
    $pagamento = $this->pagamento('authorization');
    $cliente = new class() {

      /**
       * Simulates a timeout containing private provider details.
       */
      public function get(int $id): object {
        throw new \RuntimeException('private provider token');
      }

    };

    $this->expectException(PaymentOperationException::class);
    $this->expectExceptionMessage('O Mercado Pago não respondeu como esperado.');
    $this->servico($cliente, $pagamento)->refresh($pagamento);
  }

  /**
   * Does not persist a cancellation response tied to another payment.
   */
  public function testCancelRejectsMismatchedProviderPaymentId(): void {
    $pagamento = $this->pagamento('authorization');
    $pagamento->expects($this->never())->method('setRemoteState');
    $pagamento->expects($this->never())->method('setState');
    $pagamento->expects($this->never())->method('save');
    $registro = NULL;
    $cliente = $this->cliente(
      (object) ['id' => 123456789, 'status' => 'pending'],
      $registro,
      (object) ['id' => 99, 'status' => 'cancelled'],
    );

    $this->expectException(MercadoPagoApiException::class);
    $this->servico($cliente, $pagamento)->cancelar($pagamento);
  }

  /**
   * Requests retry instead of overlapping a payment notification operation.
   */
  public function testRefreshReturnsRetryableFailureWhenPaymentLockIsBusy(): void {
    $pagamento = $this->pagamento('authorization');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('aculta_pagamentos_mercadopago.payment.123456789', 300)
      ->willReturn(FALSE);
    $lock->expects($this->never())->method('release');
    $cliente = new class() {

      /**
       * Fails the test if the provider is reached while the lock is busy.
       */
      public function get(int $id): object {
        throw new \LogicException('Provider call should not happen.');
      }

    };

    $this->expectException(PaymentOperationException::class);
    $this->expectExceptionMessage('Outra operação deste pagamento está em andamento; tente novamente.');
    $this->servico($cliente, $pagamento, $lock)->refresh($pagamento);
  }

  /**
   * Refuses mismatched amount or currency before changing local payment state.
   */
  #[DataProvider('mismatchedPaymentProvider')]
  public function testRefreshRejectsMismatchedRemoteAmounts(string $remote_amount, string $currency): void {
    $pagamento = $this->pagamento('authorization');
    $pagamento->expects($this->never())->method('setRemoteState');
    $pagamento->expects($this->never())->method('setState');
    $pagamento->expects($this->never())->method('save');
    $cliente = $this->cliente((object) [
      'id' => 123456789,
      'status' => 'approved',
      'transaction_amount' => $remote_amount,
      'currency_id' => $currency,
    ]);

    $this->expectException(PaymentOperationException::class);
    $this->servico($cliente, $pagamento)->refresh($pagamento);
  }

  /**
   * Provides mismatched remote payment values.
   *
   * @return iterable<string, array{string, string}>
   *   Invalid remote amount/currency combinations.
   */
  public static function mismatchedPaymentProvider(): iterable {
    yield 'amount mismatch' => ['119.99', 'BRL'];
    yield 'currency mismatch' => ['120.00', 'USD'];
  }

  /**
   * Tests the pagamento sem id remoto eh recusado behavior.
   */
  public function testPagamentoSemIdRemotoEhRecusado(): void {
    $this->expectException(\DomainException::class);
    $pagamento = $this->pagamento('completed', 'abc');
    $cliente = $this->cliente((object) [
      'id' => 123456789,
      'status' => 'approved',
      'transaction_amount' => 120.0,
      'currency_id' => 'BRL',
    ]);
    $this->servico($cliente, $pagamento)->atualizar($pagamento);
  }

}
