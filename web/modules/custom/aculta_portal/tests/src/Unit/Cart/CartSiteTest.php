<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Cart;

use Drupal\aculta_portal\Cart\CartSite;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Caracteriza o agrupamento do carrinho único por separador (P-CART A).
 *
 * Registra o comportamento atual do carrinho da barra multidomínio; não define comportamento novo.
 */
#[Group('aculta_portal')]
final class CartSiteTest extends UnitTestCase {

  #[DataProvider('tiposDePedido')]
  public function testTipoDePedidoVaiParaOSeparador(string $tipo, string $esperado): void {
    $this->assertSame($esperado, CartSite::paraTipo($tipo));
  }

  public static function tiposDePedido(): array {
    return [
      'padrão da loja' => ['default', CartSite::LOJA],
      'curso' => ['curso', CartSite::CURSOS],
      'doação' => ['doacao', CartSite::APOIO],
      'assinatura' => ['assinatura', CartSite::ASSINATURA],
      'tipo desconhecido cai na loja' => ['qualquer_outro', CartSite::LOJA],
      'vazio cai na loja' => ['', CartSite::LOJA],
    ];
  }

  public function testOrdemDosSeparadores(): void {
    $this->assertSame(['loja', 'cursos', 'apoio', 'assinatura'], CartSite::ORDEM);
  }

  public function testRotulosVisiveisDoPainel(): void {
    $this->assertSame('Loja', CartSite::rotulo(CartSite::LOJA));
    $this->assertSame('Cursos', CartSite::rotulo(CartSite::CURSOS));
    $this->assertSame('Apoio', CartSite::rotulo(CartSite::APOIO));
    $this->assertSame('Assinatura', CartSite::rotulo(CartSite::ASSINATURA));
  }

  public function testTodoSeparadorDaOrdemTemRotulo(): void {
    foreach (CartSite::ORDEM as $site) {
      $this->assertNotSame('', CartSite::rotulo($site));
    }
  }

}
