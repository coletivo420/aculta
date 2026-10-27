<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\MercadoPago;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use MercadoPago\Client\Common\RequestOptions;

/**
 * Processes signed chargeback notifications using provider data as authority.
 */
final class MercadoPagoChargebackNotificationService implements MercadoPagoChargebackNotificationInterface {

  public const TIPO = 'topic_chargebacks_wh';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MercadoPagoClientFactory $clients,
    private readonly MercadoPagoPaymentService $payments,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Fetches a signed chargeback case and reconciles its linked payment.
   *
   * The webhook body is not used for association: the case ID is covered by
   * the Webhook signature and the payment ID is read from the provider API.
   *
   * @throws \Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException
   */
  public function processar(string $caseId): void {
    if (preg_match('/^[1-9][0-9]{0,19}$/D', $caseId) !== 1) {
      throw new \InvalidArgumentException('Identificador de contestação inválido.');
    }
    $gateway = $this->entityTypeManager->getStorage('commerce_payment_gateway')->load('mercado_pago');
    if (!$gateway instanceof PaymentGatewayInterface || !$gateway->status()) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O gateway Mercado Pago está indisponível.'), 503);
    }

    try {
      // Mercado Pago requires the authenticated seller ID for chargeback GET.
      $seller = $this->clients->criar('usuarios', $gateway)->get();
      if (!is_int($seller->id ?? NULL) || $seller->id < 1) {
        throw new \UnexpectedValueException('Missing authenticated seller ID.');
      }
      $options = new RequestOptions(NULL, NULL, ['X-Caller-Id' => (string) $seller->id]);
      $case = $this->clients->criar('contestacoes', $gateway)->get($caseId, $options);
    }
    catch (\Throwable) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('Não foi possível consultar a contestação no Mercado Pago.'), 502);
    }
    if ((!is_string($case->id ?? NULL) && !is_int($case->id ?? NULL)) || (string) $case->id !== $caseId
      || (!is_string($case->payment_id ?? NULL) && !is_int($case->payment_id ?? NULL))
      || preg_match('/^[1-9][0-9]{0,19}$/D', (string) $case->payment_id) !== 1) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou uma contestação inconsistente.'), 502);
    }

    $paymentId = (string) $case->payment_id;
    $storage = $this->entityTypeManager->getStorage('commerce_payment');
    $paymentIds = $storage->getQuery()
      ->condition('payment_gateway', $gateway->id())
      ->condition('remote_id', $paymentId)
      ->accessCheck(FALSE)
      ->range(0, 2)
      ->execute();
    if (count($paymentIds) !== 1) {
      $this->logger->error('Contestação @case não tem uma associação única com pagamento Commerce (referência @payment).', [
        '@case' => $caseId,
        '@payment' => $paymentId,
      ]);
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O pagamento da contestação não pôde ser associado com segurança.'), 409);
    }
    $payment = $storage->load(reset($paymentIds));
    if (!$payment instanceof PaymentInterface || (string) $payment->getRemoteId() !== $paymentId
      || $payment->getPaymentGatewayId() !== $gateway->id()) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O pagamento da contestação não pôde ser associado com segurança.'), 409);
    }

    // Re-query the payment from Mercado Pago and use the regular shared lock,
    // amount/currency/order checks, and Commerce status reconciliation.
    $paymentStatus = $this->payments->atualizar($payment);
    $this->logger->warning('Contestação @case do pagamento @payment: status @status. Revisão operacional necessária.', [
      '@case' => $caseId,
      '@payment' => $paymentId,
      '@status' => is_string($case->status ?? NULL) ? $case->status : 'unknown',
    ]);
    $this->logger->notice('Pagamento @payment consultado após notificação de contestação: @status.', [
      '@payment' => $paymentId,
      '@status' => $paymentStatus,
    ]);
  }

}
