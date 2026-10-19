<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Sales;

use Drupal\Core\Session\AccountInterface;

/**
 * Quem pode agir sobre o pagamento de um pedido (atualizar, cancelar, estornar).
 *
 * A regra segue o tipo de item do pedido: pedido com produto exige a permissão de vendas; pedido com doação
 * exige a permissão de doações; pedido misto exige as duas. Não há permissão para o pedido inteiro.
 */
final class SalesPaymentAccess {

  public const ESCOPO_PRODUTO = SalesReportQuery::ESCOPO_PRODUTO;

  public const ESCOPO_DOACAO = SalesReportQuery::ESCOPO_DOACAO;

  public const PERMISSAO_PRODUTO = 'gerenciar pagamentos de vendas';

  public const PERMISSAO_DOACAO = 'administrar doacoes';

  public function __construct(private readonly AccountInterface $usuario) {}

  /**
   * Escopos presentes nos itens do pedido, a partir dos tipos de item.
   *
   * @param string[] $tiposDosItens
   *
   * @return string[]
   */
  public static function escoposDoPedido(array $tiposDosItens): array {
    $escopos = [];
    foreach ($tiposDosItens as $tipo) {
      $escopos[$tipo === SalesReportQuery::TIPO_DOACAO ? self::ESCOPO_DOACAO : self::ESCOPO_PRODUTO] = TRUE;
    }
    return array_keys($escopos);
  }

  /**
   * Permissão exigida para um escopo.
   */
  public static function permissaoPara(string $escopo): string {
    return $escopo === self::ESCOPO_DOACAO ? self::PERMISSAO_DOACAO : self::PERMISSAO_PRODUTO;
  }

  /**
   * Pode agir sobre um pedido com estes escopos: precisa de todas as permissões correspondentes.
   *
   * @param string[] $escopos
   */
  public function podeAgir(array $escopos): bool {
    if ($escopos === []) {
      return FALSE;
    }
    foreach ($escopos as $escopo) {
      if (!$this->usuario->hasPermission(self::permissaoPara($escopo))) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
