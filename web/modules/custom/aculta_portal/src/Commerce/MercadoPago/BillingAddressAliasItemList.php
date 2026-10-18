<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Commerce\MercadoPago;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Lê o endereço do perfil pelo campo `field_address` e o expõe como `address`.
 *
 * O gateway `aculta_pagamentos_mercadopago` (porte do contrib `commerce_mercado_pago`) lê o campo `address` do perfil de cobrança. Os perfis do ACULTA guardam o
 * endereço em `field_address`, e o endereço continua sendo um só (AGENTS.md, "Endereço único"). Este campo é
 * computado: não tem armazenamento próprio nem cópia, e o valor vem sempre do `field_address` do mesmo perfil.
 * Ver docs/portal/CHECKOUT-BILLING.md.
 */
final class BillingAddressAliasItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $profile = $this->getEntity();
    if (!$profile->hasField('field_address') || $profile->get('field_address')->isEmpty()) {
      return;
    }
    $this->list[0] = $this->createItem(0, $profile->get('field_address')->first()->getValue());
  }

}
