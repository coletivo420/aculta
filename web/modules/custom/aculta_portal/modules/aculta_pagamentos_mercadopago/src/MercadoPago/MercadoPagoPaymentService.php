<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\Sales\MercadoPagoPaymentPolicy;
use Drupal\aculta_portal\Contract\PaymentOperationsInterface;
use Drupal\aculta_portal\Exception\PaymentOperationException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use MercadoPago\Exceptions\MPApiException;

/**
 * Atualiza e cancela pagamentos do Checkout Pro pela API do Mercado Pago.
 *
 * O estorno não é feito aqui: é a operação refund do Commerce, que o gateway
 * do módulo implementa (refundPayment). Este serviço cobre o que o Commerce não
 * oferece: consultar o status atual, cancelar pagamento pendente e espelhar no
 * pagamento local o que o Mercado Pago informa.
 */
final class MercadoPagoPaymentService implements PaymentOperationsInterface {

  public function __construct(
    private readonly MercadoPagoClientFactory $clientes,
    private readonly MercadoPagoPaymentPolicy $politica,
    private readonly LoggerChannelInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LockBackendInterface $lock,
  ) {}

  /**
   * Consulta o pagamento no Mercado Pago e espelha o status no pagamento local.
   *
   * @return string
   *   Status remoto consultado (approved, refunded, in_process...).
   *
   * @throws \Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException
   */
  public function atualizar(PaymentInterface $pagamento): string {
    $remote_id = $this->remoteId($pagamento);
    $lock_id = 'aculta_pagamentos_mercadopago.payment.' . $remote_id;
    if (!$this->lock->acquire($lock_id, 300)) {
      throw new MercadoPagoApiException('Outra operação deste pagamento está em andamento; tente novamente.', 409);
    }
    try {
      $locked_payment = $this->reload($pagamento);
      $remoto = $this->consultar($locked_payment, $remote_id);
      $status = (string) ($remoto->status ?? '');
      $valor = $locked_payment->getAmount();
      if ($valor === NULL) {
        throw new MercadoPagoApiException('Os dados do pagamento remoto não correspondem ao pagamento local.');
      }
      $precision = MercadoPagoAmount::currencyPrecision($valor->getCurrencyCode());
      try {
        $remote_amount = MercadoPagoAmount::normalize($remoto->transaction_amount ?? NULL, $precision);
        $local_amount = MercadoPagoAmount::normalize($valor->getNumber(), $precision);
      }
      catch (MercadoPagoApiException $exception) {
        throw new MercadoPagoApiException($exception->getMessage(), 0, $exception);
      }
      if ($remote_amount !== $local_amount
        || ($remoto->currency_id ?? NULL) !== $valor->getCurrencyCode()) {
        throw new MercadoPagoApiException('Os dados do pagamento remoto não correspondem ao pagamento local.');
      }
      try {
        $estornado_remoto = MercadoPagoAmount::normalize($remoto->transaction_amount_refunded ?? 0, $precision);
      }
      catch (MercadoPagoApiException $exception) {
        throw new MercadoPagoApiException('O valor de estorno informado pelo Mercado Pago é inválido.', 0, $exception);
      }
      if (bccomp($estornado_remoto, '0', $precision) < 0 || bccomp($estornado_remoto, $local_amount, $precision) > 0) {
        throw new MercadoPagoApiException('O valor de estorno informado pelo Mercado Pago é inválido.');
      }
      if ($status === 'refunded' && bccomp($estornado_remoto, '0', $precision) <= 0) {
        throw new MercadoPagoApiException('O Mercado Pago não informou o total estornado.');
      }
      $decisao = $this->politica->decidirSincronizacao(
        $status,
        $locked_payment->getState()->getId(),
        $local_amount,
        $estornado_remoto,
        $precision,
      );

      $locked_payment->setRemoteState($status);
      if ($decisao['estornado'] !== NULL) {
        $locked_payment->setRefundedAmount(new Price($decisao['estornado'], $valor->getCurrencyCode()));
      }
      if ($decisao['estado'] !== NULL) {
        $locked_payment->setState($decisao['estado']);
      }
      $locked_payment->save();

      if ($decisao['contestacao']) {
        $this->logger->warning('Pagamento @id com contestação (chargeback) no Mercado Pago. Tratar manualmente.', ['@id' => $remote_id]);
      }
      $this->logger->info('Pagamento @id atualizado a partir do Mercado Pago: status remoto @status.', [
        '@id' => $remote_id,
        '@status' => $status,
      ]);
      return $status;
    }
    finally {
      $this->lock->release($lock_id);
    }
  }

