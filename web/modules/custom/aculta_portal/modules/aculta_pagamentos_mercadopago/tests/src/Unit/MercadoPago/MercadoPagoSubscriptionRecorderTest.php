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
   * @param string $assinatura_id
   *   ID remoto de assinatura armazenado no pedido.
   * @param mixed $criacao_pendente
   *   Marcador de tentativa remota de criação ainda sem conciliação.
   *
   * @return array{0: MercadoPagoSubscriptionRecorder, 1: MockObject, 2: MockObject, 3: MockObject, 4: LockBackendInterface}
   *   Gravador, pedido, armazém de pagamentos, estado e lock.
   */
  private function contexto(string $tipo, string $estado, array $transicoes, array $existentes, bool $lock_disponivel = TRUE, string $status_assinatura = '', ?int $versao_assinatura = NULL, string $assinatura_id = 'pre-9', mixed $criacao_pendente = NULL): array {
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
        MercadoPagoSubscriptionRecorder::CHAVE_ASSINATURA => $assinatura_id,
        MercadoPagoSubscriptionRecorder::CHAVE_STATUS => $status_assinatura,
        MercadoPagoSubscriptionRecorder::CHAVE_VERSAO => $versao_assinatura,
        MercadoPagoSubscriptionRecorder::CHAVE_CRIACAO_PENDENTE => $criacao_pendente,
        default => $default,
      },
    );

    $pedidos = $this->createMock(EntityStorageInterface::class);
    $pedidos->method('load')->with(7)->willReturn($pedido);
    $pedidos->method('loadUnchanged')->with(7)->willReturn($pedido);
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
    return [$servico, $pedido, $pagamentos, $item, $lock, $recorrencia, $logger, $pedidos];
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
   * Tests first creation persists the subscription before checkout redirects.
   */
  public function testSubscriptionCreationLinksFreshProviderSubscriptionToOrder(): void {
    $gateway = $this->gateway();
    $state = $this->createMock(StateItemInterface::class);
    $state->method('getTransitions')->willReturn(['place' => new \stdClass()]);
    $state->expects($this->once())->method('applyTransitionById')->with('place');
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn('7');
    $order->method('bundle')->willReturn('assinatura');
    $order->method('getEmail')->willReturn('donor@example.invalid');
    $order->method('getData')->willReturn('');
    $order->method('getState')->willReturn($state);
    $order->expects($this->exactly(4))->method('setData');
    $order->expects($this->exactly(2))->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('loadUnchanged')->with('7')->willReturn($order);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->once())->method('getStorage')->with('commerce_order')->willReturn($storage);
    $recurrence = $this->createMock(MercadoPagoRecurrenceService::class);
    $recurrence->expects($this->once())->method('criarAssinatura')->with($gateway, $this->callback(
      static fn (array $data): bool => $data['referencia'] === '7' && $data['email'] === 'donor@example.invalid'
    ))->willReturn([
      'id' => 'pre-10',
      'status' => 'pending',
      'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=pre-10',
    ]);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->with('aculta_pagamentos_mercadopago.subscription_order.7', 30)->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.subscription_order.7');
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $recorder = new MercadoPagoSubscriptionRecorder(
      $manager,
      $recurrence,
      $this->createMock(LoggerChannelInterface::class),
      $time,
      $lock,
    );

    $result = $recorder->criarAssinatura($gateway, $order, [
      'email' => 'ignored@example.invalid',
      'referencia' => '999',
      'back_url' => 'https://aculta.test/return',
    ]);

    $this->assertSame('pre-10', $result['id']);
    $this->assertSame('pending', $result['status']);
    $this->assertStringContainsString('preapproval_id=pre-10', $result['init_point']);
  }

  /**
   * Malformed provider IDs must not be string-cast and linked to the order.
   */
  public function testMalformedSubscriptionCreationResponseKeepsReconciliationMarker(): void {
    $gateway = $this->gateway();
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn('7');
    $order->method('bundle')->willReturn('assinatura');
    $order->method('getEmail')->willReturn('donor@example.invalid');
    $order->method('getData')->willReturn('');
    $order->expects($this->once())->method('setData')
      ->with(MercadoPagoSubscriptionRecorder::CHAVE_CRIACAO_PENDENTE, 1700000000);
    $order->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('loadUnchanged')->with('7')->willReturn($order);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->once())->method('getStorage')->with('commerce_order')->willReturn($storage);
    $recurrence = $this->createMock(MercadoPagoRecurrenceService::class);
    $recurrence->expects($this->once())->method('criarAssinatura')->willReturn([
      'id' => ['malformed'],
      'status' => 'pending',
      'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout',
    ]);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.subscription_order.7');
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $recorder = new MercadoPagoSubscriptionRecorder(
      $manager,
      $recurrence,
      $this->createMock(LoggerChannelInterface::class),
      $time,
      $lock,
    );

    $this->expectException(MercadoPagoApiException::class);
    $recorder->criarAssinatura($gateway, $order, []);
  }

  /**
   * An ambiguous remote timeout leaves a marker to prevent duplicate creation.
   */
  public function testSubscriptionCreationTimeoutKeepsReconciliationMarker(): void {
    $gateway = $this->gateway();
    $state = $this->createMock(StateItemInterface::class);
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn('7');
    $order->method('bundle')->willReturn('assinatura');
    $order->method('getEmail')->willReturn('donor@example.invalid');
    $order->method('getData')->willReturn(NULL);
    $order->method('getState')->willReturn($state);
    $order->expects($this->once())->method('setData')
      ->with(MercadoPagoSubscriptionRecorder::CHAVE_CRIACAO_PENDENTE, 1700000000);
    $order->expects($this->once())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('7')->willReturn($order);
    $storage->method('loadUnchanged')->with('7')->willReturn($order);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('commerce_order')->willReturn($storage);
    $recurrence = $this->createMock(MercadoPagoRecurrenceService::class);
    $recurrence->expects($this->once())->method('criarAssinatura')
      ->willThrowException(new MercadoPagoApiException('Falha segura.', 0));
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release');
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $recorder = new MercadoPagoSubscriptionRecorder(
      $manager,
      $recurrence,
      $this->createMock(LoggerChannelInterface::class),
      $time,
      $lock,
    );

    $this->expectException(MercadoPagoApiException::class);
    $recorder->criarAssinatura($gateway, $order, []);
  }

  /**
   * An order with an ambiguous prior attempt cannot issue another POST.
   */
  public function testPendingReconciliationMarkerBlocksDuplicateSubscriptionPost(): void {
    $gateway = $this->gateway();
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn('7');
    $order->method('bundle')->willReturn('assinatura');
    $order->method('getData')->willReturnCallback(
      static fn (string $key, mixed $default = NULL): mixed => $key === MercadoPagoSubscriptionRecorder::CHAVE_CRIACAO_PENDENTE ? 1700000000 : $default,
    );
    $order->expects($this->never())->method('setData');
    $order->expects($this->never())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('7')->willReturn($order);
    $storage->method('loadUnchanged')->with('7')->willReturn($order);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('commerce_order')->willReturn($storage);
    $recurrence = $this->createMock(MercadoPagoRecurrenceService::class);
    $recurrence->expects($this->never())->method('criarAssinatura');
    $recurrence->expects($this->never())->method('obterAssinatura');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release');
    $recorder = new MercadoPagoSubscriptionRecorder(
      $manager,
      $recurrence,
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(TimeInterface::class),
      $lock,
    );

    try {
      $recorder->criarAssinatura($gateway, $order, []);
      $this->fail('Expected pending remote reconciliation to block another creation.');
    }
    catch (MercadoPagoApiException $exception) {
      $this->assertSame(409, $exception->codigoHttp());
      $this->assertStringContainsString('conciliada', $exception->getMessage());
    }
  }

  /**
   * A request rejected by local validation clears the marker for a safe retry.
   */
  public function testInvalidSubscriptionRequestClearsReconciliationMarker(): void {
    $gateway = $this->gateway();
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn('7');
    $order->method('bundle')->willReturn('assinatura');
    $order->method('getData')->willReturn(NULL);
    $order->expects($this->exactly(2))->method('setData')
      ->with(MercadoPagoSubscriptionRecorder::CHAVE_CRIACAO_PENDENTE, $this->logicalOr(1700000000, NULL));
    $order->expects($this->exactly(2))->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('7')->willReturn($order);
    $storage->method('loadUnchanged')->with('7')->willReturn($order);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('commerce_order')->willReturn($storage);
    $recurrence = $this->createMock(MercadoPagoRecurrenceService::class);
    $recurrence->expects($this->once())->method('criarAssinatura')
      ->willThrowException(new \InvalidArgumentException('E-mail do pagador inválido.'));
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release');
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $recorder = new MercadoPagoSubscriptionRecorder(
      $manager,
      $recurrence,
      $this->createMock(LoggerChannelInterface::class),
      $time,
      $lock,
    );

    $this->expectException(\InvalidArgumentException::class);
    $recorder->criarAssinatura($gateway, $order, []);
  }

  /**
   * Tests a repeated submission resumes the linked pending subscription.
   */
  public function testSubscriptionCreationRetryDoesNotCreateDuplicateRemoteSubscription(): void {
    $gateway = $this->gateway();
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn('7');
    $order->method('bundle')->willReturn('assinatura');
    $order->method('getData')->with(MercadoPagoSubscriptionRecorder::CHAVE_ASSINATURA, '')->willReturn('pre-10');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('loadUnchanged')->with('7')->willReturn($order);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->once())->method('getStorage')->with('commerce_order')->willReturn($storage);
    $recurrence = $this->createMock(MercadoPagoRecurrenceService::class);
    $recurrence->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-10')->willReturn((object) [
      'id' => 'pre-10',
      'external_reference' => '7',
      'status' => 'pending',
      'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=pre-10',
    ]);
    $recurrence->expects($this->never())->method('criarAssinatura');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release');
    $recorder = new MercadoPagoSubscriptionRecorder(
      $manager,
      $recurrence,
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(TimeInterface::class),
      $lock,
    );

    $result = $recorder->criarAssinatura($gateway, $order, []);

    $this->assertSame('pre-10', $result['id']);
    $this->assertSame('pending', $result['status']);
  }

  /**
   * Existing pending subscriptions cannot redirect to a foreign origin.
   */
  public function testSubscriptionRetryRejectsForeignAuthorizationOrigin(): void {
    $gateway = $this->gateway();
    $pedido = $this->createMock(OrderInterface::class);
    $pedido->method('id')->willReturn(7);
    $pedido->method('bundle')->willReturn('assinatura');
    $pedido->method('getData')->with(MercadoPagoSubscriptionRecorder::CHAVE_ASSINATURA, '')->willReturn('pre-9');
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadUnchanged')->with(7)->willReturn($pedido);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('commerce_order')->willReturn($storage);
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-9')->willReturn((object) [
      'id' => 'pre-9',
      'external_reference' => '7',
      'status' => 'pending',
      'init_point' => 'https://mercadopago.com.br.attacker.invalid/checkout',
    ]);
    $recorrencia->expects($this->never())->method('criarAssinatura');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->with('aculta_pagamentos_mercadopago.subscription_order.7', 30)->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_pagamentos_mercadopago.subscription_order.7');
    $servico = new MercadoPagoSubscriptionRecorder(
      $manager,
      $recorrencia,
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(TimeInterface::class),
      $lock,
    );

    $this->expectException(MercadoPagoApiException::class);
    $servico->criarAssinatura($gateway, $pedido, []);
  }

  /**
   * A subscription linked to a different order cannot be reused at checkout.
   */
  public function testSubscriptionCreationRetryRejectsConflictingOrderReference(): void {
    $gateway = $this->gateway();
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn('7');
    $order->method('bundle')->willReturn('assinatura');
    $order->method('getData')->willReturn('pre-10');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('7')->willReturn($order);
    $storage->method('loadUnchanged')->with('7')->willReturn($order);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('commerce_order')->willReturn($storage);
    $recurrence = $this->createMock(MercadoPagoRecurrenceService::class);
    $recurrence->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-10')->willReturn((object) [
      'id' => 'pre-10',
      'external_reference' => '8',
      'status' => 'pending',
      'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=pre-10',
    ]);
    $recurrence->expects($this->never())->method('criarAssinatura');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release');
    $recorder = new MercadoPagoSubscriptionRecorder(
      $manager,
      $recurrence,
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(TimeInterface::class),
      $lock,
    );

    $this->expectException(MercadoPagoApiException::class);
    $recorder->criarAssinatura($gateway, $order, []);
  }

  /**
   * Tests expected behavior.
   */
  private function cobrancaAprovada(): array {
    return [
      'id' => 'cob-1',
      'preapproval_id' => 'pre-9',
      'status' => 'processed',
      'transaction_amount' => '1.0e1',
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
   * Requests provider retry while a new subscription ID is not linked yet.
   */
  public function testSubscriptionWebhookRetriesWhileCreationIsUnlinked(): void {
    [$servico, $pedido, , , $lock] = $this->contexto(
      'assinatura',
      'completed',
      [],
      [],
      TRUE,
      '',
      NULL,
      '',
      1700000000,
    );
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');
    $lock->expects($this->once())->method('acquire')
      ->with('aculta_pagamentos_mercadopago.subscription_order.7', 30)
      ->willReturn(TRUE);
    $lock->expects($this->once())->method('release')
      ->with('aculta_pagamentos_mercadopago.subscription_order.7');

    try {
      $servico->assinatura((object) [
        'id' => 'pre-9',
        'external_reference' => '7',
        'status' => 'authorized',
        'version' => 1,
      ]);
      $this->fail('Expected a retryable error while subscription creation is unlinked.');
    }
    catch (MercadoPagoApiException $exception) {
      $this->assertSame(503, $exception->codigoHttp());
    }
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
    $lock->expects($this->exactly(2))->method('release');

    $cobranca = $this->cobrancaAprovada();
    $cobranca['transaction_amount'] = '10.001';
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
   * Different recurring charge IDs serialize and reload the shared order.
   */
  public function testDifferentChargesForOneOrderUseSharedLockAndReload(): void {
    [$servico, $pedido, $pagamentos, , $lock, , , $orders] = $this->contexto('assinatura', 'completed', [], []);
    $orders->expects($this->exactly(2))->method('loadUnchanged')->with(7)->willReturn($pedido);
    $lock_ids = [];
    $lock->expects($this->exactly(4))->method('acquire')->willReturnCallback(
      static function (string $lock_id) use (&$lock_ids): bool {
        $lock_ids[] = $lock_id;
        return TRUE;
      },
    );
    $lock->expects($this->exactly(4))->method('release');
    $pagamentos->expects($this->exactly(2))->method('loadByProperties')->willReturn([]);
    $pagamentos->expects($this->exactly(2))->method('create')->willReturn(
      $this->createMock(PaymentInterface::class),
      $this->createMock(PaymentInterface::class),
    );
    $pedido->expects($this->exactly(2))->method('save');

    $first_charge = $this->cobrancaAprovada();
    $second_charge = $this->cobrancaAprovada();
    $second_charge['id'] = 'cob-2';
    $second_charge['payment']['id'] = '556';
    $servico->cobranca($this->gateway(), $first_charge);
    $servico->cobranca($this->gateway(), $second_charge);

    $this->assertSame([
      'aculta_pagamentos_mercadopago.subscription_payment.mercado_pago.555',
      'aculta_pagamentos_mercadopago.subscription_order.7',
      'aculta_pagamentos_mercadopago.subscription_payment.mercado_pago.556',
      'aculta_pagamentos_mercadopago.subscription_order.7',
    ], $lock_ids);
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
