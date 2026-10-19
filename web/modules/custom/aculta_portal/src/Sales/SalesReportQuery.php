<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Sales;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Agregações do relatório de vendas e de doações, lidas direto das tabelas do Commerce.
 *
 * Não há tabela de relatório paralela: o relatório é consulta sobre commerce_order e commerce_order_item,
 * então estornos e cancelamentos aparecem assim que o pedido ou o pagamento muda. Consultas usam a
 * Database API do Drupal; a sintaxe de data (DATE_FORMAT/FROM_UNIXTIME) é MariaDB, o banco de produção.
 * Portabilidade entre bancos pertence ao projeto DBTNG-2.
 *
 * Escopos (cada consulta recebe um deles, e nunca os dois juntos):
 * - ESCOPO_PRODUTO: itens que não são doação. Um pedido entra quando tem ao menos um item de produto.
 * - ESCOPO_DOACAO: itens do tipo donation (commerce_order.commerce_order_item_type.donation). Um pedido
 *   entra quando tem ao menos um item de doação.
 * Um pedido que mistura produto e doação aparece nos dois relatórios, cada um com o valor do seu escopo.
 */
class SalesReportQuery {

  public const ESCOPO_PRODUTO = 'produto';

  public const ESCOPO_DOACAO = 'doacao';

  /**
   * Tipo de item de pedido que representa doação (config commerce_order.commerce_order_item_type.donation).
   */
  public const TIPO_DOACAO = 'donation';

  /**
   * Formato MariaDB por granularidade.
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
   * Vendas de produto por variação e moeda, ordenadas pela quantidade.
   *
   * @return array<int, array{variacao_id: int, produto: string, variacao: string, quantidade: string, total: string, moeda: string}>
   */
  public function porVariacao(SalesReportFilters $filtros): array {
    $consulta = $this->itens($filtros, self::ESCOPO_PRODUTO);
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
   * Pedidos e valor por período e moeda, para o escopo informado.
   *
   * @return array<int, array{periodo: string, pedidos: int, total: string, moeda: string}>
   */
  public function porPeriodo(SalesReportFilters $filtros, string $escopo = self::ESCOPO_PRODUTO): array {
    $formato = self::FORMATOS[$filtros->granularidade] ?? self::FORMATOS['dia'];
    $consulta = $this->itens($filtros, $escopo);
    $consulta->addExpression("DATE_FORMAT(FROM_UNIXTIME(o.placed + :deslocamento), :formato)", 'periodo', [
      ':deslocamento' => $filtros->deslocamentoSegundos(),
      ':formato' => $formato,
    ]);
    $consulta->addExpression('COUNT(DISTINCT o.order_id)', 'pedidos');
    $consulta->addExpression('SUM(i.total_price__number)', 'total');
    $consulta->addField('i', 'total_price__currency_code', 'moeda');
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
   * IDs dos pedidos com itens do escopo no intervalo, do mais recente ao mais antigo, com paginação.
   *
   * @return array{ids: int[], total: int}
   */
  public function pedidos(SalesReportFilters $filtros, string $escopo, int $pagina, int $porPagina): array {
    // A contagem é uma consulta separada: fetchField() lê a primeira coluna, então não pode herdar o campo de id.
    $contagem = $this->pedidosDoEscopo($filtros, $escopo);
    $contagem->addExpression('COUNT(DISTINCT o.order_id)', 'total');
    $total = (int) $contagem->execute()->fetchField();

    $consulta = $this->pedidosDoEscopo($filtros, $escopo);
    $consulta->addField('o', 'order_id');
    $consulta->orderBy('o.placed', 'DESC');
    $consulta->orderBy('o.order_id', 'DESC');

    $consulta->range($pagina * $porPagina, $porPagina);
    $ids = array_map('intval', $consulta->execute()->fetchCol());
    return ['ids' => $ids, 'total' => $total];
  }

  /**
   * Itens do escopo de pedidos concluídos no intervalo (junção pedido e item).
   */
  private function itens(SalesReportFilters $filtros, string $escopo): SelectInterface {
    $consulta = $this->base('o', $filtros);
    $consulta->join('commerce_order_item', 'i', 'i.order_id = o.order_id');
    $this->condicaoDeEscopo($consulta, 'i.type', $escopo);
    return $consulta;
  }

  /**
   * Pedidos concluídos no intervalo com ao menos um item do escopo.
   */
  private function pedidosDoEscopo(SalesReportFilters $filtros, string $escopo): SelectInterface {
    $consulta = $this->base('o', $filtros);
    $comItem = $this->database->select('commerce_order_item', 'x');
    $comItem->addField('x', 'order_id');
    $this->condicaoDeEscopo($comItem, 'x.type', $escopo);
    $consulta->condition('o.order_id', $comItem, 'IN');
    return $consulta;
  }

  /**
   * Aplica o escopo sobre a coluna de tipo do item.
   */
  private function condicaoDeEscopo(SelectInterface $consulta, string $coluna, string $escopo): void {
    match ($escopo) {
      self::ESCOPO_PRODUTO => $consulta->condition($coluna, self::TIPO_DOACAO, '<>'),
      self::ESCOPO_DOACAO => $consulta->condition($coluna, self::TIPO_DOACAO),
      default => throw new \InvalidArgumentException("Escopo de relatório desconhecido: $escopo."),
    };
  }

  /**
   * Base: pedidos completed no intervalo, opcionalmente de uma loja.
   */
  private function base(string $alias, SalesReportFilters $filtros): SelectInterface {
    $consulta = $this->database->select('commerce_order', $alias);
    $consulta->condition("$alias.state", 'completed');
    [$inicio, $fim] = $filtros->timestamps();
    $consulta->condition("$alias.placed", [$inicio, $fim], 'BETWEEN');
    if ($filtros->lojaId !== NULL) {
      $consulta->condition("$alias.store_id", $filtros->lojaId);
    }
    return $consulta;
  }

}
