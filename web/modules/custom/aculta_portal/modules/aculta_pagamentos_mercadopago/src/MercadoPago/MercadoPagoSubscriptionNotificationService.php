<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\MercadoPago;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Loads provider resources referenced by signed subscription notifications.
 *
 * Supported Mercado Pago webhook topics:
 * - subscription_preapproval: consulta a assinatura (SDK PreApprovalClient);
 * - subscription_preapproval_plan: consulta o plano pelo SDK;
 * - subscription_authorized_payment: fetches an invoice from
 *   /authorized_payments/{id} via Drupal HTTP; SDK 3.16 has no client for it.
 *
 * SubscriptionRecorder owns order and payment writes. Logs omit payer data.
 */
class MercadoPagoSubscriptionNotificationService {

  public const TIPOS = [
    'subscription_preapproval',
    'subscription_preapproval_plan',
    'subscription_authorized_payment',
  ];

  private const URL_COBRANCA = 'https://api.mercadopago.com/authorized_payments/';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MercadoPagoClientFactory $clientes,
    private readonly MercadoPagoRecurrenceService $recorrencia,
    private readonly ClientInterface $http,
    private readonly LoggerChannelInterface $logger,
    private readonly MercadoPagoSubscriptionRecorder $gravador,
  ) {}

  /**
   * Processes a signed event; failures request provider retry.
   *
   * @throws \Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException
   */
  public function processar(string $tipo, string $id): void {
    if (!in_array($tipo, self::TIPOS, TRUE)) {
      throw new \InvalidArgumentException("Tópico de assinatura não tratado: $tipo.");
    }
    $gateway = $this->gateway();
    match ($tipo) {
      'subscription_preapproval' => $this->assinatura($gateway, $id),
      'subscription_preapproval_plan' => $this->plano($gateway, $id),
      'subscription_authorized_payment' => $this->cobranca($gateway, $id),
    };
  }

  /**
   * Performs the assinatura operation.
   */
  private function assinatura(PaymentGatewayInterface $gateway, string $id): void {
    $assinatura = $this->recorrencia->obterAssinatura($gateway, $id);
    if ((!is_string($assinatura->id ?? NULL) && !is_int($assinatura->id ?? NULL)) || (string) $assinatura->id !== $id) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou uma assinatura inconsistente.'), 502);
    }
    $this->logger->notice('Notificação de assinatura @id: status @status.', [
      '@id' => $id,
      '@status' => is_string($assinatura->status ?? NULL) ? $assinatura->status : '',
    ]);
    $this->gravador->assinatura($assinatura);
  }

  /**
   * Performs the plano operation.
   */
  private function plano(PaymentGatewayInterface $gateway, string $id): void {
    $plano = $this->recorrencia->obterPlano($gateway, $id);
    if ((!is_string($plano->id ?? NULL) && !is_int($plano->id ?? NULL)) || (string) $plano->id !== $id) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou um plano inconsistente.'), 502);
    }
    $this->logger->notice('Notificação de plano de assinatura @id: status @status.', [
      '@id' => $id,
      '@status' => is_string($plano->status ?? NULL) ? $plano->status : '',
    ]);
  }

  /**
   * Performs the cobranca operation.
   */
  private function cobranca(PaymentGatewayInterface $gateway, string $id): void {
    $token = $this->clientes->tokenAtivo($gateway);
    try {
      $resposta = $this->http->request('GET', self::URL_COBRANCA . rawurlencode($id), [
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
        'timeout' => 15,
        'http_errors' => FALSE,
      ]);
    }
    catch (GuzzleException) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('Não foi possível consultar a cobrança no Mercado Pago.'));
    }
    $codigo = $resposta->getStatusCode();
    if ($codigo !== 200) {
      $this->logger->error('Consulta de cobrança recorrente @id falhou (HTTP @codigo).', [
        '@id' => $id,
        '@codigo' => $codigo,
      ]);
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não respondeu como esperado.'), $codigo);
    }
    $dados = json_decode((string) $resposta->getBody(), TRUE);
    $response_id = is_array($dados) ? ($dados['id'] ?? NULL) : NULL;
    if ((!is_string($response_id) && !is_int($response_id)) || (string) $response_id !== $id) {
      $this->logger->error('Consulta de cobrança recorrente retornou um identificador inesperado.');
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou dados inconsistentes.'), 502);
    }
    $this->logger->notice('Notificação de cobrança recorrente @id: status @status.', [
      '@id' => $id,
      '@status' => is_string($dados['status'] ?? NULL) ? $dados['status'] : '',
    ]);
    $this->gravador->cobranca($gateway, $dados);
  }

  /**
   * Performs the gateway operation.
   */
  private function gateway(): PaymentGatewayInterface {
    $gateway = $this->entityTypeManager->getStorage('commerce_payment_gateway')->load('mercado_pago');
    if (!$gateway instanceof PaymentGatewayInterface) {
      throw new \RuntimeException('Gateway mercado_pago ausente.');
    }
    return $gateway;
  }

}
