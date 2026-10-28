<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\RecurrenceRequestBuilder;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use MercadoPago\Client\Common\RequestOptions;
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
      public function create(array $payload, ?RequestOptions $options = NULL): object {
        $this->capturado->payload = $payload;
        $this->capturado->options = $options;
        return $this->resposta;
      }

      /**
       * Performs the update operation.
       */
      public function update(string $id, array $payload, ?RequestOptions $options = NULL): object {
        $this->capturado->payload = $payload;
        $this->capturado->id = $id;
        $this->capturado->options = $options;
        return $this->resposta;
      }

    };
  }

  /**
   * Performs the servico operation.
   */
  private function servico(object $cliente, ?LoggerChannelInterface $logger = NULL): MercadoPagoRecurrenceService {
    $fabrica = $this->createMock(MercadoPagoClientFactory::class);
    $fabrica->method('criar')->willReturn($cliente);
    $log = $logger ?? $this->createMock(LoggerChannelInterface::class);
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
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $capturado->options->getCustomHeaders()['x-idempotency-key']);
  }

  /**
   * Plan creation retries reuse the same request idempotency key.
   */
  public function testCreatePlanUsesStableIdempotencyKey(): void {
    $capturado = new \stdClass();
    $resposta = (object) ['id' => 'plan-1', 'init_point' => NULL];
    $servico = $this->servico($this->cliente($resposta, $capturado));
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $dados = [
      'motivo' => 'Apoio mensal',
      'valor' => '30.00',
      'moeda' => 'BRL',
      'frequencia' => 1,
      'back_url' => 'https://retorno.example.invalid/ok',
    ];

    $servico->criarPlano($gateway, $dados);
    $primeira = $capturado->options->getCustomHeaders()['x-idempotency-key'];
    $servico->criarPlano($gateway, $dados);

    $this->assertSame($primeira, $capturado->options->getCustomHeaders()['x-idempotency-key']);
  }

  /**
   * Plan creation fails closed when the provider omits its resource ID.
   */
  public function testCreatePlanRejectsMissingRemoteId(): void {
    $capturado = new \stdClass();
    $servico = $this->servico($this->cliente((object) [], $capturado));

    $this->expectException(MercadoPagoApiException::class);
    $servico->criarPlano($this->createMock(PaymentGatewayInterface::class), [
      'motivo' => 'Apoio mensal',
      'valor' => '30.00',
      'moeda' => 'BRL',
      'frequencia' => 1,
      'back_url' => 'https://retorno.example.invalid/ok',
    ]);
  }

  /**
   * Plan updates use validated payloads, stable keys, and verify response ID.
   */
  public function testUpdatePlanUsesStableKeyAndChecksRemoteId(): void {
    $capturado = new \stdClass();
    $servico = $this->servico($this->cliente((object) ['id' => 'plan-42'], $capturado));
    $resultado = $servico->alterarPlano(
      $this->createMock(PaymentGatewayInterface::class),
      'plan-42',
      [
        'motivo' => 'Apoio mensal atualizado',
        'valor' => '35.00',
        'moeda' => 'BRL',
        'frequencia' => 1,
        'back_url' => 'https://retorno.example.invalid/ok',
      ],
    );

    $this->assertSame('plan-42', $capturado->id);
    $this->assertSame(35.0, $capturado->payload['auto_recurring']['transaction_amount']);
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $capturado->options->getCustomHeaders()['x-idempotency-key']);
    $this->assertSame('plan-42', $resultado->id);
  }

  /**
   * A plan update fails closed when the provider returns another resource ID.
   */
  public function testUpdatePlanRejectsDifferentRemoteId(): void {
    $capturado = new \stdClass();
    $servico = $this->servico($this->cliente((object) ['id' => 'another-plan'], $capturado));

    $this->expectException(MercadoPagoApiException::class);
    $servico->alterarPlano(
      $this->createMock(PaymentGatewayInterface::class),
      'plan-42',
      [
        'motivo' => 'Apoio mensal atualizado',
        'valor' => '35.00',
        'moeda' => 'BRL',
        'frequencia' => 1,
        'back_url' => 'https://retorno.example.invalid/ok',
      ],
    );
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
   * Subscription retries for the same order reuse a deterministic key.
   */
  public function testCreateSubscriptionUsesStableOrderScopedIdempotencyKey(): void {
    $capturado = new \stdClass();
    $resposta = (object) [
      'id' => 'sub-1',
      'init_point' => 'https://www.mercadopago.com.br/authorize',
      'status' => 'pending',
    ];
    $cliente = $this->cliente($resposta, $capturado);
    $dados = [
      'email' => 'buyer@example.invalid',
      'back_url' => 'https://retorno.example.invalid/ok',
      'referencia' => 'commerce-order-42',
      'plano_id' => 'plan-1',
    ];

    $servico = $this->servico($cliente);
    $servico->criarAssinatura($this->createMock(PaymentGatewayInterface::class), $dados);
    $primeira = $capturado->options->getCustomHeaders()['x-idempotency-key'];
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $primeira);

    $servico->criarAssinatura($this->createMock(PaymentGatewayInterface::class), $dados);
    $this->assertSame($primeira, $capturado->options->getCustomHeaders()['x-idempotency-key']);

    $dados['referencia'] = 'commerce-order-43';
    $servico->criarAssinatura($this->createMock(PaymentGatewayInterface::class), $dados);
    $this->assertNotSame($primeira, $capturado->options->getCustomHeaders()['x-idempotency-key']);
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

  /**
   * Normalizes a transport timeout without logging or returning its details.
   */
  public function testTransportFailureIsSafeAndRetryable(): void {
    $cliente = new class {

      /**
       * Simulates a timeout with private request details.
       */
      public function create(array $payload): object {
        throw new \RuntimeException('PRIVATE_ACCESS_TOKEN_AND_URL');
      }

    };
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Falha de transporte ao @operacao no Mercado Pago.', ['@operacao' => 'criar plano']);

    try {
      $this->servico($cliente, $logger)->criarPlano(
        $this->createMock(PaymentGatewayInterface::class),
        [
          'motivo' => 'Apoio mensal',
          'valor' => '30.00',
          'moeda' => 'BRL',
          'frequencia' => 1,
          'back_url' => 'https://retorno.example.invalid/ok',
        ],
      );
      $this->fail('Expected the transport error to be normalized.');
    }
    catch (MercadoPagoApiException $exception) {
      $this->assertSame(0, $exception->codigoHttp());
      $this->assertSame('O Mercado Pago não respondeu como esperado. Tente de novo em instantes.', $exception->getMessage());
      $this->assertStringNotContainsString('PRIVATE_', $exception->getMessage());
      $this->assertSame('PRIVATE_ACCESS_TOKEN_AND_URL', $exception->getPrevious()?->getMessage());
    }
  }

}
