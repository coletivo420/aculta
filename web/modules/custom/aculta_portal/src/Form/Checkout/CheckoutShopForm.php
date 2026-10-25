<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutSettings;

/** Aba Loja: tipo de pedido comercial e checkout flow da Loja. */
final class CheckoutShopForm extends OrderTypeMappingFormBase {

  public function getFormId(): string {
    return 'aculta_portal_checkout_shop';
  }

  protected function contextId(): string {
    return 'shop';
  }

  protected function settingKey(): string {
    return 'shop_order_type';
  }

  protected function currentOrderType(CheckoutSettings $settings): string {
    return $settings->shopOrderType();
  }

  protected function intro(): string {
    return (string) $this->t('Tipo de pedido que o Portal trata como Loja ao classificar e consolidar o carrinho. O tipo de pedido de cada produto continua sendo resolvido pelo Commerce (tipo de item); a gestão operacional da loja segue no Green Stash.');
  }

}
