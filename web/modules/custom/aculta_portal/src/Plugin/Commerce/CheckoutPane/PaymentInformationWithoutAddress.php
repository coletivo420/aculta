<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_payment\Plugin\Commerce\CheckoutPane\PaymentInformation;

/**
 * Pagamento sem endereço e sem meios de pagamento salvos no resumo.
 *
 * O pagamento é do Mercado Pago: o resumo (revisão e conclusão) mostra só o gateway, nunca o endereço nem um método
 * salvo. Sem isso, a conclusão mostrava um bloco vazio e a revisão repetia o endereço. A regra de cobrança e os dados
 * enviados ao Mercado Pago não mudam. Ver docs/portal/CHECKOUT-BILLING.md.
 */
class PaymentInformationWithoutAddress extends PaymentInformation {

  /** {@inheritdoc} */
  public function buildPaneSummary() {
    $gateway = $this->order->get('payment_gateway')->entity;
    if (!$gateway) {
      return [];
    }
    return ['payment_gateway' => ['#markup' => $gateway->getPlugin()->getDisplayLabel()]];
  }

}
