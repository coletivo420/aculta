<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\MercadoPago;

use Drupal\aculta_portal\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_portal\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_portal\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Ações do doador sobre a assinatura (pausar, reativar, cancelar) e a regra de status permitido.
 */
#[Group('aculta_portal')]
final class MercadoPagoSubscriptionActionsTest extends UnitTestCase {

  /**
   * Pedido de assinatura com status e id da assinatura guardados em `data`.
   *
   * @param string[] $transicoes
   *   Transições permitidas pelo workflow a partir do estado atual.
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

  private function servico(MockObject $recorrencia): MercadoPagoSubscriptionRecorder {
    return new MercadoPagoSubscriptionRecorder(
      $this->createMock(EntityTypeManagerInterface::class),
      $recorrencia,
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(TimeInterface::class),
    );
  }

  private function gateway(): PaymentGatewayInterface {
    return $this->createMock(PaymentGatewayInterface::class);
  }

  public function testPausarUmaAssinaturaAtivaChamaOMercadoPagoEAtualizaOPedido(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')
      ->with($this->anything(), 'pre-9', 'paused')
      ->willReturn((object) ['status' => 'paused']);
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $pedido->expects($this->once())->method('setData')->with(MercadoPagoSubscriptionRecorder::CHAVE_STATUS, 'paused');
    $pedido->expects($this->once())->method('save');

    $this->servico($recorrencia)->alterar($this->gateway(), $pedido, 'pausar');
  }

  public function testReativarUmaAssinaturaPausadaVoltaParaAtiva(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')->with($this->anything(), 'pre-9', 'authorized')
      ->willReturn((object) ['status' => 'authorized']);
    $pedido = $this->pedido('assinatura', 'paused', 'pre-9', [], $item);

    $this->servico($recorrencia)->alterar($this->gateway(), $pedido, 'reativar');
  }

  public function testCancelarUmaAssinaturaAtivaCancelaOPedido(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')->with($this->anything(), 'pre-9', 'cancelled')
      ->willReturn((object) ['status' => 'cancelled']);
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', ['cancel'], $item);
    $item->expects($this->once())->method('applyTransitionById')->with('cancel');

    $this->servico($recorrencia)->alterar($this->gateway(), $pedido, 'cancelar');
  }

  public function testAcaoForaDoStatusPermitidoNaoChegaAoMercadoPago(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->never())->method('alterarStatusAssinatura');
    $pedido = $this->pedido('assinatura', 'paused', 'pre-9', [], $item);
    $pedido->expects($this->never())->method('save');

    $this->expectException(\InvalidArgumentException::class);
    $this->servico($recorrencia)->alterar($this->gateway(), $pedido, 'pausar');
  }

  public function testPedidoDeDoacaoAvulsaNaoTemAcoesDeAssinatura(): void {
    $servico = $this->servico($this->createMock(MercadoPagoRecurrenceService::class));
    $pedido = $this->pedido('doacao', 'authorized', 'pre-9', []);

    $this->assertFalse($servico->podeAlterar($pedido, 'pausar'));
  }

  public function testFalhaDoMercadoPagoNaoAlteraOPedido(): void {
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->method('alterarStatusAssinatura')->willThrowException(new MercadoPagoApiException('falha', 500));
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');

    $this->expectException(MercadoPagoApiException::class);
    $this->servico($recorrencia)->alterar($this->gateway(), $pedido, 'pausar');
  }

}
