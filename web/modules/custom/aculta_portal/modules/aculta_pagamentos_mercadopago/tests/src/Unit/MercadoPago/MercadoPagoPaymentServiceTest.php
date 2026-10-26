<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use PHPUnit\Framework\MockObject\MockObject;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoPaymentService;
use Drupal\aculta_pagamentos_mercadopago\Sales\MercadoPagoPaymentPolicy;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Atualização e cancelamento de pagamento, com o cliente do SDK substituído por um duplo.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoPaymentServiceTest extends UnitTestCase {

  /**
   * Sets up the test fixture.
   */
  protected function setUp(): void {
    parent::setUp();
    // Mensagens traduzidas (TranslatableMarkup) precisam de um container com tradução.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Cliente falso do SDK: devolve o pagamento remoto informado e registra o cancelamento.
   */
  private function cliente(object $remoto, ?object &$registro = NULL): object {
    $registro = new \stdClass();
    $registro->cancelado = FALSE;
    return new class($remoto, $registro) {

      public function __construct(private object $remoto, private object $registro) {}

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
        return (object) ['status' => 'cancelled'];
      }

    };
  }

  /**
   * Performs the pagamento operation.
   */
  private function pagamento(string $estado, string $remoteId = '123456789'): PaymentInterface&MockObject {
    $pagamento = $this->createMock(PaymentInterface::class);
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
  private function servico(object $cliente): MercadoPagoPaymentService {
    $fabrica = $this->createMock(MercadoPagoClientFactory::class);
    $fabrica->method('criar')->willReturn($cliente);
    $log = $this->createMock(LoggerChannelInterface::class);
    return new MercadoPagoPaymentService($fabrica, new MercadoPagoPaymentPolicy(), $log);
  }

  /**
   * Tests the aprovado em authorization vira completed e nao cancela behavior.
   */
  public function testApprovedAuthorizationBecomesCompletedWithoutCancel(): void {
    $pagamento = $this->pagamento('authorization');
    $pagamento->expects($this->once())->method('setState')->with('completed');
    $pagamento->expects($this->once())->method('save');
    $status = $this->servico($this->cliente((object) ['status' => 'approved', 'transaction_amount_refunded' => 0]))->refresh($pagamento);
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
    $this->servico($this->cliente((object) ['status' => 'refunded', 'transaction_amount_refunded' => 120.0]))->atualizar($pagamento);
  }

  /**
   * Tests the cancelar pendente usa void e libera a authorization behavior.
   */
  public function testCancelPendingUsesVoidAndReleasesAuthorization(): void {
    $pagamento = $this->pagamento('authorization');
    $pagamento->expects($this->once())->method('setState')->with('authorization_voided');
    $registro = NULL;
    $cliente = $this->cliente((object) ['status' => 'pending'], $registro);
    $this->servico($cliente)->cancel($pagamento);
    $this->assertTrue($registro->cancelado);
  }

  /**
   * Tests the cancelar aprovado eh recusado sem chamar o sdk behavior.
   */
  public function testCancelApprovedIsRejectedWithoutCallingSdk(): void {
    $pagamento = $this->pagamento('completed');
    $registro = NULL;
    $cliente = $this->cliente((object) ['status' => 'approved'], $registro);
    $this->expectException(\DomainException::class);
    try {
      $this->servico($cliente)->cancelar($pagamento);
    }
    finally {
      $this->assertFalse($registro->cancelado);
    }
  }

  /**
   * Tests the pagamento sem id remoto eh recusado behavior.
   */
  public function testPagamentoSemIdRemotoEhRecusado(): void {
    $this->expectException(\DomainException::class);
    $this->servico($this->cliente((object) ['status' => 'approved']))->atualizar($this->pagamento('completed', 'abc'));
  }

}
