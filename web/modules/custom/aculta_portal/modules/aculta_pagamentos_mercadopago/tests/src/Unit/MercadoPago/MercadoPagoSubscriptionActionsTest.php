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
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Donor subscription actions and allowed status transitions.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoSubscriptionActionsTest extends UnitTestCase {

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
   * @param int $versao
   *   Última versão remota persistida no pedido.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   Pedido simulado para o teste.
   */
  private function pedido(string $tipo, string $statusMp, string $assinatura, array $transicoes, ?MockObject &$item = NULL, int $versao = 0): MockObject {
    $item = $this->createMock(StateItemInterface::class);
    $item->method('getId')->willReturn('completed');
    $item->method('getTransitions')->willReturn(array_fill_keys($transicoes, new \stdClass()));

    $pedido = $this->createMock(OrderInterface::class);
    $pedido->method('bundle')->willReturn($tipo);
    $pedido->method('id')->willReturn(7);
    $pedido->method('getState')->willReturn($item);
    $pedido->method('getData')->willReturnCallback(
      static fn (string $key, mixed $default = NULL): mixed => match ($key) {
        MercadoPagoSubscriptionRecorder::CHAVE_ASSINATURA => $assinatura,
        MercadoPagoSubscriptionRecorder::CHAVE_STATUS => $statusMp,
        MercadoPagoSubscriptionRecorder::CHAVE_VERSAO => $versao,
        default => $default,
      },
    );
    return $pedido;
  }

  /**
   * Performs the servico operation.
   */
  private function servico(MockObject $recorrencia, ?OrderInterface $pedido = NULL, ?LockBackendInterface $lock = NULL): MercadoPagoSubscriptionRecorder {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    if ($pedido !== NULL) {
      $storage = $this->createMock(EntityStorageInterface::class);
      $storage->method('load')->with($pedido->id())->willReturn($pedido);
      $entityTypeManager->method('getStorage')->with('commerce_order')->willReturn($storage);
    }
    if ($lock === NULL) {
      $lock = $this->createMock(LockBackendInterface::class);
      $lock->method('acquire')->willReturn(TRUE);
    }
    return new MercadoPagoSubscriptionRecorder(
      $entityTypeManager,
      $recorrencia,
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(TimeInterface::class),
      $lock,
    );
  }

  /**
   * Performs the gateway operation.
   */
  private function gateway(): PaymentGatewayInterface {
    return $this->createMock(PaymentGatewayInterface::class);
  }

  /**
   * Expects the verified status and monotonic provider version to be saved.
   */
  private function expectsSubscriptionData(MockObject $pedido, string $status, int $versao): void {
    $pedido->expects($this->exactly(2))->method('setData')->willReturnCallback(
      function (string $key, mixed $value) use ($status, $versao): void {
        if ($key === MercadoPagoSubscriptionRecorder::CHAVE_STATUS) {
          $this->assertSame($status, $value);
          return;
        }
        $this->assertSame(MercadoPagoSubscriptionRecorder::CHAVE_VERSAO, $key);
        $this->assertSame($versao, $value);
      },
    );
  }

  /**
   * Pauses an active subscription and updates its order.
   */
  public function testPauseActiveSubscriptionCallsGatewayAndUpdatesOrder(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-9')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'authorized', 'version' => 1]);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')
      ->with($gateway, 'pre-9', 'paused')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'paused', 'version' => 2]);
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $this->expectsSubscriptionData($pedido, 'paused', 2);
    $pedido->expects($this->once())->method('save');

    $this->servico($recorrencia, $pedido)->alterar($gateway, $pedido, 'pausar');
  }

  /**
   * Tests the reativar uma assinatura pausada volta para ativa behavior.
   */
  public function testReativarUmaAssinaturaPausadaVoltaParaAtiva(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-9')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'paused', 'version' => 1]);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')->with($this->anything(), 'pre-9', 'authorized')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'authorized', 'version' => 2]);
    $pedido = $this->pedido('assinatura', 'paused', 'pre-9', [], $item);
    $this->expectsSubscriptionData($pedido, 'authorized', 2);

    $this->servico($recorrencia, $pedido)->alterar($gateway, $pedido, 'reativar');
  }

  /**
   * Tests the cancelar uma assinatura ativa cancela o pedido behavior.
   */
  public function testCancelActiveSubscriptionAndOrder(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-9')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'authorized', 'version' => 1]);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')->with($this->anything(), 'pre-9', 'canceled')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'canceled', 'version' => 2]);
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', ['cancel'], $item);
    $this->expectsSubscriptionData($pedido, 'canceled', 2);
    $item->expects($this->once())->method('applyTransitionById')->with('cancel');

    $this->servico($recorrencia, $pedido)->alterar($gateway, $pedido, 'cancelar');
  }

  /**
   * Reconciles a successful remote action retried after a local timeout.
   */
  public function testAlreadyAppliedRemoteActionIsReconciledWithoutAnotherUpdate(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-9')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'paused', 'version' => 2]);
    $recorrencia->expects($this->never())->method('alterarStatusAssinatura');
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $this->expectsSubscriptionData($pedido, 'paused', 2);
    $pedido->expects($this->once())->method('save');

    $this->servico($recorrencia, $pedido)->alterar($gateway, $pedido, 'pausar');
  }

  /**
   * Rejects an update response for a different subscription.
   */
  public function testMismatchedUpdateResponseDoesNotChangeOrder(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'authorized', 'version' => 1]);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')
      ->willReturn((object) [
        'id' => 'another-subscription',
        'external_reference' => '7',
        'status' => 'paused',
        'version' => 2,
      ]);
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');

    $this->expectException(MercadoPagoApiException::class);
    $this->servico($recorrencia, $pedido)->alterar($gateway, $pedido, 'pausar');
  }

  /**
   * Does not persist a status the provider did not confirm.
   */
  public function testUnconfirmedUpdateStatusDoesNotChangeOrder(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'authorized', 'version' => 1]);
    $recorrencia->expects($this->once())->method('alterarStatusAssinatura')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'authorized', 'version' => 1]);
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');

    $this->expectException(MercadoPagoApiException::class);
    $this->servico($recorrencia, $pedido)->alterar($gateway, $pedido, 'pausar');
  }

  /**
   * Concurrent actions ask Mercado Pago to retry without making another call.
   */
  public function testLockContentionRejectsTheAction(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->never())->method('obterAssinatura');
    $recorrencia->expects($this->never())->method('alterarStatusAssinatura');
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->willReturn(FALSE);
    $lock->expects($this->never())->method('release');
    $service = $this->servico($recorrencia, $pedido, $lock);

    $this->expectException(MercadoPagoApiException::class);
    $service->alterar($gateway, $pedido, 'pausar');
  }

  /**
   * Rejects a remote subscription linked to another order.
   */
  public function testRemoteSubscriptionMustBelongToOrderBeforeUpdate(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '8', 'status' => 'authorized', 'version' => 1]);
    $recorrencia->expects($this->never())->method('alterarStatusAssinatura');
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');

    $this->expectException(MercadoPagoApiException::class);
    $this->servico($recorrencia, $pedido)->alterar($gateway, $pedido, 'pausar');
  }

  /**
   * A stale action refreshes the order state and does not send another PUT.
   */
  public function testStaleActionReconcilesCurrentRemoteStatus(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-9')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'canceled', 'version' => 2]);
    $recorrencia->expects($this->never())->method('alterarStatusAssinatura');
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', ['cancel'], $item);
    $this->expectsSubscriptionData($pedido, 'canceled', 2);
    $pedido->expects($this->once())->method('save');
    $item->expects($this->once())->method('applyTransitionById')->with('cancel');

    try {
      $this->servico($recorrencia, $pedido)->alterar($gateway, $pedido, 'pausar');
      $this->fail('Expected the stale action to be rejected.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertStringContainsString('mudou', $exception->getMessage());
    }
  }

  /**
   * Refuses to overwrite newer order state with a stale provider read.
   */
  public function testStaleProviderVersionCannotOverwriteOrder(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-9')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'authorized', 'version' => 4]);
    $recorrencia->expects($this->never())->method('alterarStatusAssinatura');
    $pedido = $this->pedido('assinatura', 'paused', 'pre-9', [], $item, 5);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');

    $this->expectException(MercadoPagoApiException::class);
    $this->servico($recorrencia, $pedido)->alterar($gateway, $pedido, 'reativar');
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
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-9')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'authorized', 'version' => 1]);
    $recorrencia->method('alterarStatusAssinatura')->willThrowException(new MercadoPagoApiException('falha', 500));
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', [], $item);
    $pedido->expects($this->never())->method('setData');
    $pedido->expects($this->never())->method('save');

    $this->expectException(MercadoPagoApiException::class);
    $this->servico($recorrencia, $pedido)->alterar($gateway, $pedido, 'pausar');
  }

  /**
   * Keeps provider API details out of the user-facing exception.
   */
  public function testProviderFailureIsWrappedWithSafeMessage(): void {
    $gateway = $this->gateway();
    $recorrencia = $this->createMock(MercadoPagoRecurrenceService::class);
    $recorrencia->expects($this->once())->method('obterAssinatura')->with($gateway, 'pre-9')
      ->willReturn((object) ['id' => 'pre-9', 'external_reference' => '7', 'status' => 'authorized', 'version' => 1]);
    $recorrencia->method('alterarStatusAssinatura')->willThrowException(
      new MercadoPagoApiException('Mensagem segura', 500, new \RuntimeException('RAW API DETAIL')),
    );
    $pedido = $this->pedido('assinatura', 'authorized', 'pre-9', []);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Falha de API ao alterar assinatura do pedido @order (HTTP @status).', [
        '@order' => 7,
        '@status' => 500,
      ]);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(7)->willReturn($pedido);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('commerce_order')->willReturn($storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $service = new MercadoPagoSubscriptionRecorder(
      $entityTypeManager,
      $recorrencia,
      $logger,
      $this->createMock(TimeInterface::class),
      $lock,
    );

    try {
      $service->change($gateway, $pedido, 'pausar');
      $this->fail('Expected the neutral provider exception.');
    }
    catch (PaymentOperationException $exception) {
      $this->assertSame('Mensagem segura', $exception->getMessage());
      $this->assertNull($exception->getPrevious());
    }
  }

}
