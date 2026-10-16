<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Sales;

use Drupal\aculta_portal\Sales\SalesPaymentAccess;
use Drupal\aculta_portal\Sales\SalesReportQuery;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regra de acesso às ações de pagamento: escopo do pedido e permissões correspondentes.
 */
#[Group('aculta_portal')]
final class SalesPaymentAccessTest extends UnitTestCase {

  public static function escopos(): array {
    return [
      'só produto' => [['default', 'default'], [SalesReportQuery::ESCOPO_PRODUTO]],
      'só doação' => [['donation'], [SalesReportQuery::ESCOPO_DOACAO]],
      'misto' => [['default', 'donation'], [SalesReportQuery::ESCOPO_PRODUTO, SalesReportQuery::ESCOPO_DOACAO]],
      'sem itens' => [[], []],
    ];
  }

  #[DataProvider('escopos')]
  public function testEscoposDoPedidoSeguemOTipoDoItem(array $tipos, array $esperado): void {
    $this->assertEqualsCanonicalizing($esperado, SalesPaymentAccess::escoposDoPedido($tipos));
  }

  public function testPermissaoPorEscopo(): void {
    $this->assertSame('gerenciar pagamentos de vendas', SalesPaymentAccess::permissaoPara(SalesReportQuery::ESCOPO_PRODUTO));
    $this->assertSame('administrar doacoes', SalesPaymentAccess::permissaoPara(SalesReportQuery::ESCOPO_DOACAO));
  }

  /**
   * @param string[] $permissoes
   */
  private function usuario(array $permissoes): AccountInterface {
    $usuario = $this->createMock(AccountInterface::class);
    $usuario->method('hasPermission')->willReturnCallback(static fn (string $p): bool => in_array($p, $permissoes, TRUE));
    return $usuario;
  }

  public function testGerenteDeLojaAgeEmVendasEDoacoes(): void {
    $acesso = new SalesPaymentAccess($this->usuario(['gerenciar pagamentos de vendas', 'administrar doacoes']));
    $this->assertTrue($acesso->podeAgir([SalesReportQuery::ESCOPO_PRODUTO]));
    $this->assertTrue($acesso->podeAgir([SalesReportQuery::ESCOPO_DOACAO]));
    $this->assertTrue($acesso->podeAgir([SalesReportQuery::ESCOPO_PRODUTO, SalesReportQuery::ESCOPO_DOACAO]));
  }

  public function testPedidoMistoExigeAsDuasPermissoes(): void {
    $acesso = new SalesPaymentAccess($this->usuario(['gerenciar pagamentos de vendas']));
    $this->assertFalse($acesso->podeAgir([SalesReportQuery::ESCOPO_PRODUTO, SalesReportQuery::ESCOPO_DOACAO]));
  }

  public function testSemPermissaoOuSemEscopoNaoAge(): void {
    $this->assertFalse((new SalesPaymentAccess($this->usuario([])))->podeAgir([SalesReportQuery::ESCOPO_PRODUTO]));
    $this->assertFalse((new SalesPaymentAccess($this->usuario(['gerenciar pagamentos de vendas', 'administrar doacoes'])))->podeAgir([]));
  }

}
