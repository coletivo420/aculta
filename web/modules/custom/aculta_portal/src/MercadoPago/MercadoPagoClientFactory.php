<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\MercadoPago;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_mercado_pago\Plugin\Commerce\PaymentGateway\CheckoutPro;
use MercadoPago\Client\AdvancedPayment\AdvancedPaymentClient;
use MercadoPago\Client\CardToken\CardTokenClient;
use MercadoPago\Client\Chargeback\ChargebackClient;
use MercadoPago\Client\Customer\CustomerCardClient;
use MercadoPago\Client\Customer\CustomerClient;
use MercadoPago\Client\DisbursementRefund\DisbursementRefundClient;
use MercadoPago\Client\IdentificationType\IdentificationTypeClient;
use MercadoPago\Client\Invoice\InvoiceClient;
use MercadoPago\Client\MerchantOrder\MerchantOrderClient;
use MercadoPago\Client\OAuth\OAuthClient;
use MercadoPago\Client\Order\OrderClient;
use MercadoPago\Client\Order\OrderTransactionClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Payment\PaymentRefundClient;
use MercadoPago\Client\PaymentMethod\PaymentMethodClient;
use MercadoPago\Client\Point\PointClient;
use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\Client\PreApprovalPlan\PreApprovalPlanClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\User\UserClient;
use MercadoPago\MercadoPagoConfig;

/**
 * Entrega os clientes do SDK do Mercado Pago com as credenciais do gateway do ambiente.
 *
 * Todos os 20 clientes concretos do SDK 3.x (dx-php) estão mapeados em CLIENTES. O token vem do próprio
 * gateway (commerce_mercado_pago), no modo ativo (test, stage ou prod), e nunca de constante no código.
 * O SDK guarda o token de forma estática; a fábrica o redefine a cada criação, então o cliente sempre usa
 * as credenciais do gateway recebido.
 */
class MercadoPagoClientFactory {

  /**
   * Alias técnico-neutro => classe do cliente do SDK.
   */
  public const CLIENTES = [
    'pagamentos' => PaymentClient::class,
    'reembolsos' => PaymentRefundClient::class,
    'pedidos_comerciante' => MerchantOrderClient::class,
    'contestacoes' => ChargebackClient::class,
    'assinaturas' => PreApprovalClient::class,
    'planos_assinatura' => PreApprovalPlanClient::class,
    'preferencias' => PreferenceClient::class,
    'pedidos' => OrderClient::class,
    'transacoes_pedido' => OrderTransactionClient::class,
    'pagamento_avancado' => AdvancedPaymentClient::class,
    'token_cartao' => CardTokenClient::class,
    'clientes' => CustomerClient::class,
    'cartoes_cliente' => CustomerCardClient::class,
    'estornos_desembolso' => DisbursementRefundClient::class,
    'tipos_identificacao' => IdentificationTypeClient::class,
    'faturas' => InvoiceClient::class,
    'maquinas_point' => PointClient::class,
    'metodos_pagamento' => PaymentMethodClient::class,
    'oauth' => OAuthClient::class,
    'usuarios' => UserClient::class,
  ];

  /**
   * Chaves de token do gateway por modo (live usa access_token_prod, como no módulo).
   */
  private const CHAVES_TOKEN = [
    'test' => 'access_token_test',
    'stage' => 'access_token_stage',
    'live' => 'access_token_prod',
  ];

  /**
   * Cria o cliente $alias com as credenciais de $gateway.
   *
   * @throws \InvalidArgumentException
   *   Alias desconhecido, ou gateway que não é do Mercado Pago Checkout Pro.
   * @throws \RuntimeException
   *   Gateway sem token para o modo ativo.
   */
  public function criar(string $alias, PaymentGatewayInterface $gateway): object {
    if (!isset(self::CLIENTES[$alias])) {
      throw new \InvalidArgumentException("Cliente do Mercado Pago desconhecido: $alias.");
    }
    $this->configurar($gateway);
    $classe = self::CLIENTES[$alias];
    return new $classe();
  }

  /**
   * Inventário: alias => classe do SDK instalada (TRUE quando a classe existe).
   *
   * Usado pelo relatório de status e pelo teste que confirma que o SDK instalado tem todos os clientes.
   *
   * @return array<string, bool>
   */
  public function inventario(): array {
    return array_map(static fn (string $classe): bool => class_exists($classe), self::CLIENTES);
  }

  /**
   * Token do modo ativo do gateway (test, stage ou live). Falha se o gateway não for Checkout Pro ou não tiver token.
   *
   * @throws \InvalidArgumentException
   * @throws \RuntimeException
   */
  public function tokenAtivo(PaymentGatewayInterface $gateway): string {
    $plugin = $gateway->getPlugin();
    if (!$plugin instanceof CheckoutPro) {
      throw new \InvalidArgumentException('O gateway não é do Mercado Pago Checkout Pro.');
    }
    $chave = self::CHAVES_TOKEN[$plugin->getMode()] ?? NULL;
    $token = $chave === NULL ? '' : (string) ($plugin->getConfiguration()[$chave] ?? '');
    if ($token === '') {
      throw new \RuntimeException('O gateway do Mercado Pago não tem token para o modo ativo.');
    }
    return $token;
  }

  /**
   * Define o token e o integrador do gateway no SDK.
   */
  private function configurar(PaymentGatewayInterface $gateway): void {
    MercadoPagoConfig::setAccessToken($this->tokenAtivo($gateway));
    MercadoPagoConfig::setIntegratorId(CheckoutPro::INTEGRATOR_ID);
  }

}
