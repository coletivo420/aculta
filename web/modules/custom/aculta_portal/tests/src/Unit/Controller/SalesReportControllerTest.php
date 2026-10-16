<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Controller;

use Drupal\aculta_portal\Controller\SalesReportController;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\commerce_price\Price;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Agregação do relatório de vendas: variações somadas por pedido concluído, totais por moeda.
 */
#[Group('aculta_portal')]
class SalesReportControllerTest extends UnitTestCase {

  /**
   * Item de pedido: variação (ou NULL), produto, quantidade e preço total.
   */
  private function item(?int $variacaoId, string $produto, string $variacao, int $qtd, ?string $numero, string $moeda): object {
    $produtoMock = $this->createMock(\Drupal\commerce_product\Entity\ProductInterface::class);
    $produtoMock->method('label')->willReturn($produto);
    $variacaoMock = $this->createMock(\Drupal\commerce_product\Entity\ProductVariationInterface::class);
    $variacaoMock->method('id')->willReturn($variacaoId);
    $variacaoMock->method('label')->willReturn($variacao);
    $variacaoMock->method('getProduct')->willReturn($produtoMock);
    $preco = $numero === NULL ? NULL : new Price($numero, $moeda);
    $linha = $this->createMock(\Drupal\commerce_order\Entity\OrderItemInterface::class);
    $linha->method('getPurchasedEntity')->willReturn($variacaoId === NULL ? NULL : $variacaoMock);
    $linha->method('getQuantity')->willReturn((string) $qtd);
    $linha->method('getTotalPrice')->willReturn($preco);
    return $linha;
  }

  /**
   * Pedido com os itens informados.
   */
  private function pedido(array $itens) {
    $pedido = $this->createMock(\Drupal\commerce_order\Entity\OrderInterface::class);
    $pedido->method('getItems')->willReturn($itens);
    return $pedido;
  }

  /**
   * Controller com storage de pedidos que devolve os pedidos informados.
   */
  private function controller(array $pedidos): SalesReportController {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn(array_keys($pedidos));
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn($pedidos);
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('commerce_order')->willReturn($storage);
    $traducao = $this->createMock(TranslationInterface::class);
    $traducao->method('translateString')->willReturnCallback(static fn ($markup) => $markup->getUntranslatedString());
    return new SalesReportController($etm, $traducao);
  }

  /**
   * Soma a mesma variação entre pedidos e separa os totais por moeda.
   */
  public function testSomaVariacoesEntrePedidosEPorMoeda(): void {
    $pedidos = [
      1 => $this->pedido([
        $this->item(10, 'Camiseta', 'M', 2, '80.00', 'BRL'),
        $this->item(11, 'Adesivo', 'Padrão', 1, '5.50', 'BRL'),
      ]),
      2 => $this->pedido([
        $this->item(10, 'Camiseta', 'M', 3, '120.00', 'BRL'),
        $this->item(12, 'Cartaz', 'Padrão', 1, '10.00', 'USD'),
      ]),
    ];
    $render = $this->controller($pedidos)->report();
    $linhas = $render['#rows'];
    // Camiseta M: 5 unidades, 200,00 BRL (soma dos dois pedidos).
    $this->assertSame(['Camiseta', 'M', 5, '200,00 BRL'], $linhas[0]);
    $this->assertSame(['Adesivo', 'Padrão', 1, '5,50 BRL'], $linhas[1]);
    $this->assertSame(['Cartaz', 'Padrão', 1, '10,00 USD'], $linhas[2]);
    // Totais por moeda no rodapé.
    $this->assertSame(['', '', 'Total', '205,50 BRL'], array_map('strval', $linhas[3]));
    $this->assertSame(['', '', 'Total', '10,00 USD'], array_map('strval', $linhas[4]));
  }

  /**
   * Itens sem variação ou sem preço são ignorados; sem pedidos, mostra vazio.
   */
  public function testIgnoraItensIncompletosEMostraVazio(): void {
    $pedidos = [1 => $this->pedido([$this->item(NULL, 'X', 'Y', 1, '9.00', 'BRL'), $this->item(10, 'Z', 'W', 1, NULL, 'BRL')])];
    $render = $this->controller($pedidos)->report();
    $this->assertSame([], $render['#rows']);

    $vazio = $this->controller([])->report();
    $this->assertSame([], $vazio['#rows']);
    $this->assertArrayHasKey('#empty', $vazio);
  }

}
