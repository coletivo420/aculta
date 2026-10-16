<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\MercadoPago;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Consulta os recursos citados por uma notificação de assinatura (já validada pela assinatura HMAC).
 *
 * Tópicos tratados (documentação do Mercado Pago, webhooks):
 * - subscription_preapproval: consulta a assinatura (SDK PreApprovalClient);
 * - subscription_preapproval_plan: consulta o plano (SDK PreApprovalPlanClient);
 * - subscription_authorized_payment: consulta a cobrança recorrente em GET /authorized_payments/{id}. O SDK
 *   3.16.0 não tem cliente para esse recurso, então a chamada usa o cliente HTTP do Drupal com o token do gateway.
 *
 * Nenhuma mudança é gravada no Commerce aqui: o vínculo assinatura-pedido de doação é a fase F3. Os dados
 * registrados em log não incluem e-mail, cartão nem outro dado de pagador.
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
  ) {}

  /**
   * Processa uma notificação de assinatura. Falhas são lançadas para o chamador responder com erro e o Mercado Pago reenviar.
   *
   * @throws \Drupal\aculta_portal\MercadoPago\MercadoPagoApiException
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

  private function assinatura(PaymentGatewayInterface $gateway, string $id): void {
    $assinatura = $this->recorrencia->obterAssinatura($gateway, $id);
    $this->logger->notice('Notificação de assinatura @id: status @status.', ['@id' => $id, '@status' => (string) ($assinatura->status ?? '')]);
  }

  private function plano(PaymentGatewayInterface $gateway, string $id): void {
    $plano = $this->recorrencia->obterPlano($gateway, $id);
    $this->logger->notice('Notificação de plano de assinatura @id: status @status.', ['@id' => $id, '@status' => (string) ($plano->status ?? '')]);
  }

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
      $this->logger->error('Consulta de cobrança recorrente @id falhou (HTTP @codigo).', ['@id' => $id, '@codigo' => $codigo]);
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não respondeu como esperado.'), $codigo);
    }
    $dados = json_decode((string) $resposta->getBody(), TRUE);
    $this->logger->notice('Notificação de cobrança recorrente @id: status @status.', ['@id' => $id, '@status' => (string) ($dados['status'] ?? '')]);
  }

  private function gateway(): PaymentGatewayInterface {
    $gateway = $this->entityTypeManager->getStorage('commerce_payment_gateway')->load('mercado_pago');
    if (!$gateway instanceof PaymentGatewayInterface) {
      throw new \RuntimeException('Gateway mercado_pago ausente.');
    }
    return $gateway;
  }

}
