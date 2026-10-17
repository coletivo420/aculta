<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\MercadoPago;

use Drupal\aculta_portal\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_portal\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Gravação das notificações de assinatura de doação no pedido (aprovação, idempotência e cancelamento).
 */
#[Group('aculta_portal')]
final class MercadoPagoSubscriptionRecorderTest extends UnitTestCase {

  /**
   * Monta o gravador para um pedido (id 7) com o estado e as transições informados.
   *
   * @param string $tipo
   *   Tipo do pedido carregado.
   * @param string $estado
   *   Estado atual do pedido.
   * @param string[] $transicoes
   *   Transições permitidas a partir do estado atual.
   * @param object[] $existentes
   *   Pagamentos já gravados para o pedido.
   *
   * @return array{0: MercadoPagoSubscriptionRecorder, 1: MockObject, 2: MockObject, 3: MockObject}
   *   Gravador, pedido, armazém de pagamentos e item de estado.
   */
  private function contexto(string $tipo, string $estado, array $transicoes, array $existentes): array {
    $item = $this->createMock(StateItemInterface::class);
    $item->method('getId')->willReturn($estado);
    $item->method('getTransitions')->willReturn(array_fill_keys($transicoes, new \stdClass()));

    $pedido = $this->createMock(OrderInterface::class);
    $pedido->method('id')->willReturn(7);
    $pedido->method('bundle')->willReturn($tipo);
    $pedido->method('getTotalPrice')->willReturn(new Price('10.00', 'BRL'));
    $pedido->method('getState')->willReturn($item);

    $pedidos = $this->createMock(EntityStorageInterface::class);
    $pedidos->method('load')->with(7)->willReturn($pedido);
    $pagamentos = $this->createMock(EntityStorageInterface::class);
    $pagamentos->method('loadByProperties')->willReturn($existentes);

    $entidades = $this->createMock(EntityTypeManagerInterface::class);
    $entidades->method('getStorage')->willReturnCallback(
      fn (string $nome) => $nome === 'commerce_order' ? $pedidos : $pagamentos
    );

    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->method('obterAssinatura')->willReturn((object) ['external_reference' => '7', 'status' => 'authorized']);
    $tempo = $this->createMock(TimeInterface::class);
    $tempo->method('getRequestTime')->willReturn(1700000000);

    $servico = new MercadoPagoSubscriptionRecorder($entidades, $recorrencia, $this->createMock(LoggerChannelInterface::class), $tempo);
    return [$servico, $pedido, $pagamentos, $item];
  }

  private function gateway(): PaymentGatewayInterface {
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('id')->willReturn('mercado_pago');
    return $gateway;
  }

  private function cobrancaAprovada(): array {
    return [
      'id' => 'cob-1',
      'preapproval_id' => 'pre-9',
      'transaction_amount' => '10.00',
      'payment' => ['id' => '555', 'status' => 'approved'],
    ];
  }

  public function testCobrancaAprovadaGravaPagamentoEMarcaPedidoComoAtivo(): void {
    [$servico, $pedido, $pagamentos, $item] = $this->contexto('assinatura', 'needs_payment', ['mark_paid'], []);
    $pagamento = $this->createMock(PaymentInterface::class);
    $pagamento->expects($this->once())->method('save');
    $pagamentos->expects($this->once())->method('create')
      ->with($this->callback(fn (array $v) => $v['remote_id'] === '555' && $v['state'] === 'completed' && $v['order_id'] === 7))
      ->willReturn($pagamento);
    $item->expects($this->once())->method('applyTransitionById')->with('mark_paid');
    $pedido->expects($this->once())->method('save');

    $servico->cobranca($this->gateway(), $this->cobrancaAprovada());
  }

