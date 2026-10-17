<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\MercadoPago;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Grava no pedido de assinatura o que o Mercado Pago informa (webhooks de assinatura).
 *
 * O pedido de assinatura (tipo `assinatura`, workflow order_recurring) agrupa as cobranças. A ligação com o Mercado Pago
 * é o id da assinatura (preapproval), guardado em `data` do pedido, e o `external_reference` da assinatura, que é o id
 * do pedido. Regras:
 * - cobrança só vira pagamento quando o pagamento vinculado está `approved`. O status `processed` da cobrança não basta:
 *   ele também marca parcela que esgotou as tentativas sem aprovação;
 * - cada pagamento aprovado é gravado uma vez (deduplicado pelo id do pagamento no Mercado Pago), porque o webhook
 *   pode ser reenviado;
 * - a primeira cobrança aprovada leva o pedido a `completed` (transição mark_paid); o cancelamento leva a `canceled`;
 * - doações não são estornadas: não há tratamento de estorno aqui.
 *
 * Não grava método de pagamento (o Mercado Pago cobra o cartão da autorização) e não registra e-mail nem dados do pagador.
 */
final class MercadoPagoSubscriptionRecorder {

  /** Chave em `data` do pedido com o id da assinatura no Mercado Pago. */
  public const CHAVE_ASSINATURA = 'mp_preapproval_id';

  /** Chave em `data` do pedido com o último status da assinatura informado pelo Mercado Pago. */
  public const CHAVE_STATUS = 'mp_status';

  /** Tipo de pedido de assinatura de doação. */
  public const TIPO_PEDIDO = 'assinatura';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MercadoPagoRecurrenceService $recorrencia,
    private readonly LoggerChannelInterface $logger,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Atualiza o pedido com o status da assinatura (tópico subscription_preapproval).
   *
   * @param object $assinatura
   *   Resposta da assinatura (status, external_reference).
   */
  public function assinatura(object $assinatura): void {
    $pedido = $this->pedido((string) ($assinatura->external_reference ?? ''));
    if ($pedido === NULL) {
      $this->logger->warning('Assinatura @id sem pedido de doação correspondente.', ['@id' => (string) ($assinatura->id ?? '')]);
      return;
    }
    $status = (string) ($assinatura->status ?? '');
    $pedido->setData(self::CHAVE_STATUS, $status);
    if ($status === 'cancelled') {
      $this->transicionar($pedido, 'cancel');
    }
    $pedido->save();
  }

  /**
   * Grava uma cobrança aprovada como pagamento do pedido, uma vez (tópico subscription_authorized_payment).
   *
   * @param array<string, mixed> $cobranca
   *   Resposta de GET /authorized_payments/{id}.
   */
  public function cobranca(PaymentGatewayInterface $gateway, array $cobranca): void {
    $pagamento = is_array($cobranca['payment'] ?? NULL) ? $cobranca['payment'] : [];
    $remoto = (string) ($pagamento['id'] ?? '');
    if (($pagamento['status'] ?? '') !== 'approved' || $remoto === '') {
      $this->logger->notice('Cobrança @id sem pagamento aprovado (status @status); nada gravado.', [
        '@id' => (string) ($cobranca['id'] ?? ''),
        '@status' => (string) ($cobranca['status'] ?? ''),
      ]);
      return;
    }
    $assinatura = $this->recorrencia->obterAssinatura($gateway, (string) ($cobranca['preapproval_id'] ?? ''));
    $pedido = $this->pedido((string) ($assinatura->external_reference ?? ''));
    if ($pedido === NULL) {
      $this->logger->warning('Cobrança @id sem pedido de doação correspondente.', ['@id' => (string) ($cobranca['id'] ?? '')]);
      return;
    }
    if ($this->pagamentoExiste($pedido, $remoto)) {
      return;
    }
    $pagamento = $this->entidades('commerce_payment')->create([
      'state' => 'completed',
      'amount' => new Price((string) ($cobranca['transaction_amount'] ?? '0'), $pedido->getTotalPrice()->getCurrencyCode()),
      'payment_gateway' => $gateway->id(),
      'order_id' => $pedido->id(),
      'remote_id' => $remoto,
      'remote_state' => 'approved',
      'authorized' => $this->time->getRequestTime(),
    ]);
    $pagamento->save();
    if ($pedido->getState()->getId() === 'needs_payment') {
      $this->transicionar($pedido, 'mark_paid');
    }
    $pedido->save();
  }

  /**
   * Pedido de doação (tipo assinatura) pelo id, ou NULL.
   */
  private function pedido(string $id): ?OrderInterface {
    if ($id === '' || !ctype_digit($id)) {
      return NULL;
    }
    $pedido = $this->entidades('commerce_order')->load((int) $id);
    return $pedido instanceof OrderInterface && $pedido->bundle() === self::TIPO_PEDIDO ? $pedido : NULL;
  }

  /**
   * Aplica a transição se o workflow a permitir a partir do estado atual.
   */
  private function transicionar(OrderInterface $pedido, string $transicao): void {
    $estado = $pedido->getState();
    if (isset($estado->getTransitions()[$transicao])) {
      $estado->applyTransitionById($transicao);
    }
  }

  private function pagamentoExiste(OrderInterface $pedido, string $remoto): bool {
    return $this->entidades('commerce_payment')->loadByProperties([
      'order_id' => $pedido->id(),
      'remote_id' => $remoto,
    ]) !== [];
  }

  private function entidades(string $tipo): \Drupal\Core\Entity\EntityStorageInterface {
    return $this->entityTypeManager->getStorage($tipo);
  }

}
