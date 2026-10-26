<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Sales;

use Drupal\aculta_pagamentos_mercadopago\Sales\MercadoPagoPaymentPolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cancelamento e sincronização de pagamentos do Mercado Pago.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoPaymentPolicyTest extends UnitTestCase {

  /**
   * Política de pagamento sob teste.
   *
   * @var \Drupal\aculta_pagamentos_mercadopago\Sales\MercadoPagoPaymentPolicy
   */
  private MercadoPagoPaymentPolicy $politica;

  /**
   * Sets up the test fixture.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->politica = new MercadoPagoPaymentPolicy();
  }

  /**
   * Performs the cancelamentos operation.
   */
  public static function cancelamentos(): array {
    return [
      'pendente em authorization' => ['pending', 'authorization', TRUE],
      'em processo em authorization' => ['in_process', 'authorization', TRUE],
      'autorizado em authorization' => ['authorized', 'authorization', TRUE],
      'aprovado nunca cancela' => ['approved', 'authorization', FALSE],
      'estornado nunca cancela' => ['refunded', 'authorization', FALSE],
      'pendente, mas já completed localmente' => ['pending', 'completed', FALSE],
      'cancelado no MP não cancela de novo' => ['cancelled', 'authorization', FALSE],
    ];
  }

  /**
   * Tests the pode cancelar behavior.
   */
  #[DataProvider('cancelamentos')]
  public function testPodeCancelar(string $remoto, string $local, bool $esperado): void {
    $this->assertSame($esperado, $this->politica->podeCancelar($remoto, $local));
  }

  /**
   * Tests the pode estornar so quando aprovado behavior.
   */
  public function testPodeEstornarSoQuandoAprovado(): void {
    $this->assertTrue($this->politica->podeEstornar('completed'));
    $this->assertTrue($this->politica->podeEstornar('partially_refunded'));
    $this->assertFalse($this->politica->podeEstornar('authorization'));
    $this->assertFalse($this->politica->podeEstornar('refunded'));
    $this->assertFalse($this->politica->podeEstornar('authorization_voided'));
  }

  /**
   * Tests the aprovado em authorization vira completed behavior.
   */
  public function testApprovedAuthorizationBecomesCompleted(): void {
    $r = $this->politica->decidirSincronizacao('approved', 'authorization', '120.00', '0.00');
    $this->assertSame('completed', $r['estado']);
    $this->assertNull($r['estornado']);
  }

  /**
   * Tests the estorno total vira refunded behavior.
   */
  public function testFullRefundBecomesRefunded(): void {
    $r = $this->politica->decidirSincronizacao('refunded', 'completed', '120.00', '120.00');
    $this->assertSame('refunded', $r['estado']);
    $this->assertSame('120.00', $r['estornado']);
  }

  /**
   * Tests the estorno parcial vira partially refunded behavior.
   */
  public function testPartialRefundBecomesPartiallyRefunded(): void {
    $r = $this->politica->decidirSincronizacao('approved', 'completed', '120.00', '30.00');
    $this->assertSame('partially_refunded', $r['estado']);
    $this->assertSame('30.00', $r['estornado']);
  }

  /**
   * Tests the estorno total ja registrado nao muda estado behavior.
   */
  public function testEstornoTotalJaRegistradoNaoMudaEstado(): void {
    $r = $this->politica->decidirSincronizacao('refunded', 'refunded', '120.00', '120.00');
    $this->assertNull($r['estado']);
    $this->assertNull($r['estornado']);
  }

  /**
   * Tests the cancelado ou rejeitado em authorization vira authorization voided behavior.
   */
  public function testCancelledOrRejectedAuthorizationBecomesVoided(): void {
    $this->assertSame('authorization_voided', $this->politica->decidirSincronizacao('cancelled', 'authorization', '50.00', '0.00')['estado']);
    $this->assertSame('authorization_voided', $this->politica->decidirSincronizacao('rejected', 'authorization', '50.00', '0.00')['estado']);
  }

  /**
   * Tests the pendente nao muda estado local behavior.
   */
  public function testPendenteNaoMudaEstadoLocal(): void {
    $r = $this->politica->decidirSincronizacao('in_process', 'authorization', '50.00', '0.00');
    $this->assertNull($r['estado']);
    $this->assertFalse($r['contestacao']);
  }

  /**
   * Tests the chargeback sinaliza sem mudar estado behavior.
   */
  public function testChargebackSinalizaSemMudarEstado(): void {
    $r = $this->politica->decidirSincronizacao('charged_back', 'completed', '120.00', '0.00');
    $this->assertNull($r['estado']);
    $this->assertTrue($r['contestacao']);
  }

  /**
   * Tests the estorno em pagamento nao aprovado eh ignorado behavior.
   */
  public function testEstornoEmPagamentoNaoAprovadoEhIgnorado(): void {
    $r = $this->politica->decidirSincronizacao('refunded', 'authorization', '120.00', '120.00');
    $this->assertNull($r['estado']);
    $this->assertNull($r['estornado']);
  }

  /**
   * Performs the estornos operation.
   */
  public static function estornos(): array {
    return [
      'doacao por pedido' => ['doacao', ['donation'], 'doacao', 'donation', TRUE],
      'doacao por item' => ['default', ['donation'], 'doacao', 'donation', TRUE],
      'curso por pedido' => ['curso', [], 'doacao', 'donation', TRUE],
      'curso por item' => ['default', ['curso'], 'doacao', 'donation', TRUE],
      'compra de loja liberada' => ['default', ['produto'], 'doacao', 'donation', FALSE],
      'sem configuracao de doacao nao bloqueia' => ['default', ['produto'], '', '', FALSE],
      'assinatura por pedido, mesmo com item de outro tipo' => ['assinatura', ['produto'], 'doacao', 'donation', TRUE],
      'assinatura por pedido, sem configuracao de doacao' => ['assinatura', ['produto'], '', '', TRUE],
    ];
  }

  /**
   * Tests the bloqueia estorno de doacao e curso behavior.
   */
  #[DataProvider('estornos')]
  public function testBlocksDonationAndCourseRefunds(string $pedido, array $itens, string $pedidoDoacao, string $itemDoacao, bool $bloqueado): void {
    $this->assertSame($bloqueado, $this->politica->bloqueiaEstorno($pedido, $itens, $pedidoDoacao, $itemDoacao));
  }

  /**
   * Tests the tipos extras so acrescentam bloqueio behavior.
   */
  public function testTiposExtrasSoAcrescentamBloqueio(): void {
    $this->assertTrue($this->politica->bloqueiaEstorno('aula', ['produto'], 'doacao', 'donation', ['aula']));
    $this->assertTrue($this->politica->bloqueiaEstorno('default', ['item_carrinho'], 'doacao', 'donation', [], ['item_carrinho']));
    $this->assertFalse($this->politica->bloqueiaEstorno('default', ['produto'], 'doacao', 'donation', ['aula', ''], ['']));
  }

}