  public function testPagamentoNaoAprovadoNaoGravaNada(): void {
    // `processed` sem pagamento aprovado (parcela que esgotou as tentativas) não é receita.
    [$servico, $pedido, $pagamentos] = $this->contexto('assinatura', 'needs_payment', ['mark_paid'], []);
    $pagamentos->expects($this->never())->method('create');
    $pedido->expects($this->once())->method('save');

    $servico->cobranca($this->gateway(), [
      'id' => 'cob-2',
      'preapproval_id' => 'pre-9',
      'status' => 'processed',
      'payment' => ['id' => '556', 'status' => 'rejected'],
    ]);
  }

  public function testMesmoPagamentoNotificadoDuasVezesGravaUmaVez(): void {
    [$servico, $pedido, $pagamentos] = $this->contexto('assinatura', 'completed', [], [$this->createMock(PaymentInterface::class)]);
    $pagamentos->expects($this->never())->method('create');
    // A situação da cobrança é atualizada no pedido, mas o pagamento não é gravado de novo.
    $pedido->expects($this->once())->method('save');

    $servico->cobranca($this->gateway(), $this->cobrancaAprovada());
  }

  public function testCancelamentoDaAssinaturaCancelaOPedidoQuandoOWorkflowPermite(): void {
    [$servico, $pedido, , $item] = $this->contexto('assinatura', 'completed', ['cancel'], []);
    $pedido->expects($this->once())->method('setData')->with(MercadoPagoSubscriptionRecorder::CHAVE_STATUS, 'cancelled');
    $pedido->expects($this->once())->method('save');
    $item->expects($this->once())->method('applyTransitionById')->with('cancel');

    $servico->assinatura((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'cancelled']);
  }

  public function testPedidoDeOutroTipoNaoERegistrado(): void {
    [$servico, $pedido] = $this->contexto('doacao', 'draft', [], []);
    $pedido->expects($this->never())->method('save');

    $servico->assinatura((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'authorized']);
  }

  public function testSituacaoDaCobrancaMapeiaCadaStatusDocumentado(): void {
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_PAGA, MercadoPagoSubscriptionRecorder::situacaoCobranca('processed', TRUE));
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_NAO_COBRADA, MercadoPagoSubscriptionRecorder::situacaoCobranca('processed', FALSE));
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_RECUSADA, MercadoPagoSubscriptionRecorder::situacaoCobranca('recycling', FALSE));
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_PROCESSANDO, MercadoPagoSubscriptionRecorder::situacaoCobranca('waiting for gateway', FALSE));
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_AGENDADA, MercadoPagoSubscriptionRecorder::situacaoCobranca('scheduled', FALSE));
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_DESCONHECIDA, MercadoPagoSubscriptionRecorder::situacaoCobranca('qualquer', FALSE));
  }

  public function testCobrancaRecusadaGravaASituacaoSemCriarPagamento(): void {
    [$servico, $pedido, $pagamentos] = $this->contexto('assinatura', 'needs_payment', ['mark_paid'], []);
    $pedido->expects($this->once())->method('setData')
      ->with(MercadoPagoSubscriptionRecorder::CHAVE_COBRANCA, MercadoPagoSubscriptionRecorder::COBRANCA_RECUSADA);
    $pedido->expects($this->once())->method('save');
    $pagamentos->expects($this->never())->method('create');

    $servico->cobranca($this->gateway(), [
      'id' => 'cob-3',
      'preapproval_id' => 'pre-9',
      'status' => 'recycling',
      'payment' => ['id' => '557', 'status' => 'rejected'],
    ]);
  }

  public function testCobrancaAprovadaGravaASituacaoComoPaga(): void {
    [$servico, $pedido, $pagamentos, $item] = $this->contexto('assinatura', 'needs_payment', ['mark_paid'], []);
    $pedido->expects($this->once())->method('setData')
      ->with(MercadoPagoSubscriptionRecorder::CHAVE_COBRANCA, MercadoPagoSubscriptionRecorder::COBRANCA_PAGA);
    $pagamentos->method('create')->willReturn($this->createMock(PaymentInterface::class));
    $item->method('applyTransitionById');

    $servico->cobranca($this->gateway(), $this->cobrancaAprovada());
  }

}
