<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_payment\Plugin\Commerce\CheckoutPane\PaymentInformation;

/**
 * Pagamento sem repetir o endereço no resumo da revisão.
 *
 * O endereço já aparece no pane "Endereço" (aculta_billing_address). Sem esta subclasse, o resumo do Commerce
 * Payment repetia o perfil de cobrança quando não há método de pagamento salvo (caso do Mercado Pago), e o endereço
 * aparecia duas vezes. A regra de cobrança e os dados enviados ao Mercado Pago não mudam. Ver docs/portal/CHECKOUT-BILLING.md.
 */
class PaymentInformationWithoutAddress extends PaymentInformation {

  /** {@inheritdoc} */
  public function buildPaneSummary() {
    $summary = parent::buildPaneSummary();
    unset($summary['profile']);
    return $summary;
  }

}
