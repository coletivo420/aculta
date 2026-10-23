<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Sales;

use Drupal\aculta_portal\Sales\MercadoPagoPaymentPolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cancelamento e sincronização de pagamentos do Mercado Pago.
 */
#[Group('aculta_portal')]
final class MercadoPagoPaymentPolicyTest extends UnitTestCase {

  private MercadoPagoPaymentPolicy $politica;

  protected function setUp(): void {
    parent::setUp();
    $this->politica = new MercadoPagoPaymentPolicy();
  }

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

  #[DataProvider('cancelamentos')]
  public function testPodeCancelar(string $remoto, string $local, bool $esperado): void {
    $this->assertSame($esperado, $this->politica->podeCancelar($remoto, $local));
  }

  public function testPodeEstornarSoQuandoAprovado(): void {
    $this->assertTrue($this->politica->podeEstornar('completed'));
    $this->assertTrue($this->politica->podeEstornar('partially_refunded'));
    $this->assertFalse($this->politica->podeEstornar('authorization'));
    $this->assertFalse($this->politica->podeEstornar('refunded'));
    $this->assertFalse($this->politica->podeEstornar('authorization_voided'));
  }

  public function testAprovadoEmAuthorizationVira_completed(): void {
    $r = $this->politica->decidirSincronizacao('approved', 'authorization', '120.00', '0.00');
    $this->assertSame('completed', $r['estado']);
    $this->assertNull($r['estornado']);
  }

  public function testEstornoTotalVira_refunded(): void {
    $r = $this->politica->decidirSincronizacao('refunded', 'completed', '120.00', '120.00');
    $this->assertSame('refunded', $r['estado']);
    $this->assertSame('120.00', $r['estornado']);
  }

  public function testEstornoParcialVira_partially_refunded(): void {
    $r = $this->politica->decidirSincronizacao('approved', 'completed', '120.00', '30.00');
    $this->assertSame('partially_refunded', $r['estado']);
    $this->assertSame('30.00', $r['estornado']);
  }

  public function testEstornoTotalJaRegistradoNaoMudaEstado(): void {
    $r = $this->politica->decidirSincronizacao('refunded', 'refunded', '120.00', '120.00');
    $this->assertNull($r['estado']);
    $this->assertNull($r['estornado']);
  }

  public function testCanceladoOuRejeitadoEmAuthorizationVira_authorization_voided(): void {
    $this->assertSame('authorization_voided', $this->politica->decidirSincronizacao('cancelled', 'authorization', '50.00', '0.00')['estado']);
    $this->assertSame('authorization_voided', $this->politica->decidirSincronizacao('rejected', 'authorization', '50.00', '0.00')['estado']);
  }

  public function testPendenteNaoMudaEstadoLocal(): void {
    $r = $this->politica->decidirSincronizacao('in_process', 'authorization', '50.00', '0.00');
    $this->assertNull($r['estado']);
    $this->assertFalse($r['contestacao']);
  }

  public function testChargebackSinalizaSemMudarEstado(): void {
    $r = $this->politica->decidirSincronizacao('charged_back', 'completed', '120.00', '0.00');
    $this->assertNull($r['estado']);
    $this->assertTrue($r['contestacao']);
  }

  public function testEstornoEmPagamentoNaoAprovadoEhIgnorado(): void {
    $r = $this->politica->decidirSincronizacao('refunded', 'authorization', '120.00', '120.00');
    $this->assertNull($r['estado']);
    $this->assertNull($r['estornado']);
  }


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

  #[DataProvider('estornos')]
  public function testBloqueiaEstornoDeDoacaoECurso(string $pedido, array $itens, string $pedidoDoacao, string $itemDoacao, bool $bloqueado): void {
    $this->assertSame($bloqueado, $this->politica->bloqueiaEstorno($pedido, $itens, $pedidoDoacao, $itemDoacao));
  }

  public function testTiposExtrasSoAcrescentamBloqueio(): void {
    $this->assertTrue($this->politica->bloqueiaEstorno('aula', ['produto'], 'doacao', 'donation', ['aula']));
    $this->assertTrue($this->politica->bloqueiaEstorno('default', ['item_carrinho'], 'doacao', 'donation', [], ['item_carrinho']));
    $this->assertFalse($this->politica->bloqueiaEstorno('default', ['produto'], 'doacao', 'donation', ['aula', ''], ['']));
  }

}
