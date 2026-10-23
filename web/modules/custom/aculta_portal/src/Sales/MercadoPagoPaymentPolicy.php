<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Sales;

/**
 * Regras de cancelamento e de sincronização de pagamentos do Mercado Pago.
 *
 * Função pura, sem banco nem SDK, para ser testada em unidade. Os status remotos seguem a documentação do
 * Mercado Pago (query-results e criação de cancelamento: cancelável apenas em pending, in_process e authorized).
 * Os estados locais são os do workflow payment_default do Commerce.
 */
final class MercadoPagoPaymentPolicy {

  /**
   * Status remotos em que o Mercado Pago aceita cancelar o pagamento.
   */
  public const CANCELAVEIS = ['pending', 'in_process', 'authorized'];

  /**
   * Estados locais de um pagamento que foi aprovado e pode ser estornado.
   */
  public const ESTORNAVEIS = ['completed', 'partially_refunded'];

  /**
   * Pode cancelar: o status remoto aceita cancelamento e o pagamento local ainda está em authorization.
   *
   * Pagamentos do Checkout Pro entram como authorization e só viram completed na aprovação, então o
   * cancelamento usa a transição void do Commerce (authorization para authorization_voided).
   */
  public function podeCancelar(string $statusRemoto, string $estadoLocal): bool {
    return in_array($statusRemoto, self::CANCELAVEIS, TRUE) && $estadoLocal === 'authorization';
  }

  /**
   * Tipo de pedido de curso, criado pela inscrição em turma (aculta_lms).
   */
  public const TIPO_CURSO = 'curso';

  /**
   * Tipo de pedido de assinatura (doação mensal), criado por aculta_donation.
   */
  public const TIPO_ASSINATURA = 'assinatura';

  /**
   * Indica se o pedido não pode ser estornado: doação (pedido ou item) e curso não têm estorno.
   *
   * Cancelamento de pagamento pendente é operação separada e não passa por esta regra.
   *
   * @param string $tipoPedido
   *   Bundle do pedido.
   * @param string[] $tiposItens
   *   Bundles dos itens do pedido.
   * @param string $tipoPedidoDoacao
   *   Tipo de pedido de doação configurado em aculta_donation (donate_order_type).
   * @param string $tipoItemDoacao
   *   Tipo de item de doação configurado em aculta_donation (donate_item_type).
   * @param string[] $tiposPedidoExtras
   *   Outros tipos de pedido sem estorno: doação pelo carrinho (cart_order_type) e tipo de pedido de curso configurado.
   * @param string[] $tiposItemExtras
   *   Outros tipos de item sem estorno: doação pelo carrinho (cart_item_type).
   *
   * Local único da regra "doações, assinaturas e cursos não têm estorno". Assinatura e curso são fixos; o restante
   * vem da configuração e só acrescenta bloqueio.
   */
  public function bloqueiaEstorno(string $tipoPedido, array $tiposItens, string $tipoPedidoDoacao, string $tipoItemDoacao, array $tiposPedidoExtras = [], array $tiposItemExtras = []): bool {
    $pedidosSemEstorno = array_filter([self::TIPO_CURSO, self::TIPO_ASSINATURA, $tipoPedidoDoacao, ...$tiposPedidoExtras], static fn(string $t): bool => $t !== '');
    $itensSemEstorno = array_filter([self::TIPO_CURSO, $tipoItemDoacao, ...$tiposItemExtras], static fn(string $t): bool => $t !== '');
    return in_array($tipoPedido, $pedidosSemEstorno, TRUE) || array_intersect($tiposItens, $itensSemEstorno) !== [];
  }

  /**
   * Pode estornar: o pagamento local está aprovado. O estorno em si é do gateway (refundPayment).
   */
  public function podeEstornar(string $estadoLocal): bool {
    return in_array($estadoLocal, self::ESTORNAVEIS, TRUE);
  }

  /**
   * Decide a mudança local a partir do status remoto atual.
   *
   * @param string $statusRemoto
   *   Status do pagamento no Mercado Pago (approved, refunded, cancelled, charged_back...).
   * @param string $estadoLocal
   *   Estado do commerce_payment (authorization, completed, partially_refunded, refunded...).
   * @param string $valor
   *   Valor do pagamento local, com duas casas (ex.: "120.00").
   * @param string $valorEstornado
   *   Valor já estornado segundo o Mercado Pago, com duas casas.
   *
   * @return array{estado: ?string, estornado: ?string, contestacao: bool}
   *   estado: novo estado local, ou NULL para não mudar; estornado: novo valor estornado, ou NULL;
   *   contestacao: TRUE quando há chargeback, que exige tratamento manual.
   */
  public function decidirSincronizacao(string $statusRemoto, string $estadoLocal, string $valor, string $valorEstornado): array {
    $resultado = ['estado' => NULL, 'estornado' => NULL, 'contestacao' => FALSE];

    if ($statusRemoto === 'charged_back') {
      $resultado['contestacao'] = TRUE;
      return $resultado;
    }

    $temEstorno = bccomp($valorEstornado, '0', 2) === 1;
    if ($temEstorno && in_array($estadoLocal, self::ESTORNAVEIS, TRUE)) {
      $resultado['estornado'] = $valorEstornado;
      if (bccomp($valorEstornado, $valor, 2) >= 0) {
        $resultado['estado'] = $estadoLocal === 'refunded' ? NULL : 'refunded';
      }
      else {
        $resultado['estado'] = 'partially_refunded';
      }
      return $resultado;
    }

    if ($statusRemoto === 'approved' && $estadoLocal === 'authorization') {
      $resultado['estado'] = 'completed';
      return $resultado;
    }

    if (in_array($statusRemoto, ['cancelled', 'rejected'], TRUE) && $estadoLocal === 'authorization') {
      $resultado['estado'] = 'authorization_voided';
    }

    return $resultado;
  }

}