  /**
   * Cancela um pagamento pendente no Mercado Pago.
   *
   * Confere o status remoto antes de cancelar, porque o pagamento local pode
   * estar desatualizado.
   *
   * @throws \DomainException
   *   Pagamento que não pode ser cancelado no estado atual.
   * @throws \Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException
   */
  public function cancelar(PaymentInterface $pagamento): void {
    $remote_id = $this->remoteId($pagamento);
    $lock_id = 'aculta_pagamentos_mercadopago.payment.' . $remote_id;
    if (!$this->lock->acquire($lock_id, 300)) {
      throw new MercadoPagoApiException('Outra operação deste pagamento está em andamento; tente novamente.', 409);
    }
    try {
      $locked_payment = $this->reload($pagamento);
      $remoto = $this->consultar($locked_payment, $remote_id);
      $status = (string) ($remoto->status ?? '');
      $local = $locked_payment->getState()->getId();
      if ($status === 'cancelled' && in_array($local, ['authorization', 'pending'], TRUE)) {
        // Reconcile a timeout after Mercado Pago accepted the cancellation.
        $locked_payment->setRemoteState('cancelled');
        $locked_payment->setState('authorization_voided');
        $locked_payment->save();
        return;
      }
      if (!$this->politica->podeCancelar($status, $local)) {
        throw new \DomainException((string) new TranslatableMarkup('Este pagamento não pode ser cancelado no estado atual (@status).', ['@status' => $status]));
      }

      try {
        $cancelado = $this->clientes->criar('pagamentos', $locked_payment->getPaymentGateway())->cancel((int) $remote_id);
      }
      catch (MPApiException $exception) {
        throw $this->falha('cancelar', $exception);
      }
      catch (\Throwable $exception) {
        throw $this->falhaTransporte('cancelar', $exception);
      }
      if (($cancelado->status ?? NULL) !== 'cancelled'
        || !isset($cancelado->id)
        || (string) $cancelado->id !== $remote_id) {
        throw new MercadoPagoApiException('O Mercado Pago não confirmou o cancelamento deste pagamento.');
      }

      $locked_payment->setRemoteState('cancelled');
      $locked_payment->setState('authorization_voided');
      $locked_payment->save();
      $this->logger->notice('Pagamento @id cancelado no Mercado Pago (status anterior @status).', [
        '@id' => $remote_id,
        '@status' => $status,
      ]);
    }
    finally {
      $this->lock->release($lock_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function refresh(PaymentInterface $payment): string {
    try {
      return $this->atualizar($payment);
    }
    catch (MercadoPagoApiException $exception) {
      throw new PaymentOperationException($exception->getMessage(), 0, $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cancel(PaymentInterface $payment): void {
    try {
      $this->cancelar($payment);
    }
    catch (MercadoPagoApiException $exception) {
      throw new PaymentOperationException($exception->getMessage(), 0, $exception);
    }
  }

  /**
   * Busca o pagamento remoto, traduzindo falhas da API em exceção segura.
   */
  private function consultar(PaymentInterface $pagamento, string $remote_id): object {
    try {
      $remoto = $this->clientes->criar('pagamentos', $pagamento->getPaymentGateway())->get((int) $remote_id);
    }
    catch (MPApiException $exception) {
      throw $this->falha('consultar', $exception);
    }
    catch (\Throwable $exception) {
      throw $this->falhaTransporte('consultar', $exception);
    }
    if (!isset($remoto->id) || (string) $remoto->id !== $remote_id) {
      throw new MercadoPagoApiException('O Mercado Pago retornou outro pagamento durante a consulta.');
    }
    $pedido = $pagamento->getOrder();
    $referencia = $remoto->external_reference ?? NULL;
    if (!$pedido instanceof OrderInterface || $pedido->id() === NULL
      || (!is_string($referencia) && !is_int($referencia))
      || (string) $referencia !== (string) $pedido->id()) {
      throw new MercadoPagoApiException('O pagamento remoto não corresponde ao pedido local.');
    }
    return $remoto;
  }

  /**
   * Registra o código da falha e devolve uma exceção com mensagem segura.
   */
  private function falha(string $operacao, MPApiException $e): MercadoPagoApiException {
    $codigo = $e->getStatusCode();
    $this->logger->error('Falha ao @operacao pagamento no Mercado Pago (HTTP @codigo).', [
      '@operacao' => $operacao,
      '@codigo' => $codigo,
    ]);
    return new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não respondeu como esperado. Tente de novo em instantes.'), $codigo, $e);
  }

  /**
   * Translates transport, timeout and client configuration failures safely.
   */
  private function falhaTransporte(string $operacao, \Throwable $exception): MercadoPagoApiException {
    $this->logger->error('Falha de comunicação ao @operacao pagamento no Mercado Pago.', [
      '@operacao' => $operacao,
    ]);
    return new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não respondeu como esperado. Tente de novo em instantes.'), 0, $exception);
  }

  /**
   * Validates and returns the numeric provider payment ID.
   */
  private function remoteId(PaymentInterface $payment): string {
    $remote_id = (string) $payment->getRemoteId();
    if ($remote_id === '' || !ctype_digit($remote_id) || (int) $remote_id < 1) {
      throw new \DomainException((string) new TranslatableMarkup('Pagamento sem identificador válido no Mercado Pago.'));
    }
    return $remote_id;
  }

  /**
   * Reloads a Commerce payment after acquiring its provider ID lock.
   */
  private function reload(PaymentInterface $payment): PaymentInterface {
    $payment_id = $payment->id();
    $fresh = $payment_id ? $this->entityTypeManager->getStorage('commerce_payment')->load($payment_id) : NULL;
    if (!$fresh instanceof PaymentInterface || (string) $fresh->getRemoteId() !== (string) $payment->getRemoteId()) {
      throw new \DomainException((string) new TranslatableMarkup('O pagamento local não corresponde ao registro recarregado.'));
    }
    return $fresh;
  }

}
