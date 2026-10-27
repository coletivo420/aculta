<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\aculta_portal\Exception\PaymentOperationException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Records recurring donation subscription notifications.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoSubscriptionRecorderTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Builds a recorder for order 7 and the requested transitions.
   *
   * @param string $tipo
   *   Tipo do pedido carregado.
   * @param string $estado
   *   Estado atual do pedido.
   * @param string[] $transicoes
   *   Transições permitidas a partir do estado atual.
   * @param object[] $existentes
   *   Pagamentos já gravados para o pedido.
   * @param bool $lock_disponivel
   *   Se o lock do pagamento foi adquirido.
   * @param string $status_assinatura
   *   Status remoto já registrado no pedido.
   * @param int|null $versao_assinatura
   *   Versão remota já registrada no pedido.
   *
   * @return array{0: MercadoPagoSubscriptionRecorder, 1: MockObject, 2: MockObject, 3: MockObject, 4: LockBackendInterface}
   *   Gravador, pedido, armazém de pagamentos, estado e lock.
   */
  private function contexto(string $tipo, string $estado, array $transicoes, array $existentes, bool $lock_disponivel = TRUE, string $status_assinatura = '', ?int $versao_assinatura = NULL): array {
    $item = $this->createMock(StateItemInterface::class);
    $item->method('getId')->willReturn($estado);
    $item->method('getTransitions')->willReturn(array_fill_keys($transicoes, new \stdClass()));

    $pedido = $this->createMock(OrderInterface::class);
    $pedido->method('id')->willReturn(7);
    $pedido->method('bundle')->willReturn($tipo);
    $pedido->method('getTotalPrice')->willReturn(new Price('10.00', 'BRL'));
    $pedido->method('getState')->willReturn($item);
    $pedido->method('getData')->willReturnCallback(
      static fn (string $key, mixed $default = NULL): mixed => match ($key) {
        MercadoPagoSubscriptionRecorder::CHAVE_ASSINATURA => 'pre-9',
        MercadoPagoSubscriptionRecorder::CHAVE_STATUS => $status_assinatura,
        MercadoPagoSubscriptionRecorder::CHAVE_VERSAO => $versao_assinatura,
        default => $default,
      },
    );

    $pedidos = $this->createMock(EntityStorageInterface::class);
    $pedidos->method('load')->with(7)->willReturn($pedido);
    $pagamentos = $this->createMock(EntityStorageInterface::class);
    $pagamentos->method('loadByProperties')->willReturn($existentes);

    $entidades = $this->createMock(EntityTypeManagerInterface::class);
    $entidades->method('getStorage')->willReturnCallback(
      fn (string $nome) => $nome === 'commerce_order' ? $pedidos : $pagamentos
    );

    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->method('obterAssinatura')->willReturn((object) [
      'id' => 'pre-9',
      'external_reference' => '7',
      'status' => 'authorized',
      'version' => 1,
    ]);
    $tempo = $this->createMock(TimeInterface::class);
    $tempo->method('getRequestTime')->willReturn(1700000000);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn($lock_disponivel);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $servico = new MercadoPagoSubscriptionRecorder($entidades, $recorrencia, $logger, $tempo, $lock);
    return [$servico, $pedido, $pagamentos, $item, $lock, $recorrencia, $logger];
  }

  /**
   * Provider response bodies never enter logs or public operation errors.
   */
  public function testSubscriptionActionFailureDoesNotExposeProviderException(): void {
    [$servico, $pedido, , , , $recorrencia, $logger] = $this->contexto(
      'assinatura',
      'completed',
      [],
      [],
      TRUE,
      MercadoPagoSubscriptionRecorder::STATUS_ATIVA,
    );
    $gateway = $this->gateway();
    $recorrencia->expects($this->once())
      ->method('alterarStatusAssinatura')
      ->with($gateway, 'pre-9', MercadoPagoSubscriptionRecorder::STATUS_PAUSADA)
      ->willThrowException(new MercadoPagoApiException('Falha segura da API.', 503, new \RuntimeException('PRIVATE_TOKEN_SENTINEL')));
    $logger->expects($this->once())
      ->method('error')
      ->with('Falha de API ao alterar assinatura do pedido @order (HTTP @status).', [
        '@order' => 7,
        '@status' => 503,
      ]);

    $this->expectException(PaymentOperationException::class);
    $this->expectExceptionMessage('Falha segura da API.');
    $servico->change($gateway, $pedido, 'pausar');
  }

  /**
   * Tests expected behavior.
   */
  private function gateway(): PaymentGatewayInterface {
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('id')->willReturn('mercado_pago');
    return $gateway;
  }

  /**
   * Tests expected behavior.
   */
  private function cobrancaAprovada(): array {
    return [
      'id' => 'cob-1',
      'preapproval_id' => 'pre-9',
      'status' => 'processed',
      'transaction_amount' => '10.00',
      'currency_id' => 'BRL',
      'payment' => ['id' => '555', 'status' => 'approved'],
    ];
  }

  /**
   * Tests expected behavior.
   */
  public function testApprovedChargeCreatesPaymentAndMarksOrderActive(): void {
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

  /**
   * Tests expected behavior.
   */
  public function testPagamentoNaoAprovadoNaoGravaNada(): void {
    // `processed` without an approved payment is not revenue.
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

  /**
   * Tests expected behavior.
   */
  public function testMesmoPagamentoNotificadoDuasVezesGravaUmaVez(): void {
    $existente = $this->createMock(PaymentInterface::class);
    $existente->method('getOrderId')->willReturn(7);
    [$servico, $pedido, $pagamentos] = $this->contexto('assinatura', 'completed', [], [$existente]);
    $pagamentos->expects($this->never())->method('create');
    // Update charge state without duplicating the Commerce payment.
    $pedido->expects($this->once())->method('save');

    $servico->cobranca($this->gateway(), $this->cobrancaAprovada());
  }

  /**
   * Tests expected behavior.
   */
  public function testCancellationUpdatesOrderWhenWorkflowAllows(): void {
    [$servico, $pedido, , $item] = $this->contexto('assinatura', 'completed', ['cancel'], []);
    $pedido->expects($this->exactly(2))->method('setData')->willReturnCallback(
      static function (string $key, mixed $value): void {
        if ($key === MercadoPagoSubscriptionRecorder::CHAVE_STATUS) {
          self::assertSame('canceled', $value);
          return;
        }
        self::assertSame(MercadoPagoSubscriptionRecorder::CHAVE_VERSAO, $key);
        self::assertSame(1, $value);
      },
    );
    $pedido->expects($this->once())->method('save');
    $item->expects($this->once())->method('applyTransitionById')->with('cancel');

    $servico->assinatura((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'canceled', 'version' => 1]);
  }

  /**
   * Tests expected behavior.
   */
  public function testDifferentOrderTypeIsIgnored(): void {
    [$servico, $pedido] = $this->contexto('doacao', 'draft', [], []);
    $pedido->expects($this->never())->method('save');

    $servico->assinatura((object) [
      'id' => 'pre-9',
      'external_reference' => '7',
      'status' => 'authorized',
      'version' => 1,
    ]);
  }

  /**
   * Keeps orders written with the former spelling readable as canceled.
   */
  public function testLegacyCancellationStatusIsNormalizedForReading(): void {
    [$servico, $pedido] = $this->contexto(
      'assinatura',
      'completed',
      [],
      [],
      TRUE,
      'cancelled',
    );

    $this->assertSame(MercadoPagoSubscriptionRecorder::STATUS_CANCELADA, $servico->statusDoPedido($pedido));
  }

  /**
   * Does not apply a subscription event for an ID not stored on the order.
   */
  public function testSubscriptionEventWithDifferentIdIsIgnored(): void {
    [$servico, $pedido] = $this->contexto('assinatura', 'completed', ['cancel'], []);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');

    $servico->assinatura((object) [
      'id' => 'other-subscription',
      'external_reference' => '7',
      'status' => 'cancelled',
      'version' => 1,
    ]);
  }

  /**
   * Ignores an older provider version even when its status would regress.
   */
  public function testOlderSubscriptionEventCannotRegressOrder(): void {
    [$servico, $pedido] = $this->contexto('assinatura', 'completed', [], [], TRUE, 'paused', 4);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');

    $servico->assinatura((object) [
      'id' => 'pre-9',
      'external_reference' => '7',
      'status' => 'authorized',
      'version' => 3,
    ]);
  }

  /**
   * Conflicting states at one provider version are retried, not persisted.
   */
  public function testConflictingStatusAtSameSubscriptionVersionIsRejected(): void {
    [$servico, $pedido] = $this->contexto('assinatura', 'completed', [], [], TRUE, 'paused', 4);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');
    $this->expectException(MercadoPagoApiException::class);

    $servico->assinatura((object) [
      'id' => 'pre-9',
      'external_reference' => '7',
      'status' => 'authorized',
      'version' => 4,
    ]);
  }

  /**
   * Persists the newest status and provider version together.
   */
  public function testNewerSubscriptionEventAdvancesOrderVersion(): void {
    [$servico, $pedido] = $this->contexto('assinatura', 'completed', [], [], TRUE, 'paused', 4);
    $pedido->expects($this->exactly(2))->method('setData')->willReturnCallback(
      static function (string $key, mixed $value): void {
        if ($key === MercadoPagoSubscriptionRecorder::CHAVE_STATUS) {
          self::assertSame('authorized', $value);
          return;
        }
        self::assertSame(MercadoPagoSubscriptionRecorder::CHAVE_VERSAO, $key);
        self::assertSame(5, $value);
      },
    );
    $pedido->expects($this->once())->method('save');

    $servico->assinatura((object) [
      'id' => 'pre-9',
      'external_reference' => '7',
      'status' => 'authorized',
      'version' => 5,
    ]);
  }

  /**
   * Keeps a contended webhook retryable without saving order state.
   */
  public function testContendedSubscriptionWebhookReturnsRetryableFailure(): void {
    [$servico, $pedido] = $this->contexto('assinatura', 'completed', [], [], FALSE);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');
    try {
      $servico->assinatura((object) [
        'id' => 'pre-9',
        'external_reference' => '7',
        'status' => 'authorized',
        'version' => 1,
      ]);
      $this->fail('Expected a retryable Mercado Pago error.');
    }
    catch (MercadoPagoApiException $exception) {
      $this->assertSame(503, $exception->codigoHttp());
    }
  }

  /**
   * Tests expected behavior.
   */
  public function testSituacaoDaCobrancaMapeiaCadaStatusDocumentado(): void {
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_PAGA, MercadoPagoSubscriptionRecorder::situacaoCobranca('processed', TRUE));
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_NAO_COBRADA, MercadoPagoSubscriptionRecorder::situacaoCobranca('processed', FALSE));
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_RECUSADA, MercadoPagoSubscriptionRecorder::situacaoCobranca('recycling', FALSE));
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_PROCESSANDO, MercadoPagoSubscriptionRecorder::situacaoCobranca('waiting for gateway', FALSE));
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_AGENDADA, MercadoPagoSubscriptionRecorder::situacaoCobranca('scheduled', FALSE));
    $this->assertSame(MercadoPagoSubscriptionRecorder::COBRANCA_DESCONHECIDA, MercadoPagoSubscriptionRecorder::situacaoCobranca('qualquer', FALSE));
  }

  /**
   * Tests expected behavior.
   */
  public function testRejectedChargeStoresStatusWithoutPayment(): void {
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

  /**
   * Tests expected behavior.
   */
  public function testApprovedChargeStoresPaidStatus(): void {
    [$servico, $pedido, $pagamentos, $item] = $this->contexto('assinatura', 'needs_payment', ['mark_paid'], []);
    $pedido->expects($this->once())->method('setData')
      ->with(MercadoPagoSubscriptionRecorder::CHAVE_COBRANCA, MercadoPagoSubscriptionRecorder::COBRANCA_PAGA);
    $pagamentos->method('create')->willReturn($this->createMock(PaymentInterface::class));
    $item->method('applyTransitionById');

    $servico->cobranca($this->gateway(), $this->cobrancaAprovada());
  }

  /**
   * Rejects an approved payment with a mismatched amount.
   */
  public function testApprovedChargeWithWrongAmountIsIgnored(): void {
    [$servico, $pedido, $pagamentos, , $lock] = $this->contexto('assinatura', 'needs_payment', ['mark_paid'], []);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');
    $pagamentos->expects($this->never())->method('loadByProperties');
    $pagamentos->expects($this->never())->method('create');
    $lock->expects($this->once())->method('release');

    $cobranca = $this->cobrancaAprovada();
    $cobranca['transaction_amount'] = '9.99';
    $servico->cobranca($this->gateway(), $cobranca);
  }

  /**
   * Does not record a charge in a currency different from the order currency.
   */
  public function testApprovedChargeWithWrongCurrencyIsIgnored(): void {
    [$servico, $pedido, $pagamentos] = $this->contexto('assinatura', 'needs_payment', ['mark_paid'], []);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');
    $pagamentos->expects($this->never())->method('loadByProperties');
    $pagamentos->expects($this->never())->method('create');

    $cobranca = $this->cobrancaAprovada();
    $cobranca['currency_id'] = 'USD';
    $servico->cobranca($this->gateway(), $cobranca);
  }

  /**
   * Does not treat an array payment ID as an approved financial record.
   */
  public function testArrayPaymentIdIsNotCoerced(): void {
    [$servico, $pedido, $pagamentos] = $this->contexto('assinatura', 'needs_payment', ['mark_paid'], []);
    $pedido->expects($this->once())->method('setData')
      ->with(MercadoPagoSubscriptionRecorder::CHAVE_COBRANCA, MercadoPagoSubscriptionRecorder::COBRANCA_NAO_COBRADA);
    $pedido->expects($this->once())->method('save');
    $pagamentos->expects($this->never())->method('create');
    $cobranca = $this->cobrancaAprovada();
    $cobranca['payment']['id'] = ['555'];
    $servico->cobranca($this->gateway(), $cobranca);
  }

  /**
   * Does not associate a provider payment already linked to another order.
   */
  public function testRemotePaymentAlreadyLinkedToAnotherOrderIsIgnored(): void {
    $existente = $this->createMock(PaymentInterface::class);
    $existente->method('getOrderId')->willReturn(8);
    [$servico, $pedido, $pagamentos] = $this->contexto('assinatura', 'needs_payment', ['mark_paid'], [$existente]);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');
    $pagamentos->expects($this->once())->method('loadByProperties')
      ->with(['payment_gateway' => 'mercado_pago', 'remote_id' => '555']);
    $pagamentos->expects($this->never())->method('create');

    $servico->cobranca($this->gateway(), $this->cobrancaAprovada());
  }

  /**
   * Retries a concurrent notification instead of risking a duplicate payment.
   */
  public function testChargeLockContentionReturnsRetryableFailure(): void {
    [$servico, $pedido, $pagamentos] = $this->contexto('assinatura', 'needs_payment', ['mark_paid'], [], FALSE);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');
    $pagamentos->expects($this->never())->method('loadByProperties');
    $pagamentos->expects($this->never())->method('create');

    $this->expectException(MercadoPagoApiException::class);
    $servico->cobranca($this->gateway(), $this->cobrancaAprovada());
  }

}
