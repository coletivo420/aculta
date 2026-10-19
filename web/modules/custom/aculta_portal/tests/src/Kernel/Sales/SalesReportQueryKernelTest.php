<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Sales;

use Drupal\aculta_portal\Sales\SalesReportFilters;
use Drupal\aculta_portal\Sales\SalesReportQuery;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * SalesReportQuery sobre pedidos reais: escopo de produto e de doação, estado, datas e paginação.
 */
#[Group('aculta_portal')]
final class SalesReportQueryKernelTest extends OrderKernelTestBase {

  private \DateTimeZone $fuso;

  private SalesReportQuery $consultas;

  private ProductVariation $variacao;

  protected function setUp(): void {
    parent::setUp();
    // A base importa só USD; o site opera em BRL.
    $this->container->get('commerce_price.currency_importer')->import('BRL');
    $this->fuso = new \DateTimeZone('America/Sao_Paulo');
    OrderItemType::create(['id' => 'donation', 'label' => 'Doação', 'orderType' => 'default'])->save();

    $produto = Product::create(['type' => 'default', 'title' => 'Camiseta', 'stores' => [$this->store]]);
    $produto->save();
    $this->variacao = ProductVariation::create([
      'type' => 'default',
      'sku' => 'CAM-M',
      'title' => 'M',
      'price' => new Price('20.00', 'BRL'),
      'status' => 1,
      'product_id' => $produto->id(),
    ]);
    $this->variacao->save();

    $this->consultas = new SalesReportQuery($this->container->get('database'), $this->container->get('entity_type.manager'));
  }

  private function filtros(string $de = '2026-10-01', string $ate = '2026-10-16', ?int $loja = NULL): SalesReportFilters {
    $query = ['de' => $de, 'ate' => $ate] + ($loja !== NULL ? ['loja' => (string) $loja] : []);
    return SalesReportFilters::fromQuery($query, $this->fuso, new \DateTimeImmutable('2026-10-16 12:00', $this->fuso));
  }

  /**
   * Cria um pedido com os itens informados. Cada item é [tipo, quantidade, total].
   */
  private function pedido(string $estado, string $data, array $itens, ?int $loja = NULL): Order {
    $pedido = Order::create([
      'type' => 'default',
      'store_id' => $loja ?? $this->store->id(),
      'state' => $estado,
      'placed' => (new \DateTimeImmutable($data, $this->fuso))->getTimestamp(),
      'mail' => 'comprador@example.invalid',
      'uid' => 0,
    ]);
    $pedido->save();
    foreach ($itens as [$tipo, $quantidade, $total]) {
      $item = OrderItem::create([
        'type' => $tipo,
        'quantity' => $quantidade,
        'unit_price' => new Price(bcdiv($total, (string) $quantidade, 2), 'BRL'),
        'total_price' => new Price($total, 'BRL'),
        'purchased_entity' => $tipo === 'default' ? $this->variacao->id() : NULL,
        'order_id' => $pedido->id(),
      ]);
      $item->save();
      $pedido->addItem($item);
    }
    $pedido->save();
    return $pedido;
  }

  public function testVendasDeProdutoIgnoramDoacoesDoMesmoPedido(): void {
    $this->pedido('completed', '2026-10-10 12:00', [['default', 2, '40.00'], ['donation', 1, '15.00']]);

    $linhas = $this->consultas->porVariacao($this->filtros());
    $this->assertCount(1, $linhas);
    $this->assertSame(2.0, (float) $linhas[0]['quantidade']);
    $this->assertSame(40.0, (float) $linhas[0]['total']);
    $this->assertSame('BRL', $linhas[0]['moeda']);
  }

  public function testDoacoesEProdutosSeparamPorEscopo(): void {
    $this->pedido('completed', '2026-10-10 12:00', [['default', 1, '20.00'], ['donation', 1, '15.00']]);

    $produto = $this->consultas->porPeriodo($this->filtros(), SalesReportQuery::ESCOPO_PRODUTO);
    $doacao = $this->consultas->porPeriodo($this->filtros(), SalesReportQuery::ESCOPO_DOACAO);

    $this->assertSame(20.0, (float) $produto[0]['total']);
    $this->assertSame(1, $produto[0]['pedidos']);
    $this->assertSame(15.0, (float) $doacao[0]['total']);
    $this->assertSame(1, $doacao[0]['pedidos']);
  }

  public function testSomentePedidosConcluidosContam(): void {
    $this->pedido('canceled', '2026-10-10 12:00', [['default', 1, '20.00']]);
    $this->pedido('draft', '2026-10-10 12:00', [['default', 1, '20.00']]);
    $this->assertSame([], $this->consultas->porVariacao($this->filtros()));
  }

  public function testPedidoForaDoIntervaloNaoConta(): void {
    $this->pedido('completed', '2026-09-30 23:59', [['default', 1, '20.00']]);
    $this->pedido('completed', '2026-10-17 00:00', [['default', 1, '20.00']]);
    $this->assertSame([], $this->consultas->porPeriodo($this->filtros(), SalesReportQuery::ESCOPO_PRODUTO));
  }

  public function testFiltroDeLojaSeparaAsVendas(): void {
    $outraLoja = $this->store;
    $this->pedido('completed', '2026-10-10 12:00', [['default', 1, '20.00']], (int) $outraLoja->id());
    $this->assertSame([], $this->consultas->porVariacao($this->filtros(loja: 999999)));
    $this->assertCount(1, $this->consultas->porVariacao($this->filtros(loja: (int) $outraLoja->id())));
  }

  public function testPaginacaoDePedidosComProduto(): void {
    for ($i = 1; $i <= 3; $i++) {
      $this->pedido('completed', "2026-10-0$i 12:00", [['default', 1, '20.00']]);
    }
    $this->pedido('completed', '2026-10-09 12:00', [['donation', 1, '5.00']]);

    $pagina = $this->consultas->pedidos($this->filtros(), SalesReportQuery::ESCOPO_PRODUTO, 0, 2);
    $this->assertSame(3, $pagina['total']);
    $this->assertCount(2, $pagina['ids']);
  }

}
