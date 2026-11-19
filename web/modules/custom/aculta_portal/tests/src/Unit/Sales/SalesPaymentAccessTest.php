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

  /**
   * Fornece casos de escopo de pedido a partir dos tipos de item.
   *
   * @return array
   *   Casos de teste com tipos de item e escopos esperados.
   */
  public static function escopos(): array {
    return [
      'só produto' => [['default', 'default'], [SalesReportQuery::ESCOPO_PRODUTO]],
      'só doação' => [['donation'], [SalesReportQuery::ESCOPO_DOACAO]],
      'misto' => [['default', 'donation'], [SalesReportQuery::ESCOPO_PRODUTO, SalesReportQuery::ESCOPO_DOACAO]],
      'sem itens' => [[], []],
    ];
  }

  /**
   * Verifica que os escopos do pedido seguem o tipo de cada item.
   *
   * @param array $tipos
   *   Tipos dos itens do pedido.
   * @param array $esperado
   *   Escopos esperados para o pedido.
   */
  #[DataProvider('escopos')]
  public function testEscoposDoPedidoSeguemOTipoDoItem(array $tipos, array $esperado): void {
    $this->assertEqualsCanonicalizing($esperado, SalesPaymentAccess::escoposDoPedido($tipos));
  }

  /**
   * Verifica a permissão correspondente a cada escopo.
   */
  public function testPermissaoPorEscopo(): void {
    $this->assertSame('gerenciar pagamentos de vendas', SalesPaymentAccess::permissaoPara(SalesReportQuery::ESCOPO_PRODUTO));
    $this->assertSame('administrar doacoes', SalesPaymentAccess::permissaoPara(SalesReportQuery::ESCOPO_DOACAO));
  }

  /**
   * Cria um usuário simulado com as permissões informadas.
   *
   * @param string[] $permissoes
   *   Permissões concedidas ao usuário.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Conta simulada com as permissões informadas.
   */
  private function usuario(array $permissoes): AccountInterface {
    $usuario = $this->createMock(AccountInterface::class);
    $usuario->method('hasPermission')->willReturnCallback(static fn (string $p): bool => in_array($p, $permissoes, TRUE));
    return $usuario;
  }

  /**
   * Verifica que o gerente de loja pode agir em vendas e doações.
   */
  public function testGerenteDeLojaAgeEmVendasEDoacoes(): void {
    $acesso = new SalesPaymentAccess($this->usuario(['gerenciar pagamentos de vendas', 'administrar doacoes']));
    $this->assertTrue($acesso->podeAgir([SalesReportQuery::ESCOPO_PRODUTO]));
    $this->assertTrue($acesso->podeAgir([SalesReportQuery::ESCOPO_DOACAO]));
    $this->assertTrue($acesso->podeAgir([SalesReportQuery::ESCOPO_PRODUTO, SalesReportQuery::ESCOPO_DOACAO]));
  }

  /**
   * Verifica que pedido misto exige as duas permissões.
   */
  public function testPedidoMistoExigeAsDuasPermissoes(): void {
    $acesso = new SalesPaymentAccess($this->usuario(['gerenciar pagamentos de vendas']));
    $this->assertFalse($acesso->podeAgir([SalesReportQuery::ESCOPO_PRODUTO, SalesReportQuery::ESCOPO_DOACAO]));
  }

  /**
   * Verifica que falta de permissão ou de escopo impede a ação.
   */
  public function testSemPermissaoOuSemEscopoNaoAge(): void {
    $this->assertFalse((new SalesPaymentAccess($this->usuario([])))->podeAgir([SalesReportQuery::ESCOPO_PRODUTO]));
    $this->assertFalse((new SalesPaymentAccess($this->usuario(['gerenciar pagamentos de vendas', 'administrar doacoes'])))->podeAgir([]));
  }

}
