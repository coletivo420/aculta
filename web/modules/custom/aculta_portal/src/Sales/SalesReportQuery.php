<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Sales;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Agregações do relatório de vendas, lidas direto das tabelas do Commerce.
 *
 * Não há tabela de relatório paralela: o relatório é consulta sobre commerce_order e commerce_order_item,
 * então estornos e cancelamentos aparecem assim que o pedido ou o pagamento muda. Consultas usam a
 * Database API do Drupal; a sintaxe de data (DATE_FORMAT/FROM_UNIXTIME) é MariaDB, o banco de produção.
 * Portabilidade entre bancos pertence ao projeto DBTNG-2.
 *
 * Vendas consideram pedidos no estado completed, com placed dentro do intervalo. Itens de qualquer tipo
 * (produto e apoio) entram no total, como no relatório anterior.
 */
final class SalesReportQuery {

  /**
   * Formato MariaDB por granularidade, em torno do dia local.
   */
  private const FORMATOS = [
    'dia' => '%Y-%m-%d',
    'semana' => '%x-S%v',
    'mes' => '%Y-%m',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Vendas por variação e moeda, ordenadas pela quantidade.
   *
   * @return array<int, array{variacao_id: int, produto: string, variacao: string, quantidade: string, total: string, moeda: string}>
   */
  public function porVariacao(SalesReportFilters $filtros): array {
    $consulta = $this->base('commerce_order_item', 'i', 'i.order_id', $filtros);
    $consulta->addField('i', 'purchased_entity', 'variacao_id');
    $consulta->addField('i', 'title', 'titulo');
    $consulta->addField('i', 'total_price__currency_code', 'moeda');
    $consulta->addExpression('SUM(i.quantity)', 'quantidade');
    $consulta->addExpression('SUM(i.total_price__number)', 'total');
    $consulta->isNotNull('i.purchased_entity');
    $consulta->groupBy('variacao_id');
    $consulta->groupBy('moeda');
    $consulta->orderBy('quantidade', 'DESC');
    $linhas = $consulta->execute()->fetchAll(\PDO::FETCH_ASSOC);

    // Nome do produto e da variação vêm das entidades, para não depender de joins em tabelas de tradução.
    $variacoes = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->loadMultiple(array_unique(array_map(static fn (array $l): int => (int) $l['variacao_id'], $linhas)));

    $resultado = [];
    foreach ($linhas as $linha) {
      $variacao = $variacoes[$linha['variacao_id']] ?? NULL;
      $produto = $variacao?->getProduct();
      $resultado[] = [
        'variacao_id' => (int) $linha['variacao_id'],
        'produto' => $produto ? (string) $produto->label() : '',
        'variacao' => $variacao ? (string) $variacao->label() : (string) $linha['titulo'],
        'quantidade' => (string) $linha['quantidade'],
        'total' => (string) $linha['total'],
        'moeda' => (string) $linha['moeda'],
      ];
    }
    return $resultado;
  }

  /**
   * Pedidos e receita por período e moeda.
   *
   * @return array<int, array{periodo: string, pedidos: int, total: string, moeda: string}>
   */
  public function porPeriodo(SalesReportFilters $filtros): array {
    $formato = self::FORMATOS[$filtros->granularidade] ?? self::FORMATOS['dia'];
    $consulta = $this->base('commerce_order', 'o', 'o.order_id', $filtros);
    $consulta->addExpression("DATE_FORMAT(FROM_UNIXTIME(o.placed + :deslocamento), :formato)", 'periodo', [
      ':deslocamento' => $filtros->deslocamentoSegundos(),
      ':formato' => $formato,
    ]);
    $consulta->addExpression('COUNT(DISTINCT o.order_id)', 'pedidos');
    $consulta->addExpression('SUM(o.total_price__number)', 'total');
    $consulta->addField('o', 'total_price__currency_code', 'moeda');
    $consulta->groupBy('periodo');
    $consulta->groupBy('moeda');
    $consulta->orderBy('periodo', 'DESC');
    return array_map(static fn (array $l): array => [
      'periodo' => (string) $l['periodo'],
      'pedidos' => (int) $l['pedidos'],
      'total' => (string) $l['total'],
      'moeda' => (string) $l['moeda'],
    ], $consulta->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  /**
   * IDs dos pedidos concluídos no intervalo, do mais recente ao mais antigo, com paginação.
   *
   * @return array{ids: int[], total: int}
   */
  public function pedidos(SalesReportFilters $filtros, int $pagina, int $porPagina): array {
    $consulta = $this->base('commerce_order', 'o', 'o.order_id', $filtros);
    $consulta->addField('o', 'order_id');
    $consulta->orderBy('o.placed', 'DESC');
    $consulta->orderBy('o.order_id', 'DESC');

    $contagem = clone $consulta;
    $contagem->addExpression('COUNT(DISTINCT o.order_id)', 'total');
    $total = (int) $contagem->execute()->fetchField();

    $consulta->range($pagina * $porPagina, $porPagina);
    $ids = array_map('intval', $consulta->execute()->fetchCol());
    return ['ids' => $ids, 'total' => $total];
  }

  /**
   * Consulta base: pedidos completed no intervalo, opcionalmente de uma loja.
   */
  private function base(string $tabela, string $alias, string $chaveJoin, SalesReportFilters $filtros) {
    $consulta = $this->database->select('commerce_order', 'o');
    if ($tabela !== 'commerce_order') {
      $consulta->join($tabela, $alias, "$chaveJoin = o.order_id");
    }
    $consulta->condition('o.state', 'completed');
    [$inicio, $fim] = $filtros->timestamps();
    $consulta->condition('o.placed', [$inicio, $fim], 'BETWEEN');
    if ($filtros->lojaId !== NULL) {
      $consulta->condition('o.store_id', $filtros->lojaId);
    }
    return $consulta;
  }

}
