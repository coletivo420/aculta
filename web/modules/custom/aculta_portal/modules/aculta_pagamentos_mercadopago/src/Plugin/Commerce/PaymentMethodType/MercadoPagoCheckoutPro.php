<?php

namespace Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Attribute\CommercePaymentMethodType;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\CreditCard;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides the Mercado Pago Checkout payment method type.
 */
#[CommercePaymentMethodType(
  id: 'aculta_mercadopago_checkout_pro',
  label: new TranslatableMarkup('Mercado Pago Checkout Pro'),
  create_label: new TranslatableMarkup('Mercado Pago'),
)]
class MercadoPagoCheckoutPro extends CreditCard {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    if ($payment_method->hasField('card_type') && !$payment_method->get('card_type')->isEmpty()) {
      return parent::buildLabel($payment_method);
    }
    return $this->t('Mercado Pago');
  }

}
