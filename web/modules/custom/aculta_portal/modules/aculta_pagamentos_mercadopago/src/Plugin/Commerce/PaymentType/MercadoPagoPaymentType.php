<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentType;

use Drupal\commerce_payment\Attribute\CommercePaymentType;
use Drupal\commerce_payment\Plugin\Commerce\PaymentType\PaymentTypeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides payment states required by Mercado Pago Checkout Pro.
 */
#[CommercePaymentType(
  id: 'aculta_mercadopago',
  label: new TranslatableMarkup('Mercado Pago'),
  workflow: 'aculta_mercadopago_payment',
)]
final class MercadoPagoPaymentType extends PaymentTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    return [];
  }

}
