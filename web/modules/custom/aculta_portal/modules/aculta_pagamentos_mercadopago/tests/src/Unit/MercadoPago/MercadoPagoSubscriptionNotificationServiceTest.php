<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoSubscriptionNotificationService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Consulta de cobrança recorrente (GET /authorized_payments/{id}) com cliente HTTP substituído.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoSubscriptionNotificationServiceTest extends UnitTestCase {

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
   * Cria o serviço com respostas HTTP controladas pelo teste.
   *
   * @param array<int, \GuzzleHttp\Psr7\Response|\Throwable> $respostas
   *   Respostas enfileiradas para o cliente HTTP simulado.
   * @param array<int, array{request: \Psr\Http\Message\RequestInterface}> $historico
   *   Recebe as requisições feitas pelo serviço.
   *
   * @return \Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoSubscriptionNotificationService
   *   Serviço configurado com o cliente HTTP simulado.
   */
  private function servico(array $respostas, array &$historico): MercadoPagoSubscriptionNotificationService {
    $pilha = HandlerStack::create(new MockHandler($respostas));
    $pilha->push(Middleware::history($historico));
    $http = new Client(['handler' => $pilha]);

    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $armazem = $this->createMock(EntityStorageInterface::class);
    $armazem->method('load')->with('mercado_pago')->willReturn($gateway);
    $entidades = $this->createMock(EntityTypeManagerInterface::class);
    $entidades->method('getStorage')->with('commerce_payment_gateway')->willReturn($armazem);

    $fabrica = $this->createMock(MercadoPagoClientFactory::class);
    $fabrica->method('tokenAtivo')->willReturn('token-de-teste-nao-real');

    return new MercadoPagoSubscriptionNotificationService(
      $entidades,
      $fabrica,
      $this->createMock(MercadoPagoRecurrenceService::class),
      $http,
      $this->createMock(LoggerChannelInterface::class),
      new MercadoPagoSubscriptionRecorder(
        $this->createMock(EntityTypeManagerInterface::class),
        $this->createMock(MercadoPagoRecurrenceService::class),
        $this->createMock(LoggerChannelInterface::class),
        $this->createMock(TimeInterface::class),
      ),
    );
  }

  /**
   * Tests the cobranca consulta o endpoint com o token do gateway behavior.
   */
  public function testChargeQueriesEndpointWithGatewayToken(): void {
    $historico = [];
    $servico = $this->servico([new Response(200, [], json_encode(['status' => 'approved']))], $historico);
    $servico->processar('subscription_authorized_payment', 'cob-42');

    $this->assertCount(1, $historico);
    $requisicao = $historico[0]['request'];
    $this->assertSame('GET', $requisicao->getMethod());
    $this->assertSame('https://api.mercadopago.com/authorized_payments/cob-42', (string) $requisicao->getUri());
    $this->assertSame('Bearer token-de-teste-nao-real', $requisicao->getHeaderLine('Authorization'));
  }

  /**
   * Tests the resposta de erro vira excecao segura behavior.
   */
  public function testRespostaDeErroViraExcecaoSegura(): void {
    $historico = [];
    $servico = $this->servico([new Response(404, [], '{"message":"not found"}')], $historico);
    $this->expectException(MercadoPagoApiException::class);
    $servico->processar('subscription_authorized_payment', 'cob-x');
  }

  /**
   * Tests the topico desconhecido eh recusado behavior.
   */
  public function testTopicoDesconhecidoEhRecusado(): void {
    $historico = [];
    $servico = $this->servico([], $historico);
    $this->expectException(\InvalidArgumentException::class);
    $servico->processar('payment', '1');
  }

}
