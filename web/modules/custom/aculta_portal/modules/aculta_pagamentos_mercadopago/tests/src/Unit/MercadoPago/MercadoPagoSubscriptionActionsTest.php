<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\aculta_portal\Exception\PaymentOperationException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Donor subscription actions and allowed status transitions.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoSubscriptionActionsTest extends UnitTestCase {

  /**
   * Pedido de assinatura com status e id da assinatura guardados em `data`.
   *
   * @param string $tipo
   *   Tipo de pedido.
   * @param string $statusMp
   *   Estado da assinatura no Mercado Pago.
   * @param string $assinatura
   *   Identificador da assinatura.
   * @param string[] $transicoes
   *   Transições permitidas pelo workflow a partir do estado atual.
   * @param \PHPUnit\Framework\MockObject\MockObject|null $item
   *   Recebe o mock do estado do pedido.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   Pedido simulado para o teste.
   */
  private function pedido(string $tipo, string $statusMp, string $assinatura, array $transicoes, ?MockObject &$item = NULL): MockObject {
    $item = $this->createMock(StateItemInterface::class);
    $item->method('getId')->willReturn('completed');
    $item->method('getTransitions')->willReturn(array_fill_keys($transicoes, new \stdClass()));

    $pedido = $this->createMock(OrderInterface::class);
    $pedido->method('bundle')->willReturn($tipo);
    $pedido->method('id')->willReturn(7);
    $pedido->method('getState')->willReturn($item);
    $pedido->method('getData')->willReturnMap([
      [MercadoPagoSubscriptionRecorder::CHAVE_ASSINATURA, NULL, $assinatura],
      [MercadoPagoSubscriptionRecorder::CHAVE_STATUS, '', $statusMp],
    ]);
    return $pedido;
  }

  /**
   * Performs the servico operation.
   */
  private function servico(MockObject $recorrencia): MercadoPagoSubscriptionRecorder {
    return new MercadoPagoSubscriptionRecorder(
      $this->createMock(EntityTypeManagerInterface::class),
      $recorrencia,
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(LockBackendInterface::class),
    );
  }

  /**
   * Performs the gateway operation.
   */
  private function gateway(): PaymentGatewayInterface {
    return $this->createMock(PaymentGatewayInterface::class);
  }

  /**
   * Pauses an active subscription and updates its order.
   */
  public function testPauseActiveSubscriptionCallsGatewayAndUpdatesOrder(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')
      ->with($this->anything(), 'pre-9', 'paused')
      ->willReturn((object) ['status' => 'paused']);
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $pedido->expects($this->once())->method('setData')->with(MercadoPagoSubscriptionRecorder::CHAVE_STATUS, 'paused');
    $pedido->expects($this->once())->method('save');

    $this->servico($recorrencia)->alterar($this->gateway(), $pedido, 'pausar');
  }

  /**
   * Tests the reativar uma assinatura pausada volta para ativa behavior.
   */
  public function testReativarUmaAssinaturaPausadaVoltaParaAtiva(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')->with($this->anything(), 'pre-9', 'authorized')
      ->willReturn((object) ['status' => 'authorized']);
    $pedido = $this->pedido('assinatura', 'paused', 'pre-9', [], $item);

    $this->servico($recorrencia)->alterar($this->gateway(), $pedido, 'reativar');
  }

  /**
   * Tests the cancelar uma assinatura ativa cancela o pedido behavior.
   */
  public function testCancelActiveSubscriptionAndOrder(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')->with($this->anything(), 'pre-9', 'cancelled')
      ->willReturn((object) ['status' => 'cancelled']);
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', ['cancel'], $item);
    $item->expects($this->once())->method('applyTransitionById')->with('cancel');

    $this->servico($recorrencia)->alterar($this->gateway(), $pedido, 'cancelar');
  }

  /**
   * Tests the acao fora do status permitido nao chega ao mercado pago behavior.
   */
  public function testAcaoForaDoStatusPermitidoNaoChegaAoMercadoPago(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->never())->method('alterarStatusAssinatura');
    $pedido = $this->pedido('assinatura', 'paused', 'pre-9', [], $item);
    $pedido->expects($this->never())->method('save');

    $this->expectException(\InvalidArgumentException::class);
    $this->servico($recorrencia)->alterar($this->gateway(), $pedido, 'pausar');
  }

  /**
   * Tests the pedido de doacao avulsa nao tem acoes de assinatura behavior.
   */
  public function testPedidoDeDoacaoAvulsaNaoTemAcoesDeAssinatura(): void {
    $servico = $this->servico($this->createMock(MercadoPagoRecurrenceService::class));
    $pedido = $this->pedido('doacao', 'authorized', 'pre-9', []);

    $this->assertFalse($servico->podeAlterar($pedido, 'pausar'));
  }

  /**
   * Tests the falha do mercado pago nao altera o pedido behavior.
   */
  public function testGatewayFailureDoesNotChangeOrder(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->method('alterarStatusAssinatura')->willThrowException(new MercadoPagoApiException('falha', 500));
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');

    $this->expectException(MercadoPagoApiException::class);
    $this->servico($recorrencia)->alterar($this->gateway(), $pedido, 'pausar');
  }

  /**
   * Keeps provider API details out of the user-facing exception.
   */
  public function testProviderFailureIsWrappedWithSafeMessage(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->method('alterarStatusAssinatura')->willThrowException(
      new MercadoPagoApiException('Mensagem segura', 500, new \RuntimeException('RAW API DETAIL')),
    );
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', []);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('Falha de API'), $this->callback(
        static fn (array $context): bool => $context['@message'] === 'RAW API DETAIL',
      ));
    $service = new MercadoPagoSubscriptionRecorder(
      $this->createMock(EntityTypeManagerInterface::class),
      $recorrencia,
      $logger,
      $this->createMock(TimeInterface::class),
      $this->createMock(LockBackendInterface::class),
    );

    try {
      $service->change($this->gateway(), $pedido, 'pausar');
      $this->fail('Expected the neutral provider exception.');
    }
    catch (PaymentOperationException $exception) {
      $this->assertSame('Mensagem segura', $exception->getMessage());
      $this->assertSame('Mensagem segura', $exception->getPrevious()?->getMessage());
      $this->assertSame('RAW API DETAIL', $exception->getPrevious()?->getPrevious()?->getMessage());
    }
  }

}
