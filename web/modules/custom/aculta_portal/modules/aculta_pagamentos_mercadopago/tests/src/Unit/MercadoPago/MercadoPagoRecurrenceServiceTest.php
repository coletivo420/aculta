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
use MercadoPago\Net\MPSearchRequest;
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
      'plan-intent-basic',
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

    $servico->criarPlano($gateway, $dados, 'plan-intent-1');
    $primeira = $capturado->options->getCustomHeaders()['x-idempotency-key'];
    $servico->criarPlano($gateway, $dados, 'plan-intent-1');

    $this->assertSame($primeira, $capturado->options->getCustomHeaders()['x-idempotency-key']);
  }

  /**
   * Identical plan data from distinct create intents must not collide.
   */
  public function testIdenticalPlansUseDistinctIdempotencyKeysForDistinctIntents(): void {
    $capturado = new \stdClass();
    $servico = $this->servico($this->cliente((object) ['id' => 'plan-1'], $capturado));
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $dados = [
      'motivo' => 'Apoio mensal',
      'valor' => '30.00',
      'moeda' => 'BRL',
      'frequencia' => 1,
      'back_url' => 'https://retorno.example.invalid/ok',
    ];

    $servico->criarPlano($gateway, $dados, 'plan-intent-1');
    $primeira = $capturado->options->getCustomHeaders()['x-idempotency-key'];
    $servico->criarPlano($gateway, $dados, 'plan-intent-2');

    $this->assertNotSame($primeira, $capturado->options->getCustomHeaders()['x-idempotency-key']);
  }

  /**
   * Invalid create intent identifiers are rejected before any SDK call.
   */
  public function testCreatePlanRejectsInvalidIntentIdentifier(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->servico(new \stdClass())->criarPlano(
      $this->createMock(PaymentGatewayInterface::class),
      [
        'motivo' => 'Apoio mensal',
        'valor' => '30.00',
        'moeda' => 'BRL',
        'frequencia' => 1,
        'back_url' => 'https://retorno.example.invalid/ok',
      ],
      'not a valid intent',
    );
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
    ], 'plan-intent-invalid-id');
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
   * Plan search validates pagination and every returned remote identifier.
   */
  public function testSearchPlansUsesBoundedPaginationAndValidatesIds(): void {
    $capturado = new \stdClass();
    $cliente = new class($capturado) {

      public function __construct(private \stdClass $capturado) {}

      /**
       * Search plans and capture the request.
       */
      public function search(MPSearchRequest $request): object {
        $this->capturado->request = $request;
        return (object) [
          'results' => [(object) ['id' => 'plan-1'], (object) ['id' => 'plan-2']],
          'paging' => (object) ['total' => 2, 'limit' => 25, 'offset' => 0],
        ];
      }

    };
    $result = $this->servico($cliente)->listarPlanos(
      $this->createMock(PaymentGatewayInterface::class),
      25,
      0,
    );

    $this->assertSame(25, $capturado->request->getLimit());
    $this->assertSame(0, $capturado->request->getOffset());
    $this->assertSame(['plan-1', 'plan-2'], array_map(static fn (object $plan): string => $plan->id, $result['results']));
    $this->assertSame(2, $result['paging']->total);
  }

  /**
   * Invalid plan search pagination fails before calling the provider.
   */
  public function testSearchPlansRejectsInvalidPaginationBeforeProviderCall(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->servico(new \stdClass())->listarPlanos(
      $this->createMock(PaymentGatewayInterface::class),
      0,
      0,
    );
  }

  /**
   * A search response with a malformed plan ID fails closed.
   */
  public function testSearchPlansRejectsMalformedRemotePlanIds(): void {
    $client = new class {

      /**
       * Return an invalid plan identifier.
       */
      public function search(MPSearchRequest $request): object {
        return (object) ['results' => [(object) ['id' => ['invalid']]]];
      }

    };
    $this->expectException(MercadoPagoApiException::class);
    $this->servico($client)->listarPlanos($this->createMock(PaymentGatewayInterface::class));
  }

  /**
   * Duplicate remote plan identifiers fail closed.
   */
  public function testSearchPlansRejectsDuplicateRemoteIds(): void {
    $client = new class {

      /**
       * Return two results with the same identifier.
       */
      public function search(MPSearchRequest $request): object {
        return (object) ['results' => [(object) ['id' => 'plan-1'], (object) ['id' => 'plan-1']]];
      }

    };
    $this->expectException(MercadoPagoApiException::class);
    $this->servico($client)->listarPlanos($this->createMock(PaymentGatewayInterface::class));
  }

  /**
   * A malformed search result collection fails closed.
   */
  public function testSearchPlansRejectsMalformedResultsCollection(): void {
    $client = new class {

      /**
       * Return a result collection with an invalid shape.
       */
      public function search(MPSearchRequest $request): object {
        return (object) ['results' => (object) ['id' => 'plan-1']];
      }

    };
    $this->expectException(MercadoPagoApiException::class);
    $this->servico($client)->listarPlanos($this->createMock(PaymentGatewayInterface::class));
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
   * Malformed provider subscription IDs fail closed without string coercion.
   */
  public function testCreateSubscriptionRejectsMalformedProviderId(): void {
    $capturado = new \stdClass();
    $resposta = (object) [
      'id' => ['unexpected'],
      'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout',
      'status' => 'pending',
    ];
    $servico = $this->servico($this->cliente($resposta, $capturado));

    $this->expectException(MercadoPagoApiException::class);
    $servico->criarAssinatura($this->createMock(PaymentGatewayInterface::class), [
      'email' => 'buyer@example.invalid',
      'back_url' => 'https://retorno.example.invalid/ok',
      'referencia' => 'commerce-order-42',
      'plano_id' => 'plan-1',
    ]);
  }

  /**
   * Pending subscriptions cannot redirect to an origin outside Mercado Pago.
   */
  public function testCreateSubscriptionRejectsForeignAuthorizationOrigin(): void {
    $capturado = new \stdClass();
    $resposta = (object) [
      'id' => 'sub-1',
      'init_point' => 'https://mercadopago.com.br.attacker.invalid/checkout',
      'status' => 'pending',
    ];
    $servico = $this->servico($this->cliente($resposta, $capturado));

    $this->expectException(MercadoPagoApiException::class);
    $servico->criarAssinatura($this->createMock(PaymentGatewayInterface::class), [
      'email' => 'buyer@example.invalid',
      'back_url' => 'https://retorno.example.invalid/ok',
      'referencia' => 'commerce-order-42',
      'plano_id' => 'plan-1',
    ]);
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
        'plan-intent-timeout',
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
