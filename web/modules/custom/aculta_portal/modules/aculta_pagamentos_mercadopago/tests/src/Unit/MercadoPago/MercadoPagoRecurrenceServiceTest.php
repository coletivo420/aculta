<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\RecurrenceRequestBuilder;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Testa os payloads e estados de planos e assinaturas.
 *
 * O SDK é substituído por um duplo.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoRecurrenceServiceTest extends UnitTestCase {

  /**
   * Sets up the test fixture.
   */
  protected function setUp(): void {
    parent::setUp();
    // Mensagens traduzidas (TranslatableMarkup) precisam de um container com
    // tradução.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Cliente falso que registra o último payload recebido.
   */
  private function cliente(object $resposta, \stdClass $capturado): object {
    return new class($resposta, $capturado) {

      public function __construct(private object $resposta, private \stdClass $capturado) {}

      /**
       * Performs the create operation.
       */
      public function create(array $payload): object {
        $this->capturado->payload = $payload;
        return $this->resposta;
      }

      /**
       * Performs the update operation.
       */
      public function update(string $id, array $payload): object {
        $this->capturado->payload = $payload;
        $this->capturado->id = $id;
        return $this->resposta;
      }

    };
  }

  /**
   * Performs the servico operation.
   */
  private function servico(object $cliente): MercadoPagoRecurrenceService {
    $fabrica = $this->createMock(MercadoPagoClientFactory::class);
    $fabrica->method('criar')->willReturn($cliente);
    $log = $this->createMock(LoggerChannelInterface::class);
    return new MercadoPagoRecurrenceService($fabrica, new RecurrenceRequestBuilder(), $log);
  }

  /**
   * Tests the criar plano envia payload validado e devolve id e link behavior.
   */
  public function testCreatePlanSendsValidatedPayloadAndReturnsIdAndLink(): void {
    $capturado = new \stdClass();
    $resposta = (object) ['id' => 'plan-1', 'init_point' => 'https://www.mercadopago.com.br/x'];
    $resultado = $this->servico($this->cliente($resposta, $capturado))->criarPlano(
      $this->createMock(PaymentGatewayInterface::class),
      [
        'motivo' => 'Apoio mensal',
        'valor' => '30.00',
        'moeda' => 'BRL',
        'frequencia' => 1,
        'back_url' => 'https://retorno.example.invalid/ok',
      ],
    );
    $this->assertSame(['id' => 'plan-1', 'init_point' => 'https://www.mercadopago.com.br/x'], $resultado);
    $this->assertSame(30.0, $capturado->payload['auto_recurring']['transaction_amount']);
  }

  /**
   * Tests invalid email rejection before the Mercado Pago SDK call.
   */
  public function testCreateSubscriptionRejectsInvalidEmailBeforeSdkCall(): void {
    $capturado = new \stdClass();
    $this->expectException(\InvalidArgumentException::class);
    $this->servico($this->cliente((object) ['id' => 'x'], $capturado))->criarAssinatura(
      $this->createMock(PaymentGatewayInterface::class),
      [
        'email' => 'invalido',
        'back_url' => 'https://retorno.example.invalid/ok',
        'referencia' => 'doacao-1',
        'plano_id' => 'plan-1',
      ],
    );
  }

  /**
   * Tests the pausar assinatura envia so o status behavior.
   */
  public function testPauseSubscriptionSendsOnlyStatus(): void {
    $capturado = new \stdClass();
    $this->servico($this->cliente((object) ['id' => 'sub-1', 'status' => 'paused'], $capturado))
      ->alterarStatusAssinatura($this->createMock(PaymentGatewayInterface::class), 'sub-1', 'paused');
    $this->assertSame(['status' => 'paused'], $capturado->payload);
    $this->assertSame('sub-1', $capturado->id);
  }

  /**
   * Tests the status pending nao pode ser imposto pela aplicacao behavior.
   */
  public function testStatusPendingNaoPodeSerImpostoPelaAplicacao(): void {
    $capturado = new \stdClass();
    $this->expectException(\InvalidArgumentException::class);
    $this->servico($this->cliente((object) ['id' => 'sub-1'], $capturado))
      ->alterarStatusAssinatura($this->createMock(PaymentGatewayInterface::class), 'sub-1', 'pending');
  }

}
