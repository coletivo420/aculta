<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\MercadoPago;

use Drupal\aculta_portal\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_portal\MercadoPago\MercadoPagoPaymentService;
use Drupal\aculta_portal\Sales\MercadoPagoPaymentPolicy;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Atualização e cancelamento de pagamento, com o cliente do SDK substituído por um duplo.
 */
#[Group('aculta_portal')]
final class MercadoPagoPaymentServiceTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    // Mensagens traduzidas (TranslatableMarkup) precisam de um container com tradução.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Cliente falso do SDK: devolve o pagamento remoto informado e registra o cancelamento.
   */
  private function cliente(object $remoto, ?object &$registro = NULL): object {
    $registro = new \stdClass();
    $registro->cancelado = FALSE;
    return new class($remoto, $registro) {
      public function __construct(private object $remoto, private object $registro) {}
      public function get(int $id): object { return $this->remoto; }
      public function cancel(int $id): object {
        $this->registro->cancelado = TRUE;
        return (object) ['status' => 'cancelled'];
      }
    };
  }

  private function pagamento(string $estado, string $remoteId = '123456789'): PaymentInterface&\PHPUnit\Framework\MockObject\MockObject {
    $pagamento = $this->createMock(PaymentInterface::class);
    $pagamento->method('getRemoteId')->willReturn($remoteId);
    $pagamento->method('getAmount')->willReturn(new Price('120.00', 'BRL'));
    $pagamento->method('getPaymentGateway')->willReturn($this->createMock(PaymentGatewayInterface::class));
    $etapa = new class($estado) {
      public function __construct(private string $id) {}
      public function getId(): string { return $this->id; }
    };
    $pagamento->method('getState')->willReturn($etapa);
    return $pagamento;
  }

  private function servico(object $cliente): MercadoPagoPaymentService {
    $fabrica = $this->createMock(MercadoPagoClientFactory::class);
    $fabrica->method('criar')->willReturn($cliente);
    $log = $this->createMock(LoggerChannelInterface::class);
    return new MercadoPagoPaymentService($fabrica, new MercadoPagoPaymentPolicy(), $log);
  }

  public function testAprovadoEmAuthorizationViraCompletedENaoCancela(): void {
    $pagamento = $this->pagamento('authorization');
    $pagamento->expects($this->once())->method('setState')->with('completed');
    $pagamento->expects($this->once())->method('save');
    $status = $this->servico($this->cliente((object) ['status' => 'approved', 'transaction_amount_refunded' => 0]))->atualizar($pagamento);
    $this->assertSame('approved', $status);
  }

  public function testEstornoTotalRegistraValorEstornadoEEstado(): void {
    $pagamento = $this->pagamento('completed');
    $pagamento->expects($this->once())->method('setRefundedAmount')->with($this->callback(
      static fn (Price $p): bool => $p->getNumber() === '120.00' && $p->getCurrencyCode() === 'BRL'
    ));
    $pagamento->expects($this->once())->method('setState')->with('refunded');
    $this->servico($this->cliente((object) ['status' => 'refunded', 'transaction_amount_refunded' => 120.0]))->atualizar($pagamento);
  }

  public function testCancelarPendenteUsaVoidELiberaAAuthorization(): void {
    $pagamento = $this->pagamento('authorization');
    $pagamento->expects($this->once())->method('setState')->with('authorization_voided');
    $registro = NULL;
    $cliente = $this->cliente((object) ['status' => 'pending'], $registro);
    $this->servico($cliente)->cancelar($pagamento);
    $this->assertTrue($registro->cancelado);
  }

  public function testCancelarAprovadoEhRecusadoSemChamarOSdk(): void {
    $pagamento = $this->pagamento('completed');
    $registro = NULL;
    $cliente = $this->cliente((object) ['status' => 'approved'], $registro);
    $this->expectException(\DomainException::class);
    try {
      $this->servico($cliente)->cancelar($pagamento);
    }
    finally {
      $this->assertFalse($registro->cancelado);
    }
  }

  public function testPagamentoSemIdRemotoEhRecusado(): void {
    $this->expectException(\DomainException::class);
    $this->servico($this->cliente((object) ['status' => 'approved']))->atualizar($this->pagamento('completed', 'abc'));
  }

}
