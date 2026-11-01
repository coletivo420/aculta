<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Sales;

use Drupal\aculta_portal\Contract\RefundPolicyInterface;

/**
 * Handles local payment cancellation and reconciliation policy.
 *
 * Função pura, sem banco nem SDK, para testes unitários. Os status remotos
 * seguem a documentação do Mercado Pago: pending, in_process e authorized
 * aceitam cancelamento.
 * Os estados locais seguem os workflows do Commerce. Pagamentos novos usam o
 * workflow do Mercado Pago, que acrescenta o estado pending.
 */
final class MercadoPagoPaymentPolicy implements RefundPolicyInterface {

  /**
   * Status remotos em que o Mercado Pago aceita cancelar o pagamento.
   */
  public const CANCELAVEIS = ['pending', 'in_process', 'authorized'];

  /**
   * Estados locais de um pagamento que foi aprovado e pode ser estornado.
   */
  public const ESTORNAVEIS = ['completed', 'partially_refunded'];

  /**
   * Checks whether the payment can be cancelled.
   *
   * O status remoto precisa permitir cancelamento e o pagamento local precisa
   * estar em authorization ou pending.
   *
   * O cancelamento usa a transição void do workflow Commerce selecionado pelo
   * gateway (authorization/pending para authorization_voided).
   */
  public function podeCancelar(string $statusRemoto, string $estadoLocal): bool {
    return in_array($statusRemoto, self::CANCELAVEIS, TRUE) && in_array($estadoLocal, ['authorization', 'pending'], TRUE);
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
   * Checks whether the order is excluded from refunds.
   *
   * Doações (pedido ou item) e cursos não podem ser estornados.
   *
   * Cancelamento de pagamento pendente é operação separada e não passa por esta
   * regra.
   *
   * @param string $tipoPedido
   *   Bundle do pedido.
   * @param string[] $tiposItens
   *   Bundles dos itens do pedido.
   * @param string $tipoPedidoDoacao
   *   Tipo de pedido de doação configurado em aculta_donation
   *   (donate_order_type).
   * @param string $tipoItemDoacao
   *   Tipo de item de doação configurado em aculta_donation (donate_item_type).
   * @param string[] $tiposPedidoExtras
   *   Tipos sem estorno: doação no carrinho (cart_order_type) e curso.
   * @param string[] $tiposItemExtras
   *   Outros tipos de item sem estorno: doação pelo carrinho (cart_item_type).
   *
   *
   *   Esta é a regra única para doações, assinaturas e cursos sem estorno.
   *   Assinatura e curso são fixos; os demais tipos vêm da configuração.
   */
  public function bloqueiaEstorno(string $tipoPedido, array $tiposItens, string $tipoPedidoDoacao, string $tipoItemDoacao, array $tiposPedidoExtras = [], array $tiposItemExtras = []): bool {
    $pedidosSemEstorno = array_filter(
      [
        self::TIPO_CURSO,
        self::TIPO_ASSINATURA,
        $tipoPedidoDoacao,
        ...$tiposPedidoExtras,
      ],
      static fn (string $t): bool => $t !== '',
    );
    $itensSemEstorno = array_filter([self::TIPO_CURSO, $tipoItemDoacao, ...$tiposItemExtras], static fn (string $t): bool => $t !== '');
    return in_array($tipoPedido, $pedidosSemEstorno, TRUE) || array_intersect($tiposItens, $itensSemEstorno) !== [];
  }

  /**
   * {@inheritdoc}
   */
  public function isRefundable(string $paymentState): bool {
    return $this->podeEstornar($paymentState);
  }

  /**
   * Checks whether the local payment state allows a refund.
   *
   * O gateway executa o estorno (refundPayment).
   */
  public function podeEstornar(string $estadoLocal): bool {
    return in_array($estadoLocal, self::ESTORNAVEIS, TRUE);
  }

  /**
   * Decide a mudança local a partir do status remoto atual.
   *
   * @param string $statusRemoto
   *   Status do pagamento no Mercado Pago (approved, refunded, cancelled,
   *   charged_back...).
   * @param string $estadoLocal
   *   Estado do commerce_payment (authorization, completed, partially_refunded,
   *   refunded...).
   * @param string $valor
   *   Valor do pagamento local, na precisão da moeda (ex.: "120.00").
   * @param string $valorEstornado
   *   Valor já estornado segundo o Mercado Pago, na precisão da moeda.
   * @param int $precision
   *   ISO fraction digits for the payment currency.
   *
   * @return array{estado: ?string, estornado: ?string, contestacao: bool}
   *   estado: novo estado local, ou NULL para não mudar; estornado: novo valor
   *   estornado, ou NULL; contestacao: TRUE quando há chargeback, que exige
   *   tratamento manual.
   */
  public function decidirSincronizacao(string $statusRemoto, string $estadoLocal, string $valor, string $valorEstornado, int $precision = 2): array {
    $resultado = ['estado' => NULL, 'estornado' => NULL, 'contestacao' => FALSE];

    if ($statusRemoto === 'charged_back') {
      $resultado['contestacao'] = TRUE;
      return $resultado;
    }

    $temEstorno = bccomp($valorEstornado, '0', $precision) === 1;
    if ($temEstorno && in_array($estadoLocal, self::ESTORNAVEIS, TRUE)) {
      $resultado['estornado'] = $valorEstornado;
      if (bccomp($valorEstornado, $valor, $precision) >= 0) {
        $resultado['estado'] = $estadoLocal === 'refunded' ? NULL : 'refunded';
      }
      else {
        $resultado['estado'] = 'partially_refunded';
      }
      return $resultado;
    }

    if (in_array($statusRemoto, ['pending', 'in_process', 'authorized'], TRUE)
      && in_array($estadoLocal, ['new', 'authorization'], TRUE)) {
      $resultado['estado'] = 'pending';
      return $resultado;
    }

    if ($statusRemoto === 'approved' && in_array($estadoLocal, ['new', 'authorization', 'pending'], TRUE)) {
      $resultado['estado'] = 'completed';
      return $resultado;
    }

    if (in_array($statusRemoto, ['cancelled', 'rejected'], TRUE) && in_array($estadoLocal, ['authorization', 'pending'], TRUE)) {
      $resultado['estado'] = 'authorization_voided';
    }

    return $resultado;
  }

}
