<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutSettings;

/** Aba LMS: tipo de pedido das compras de curso e checkout flow delas. */
final class CheckoutLmsForm extends OrderTypeMappingFormBase {

  public function getFormId(): string {
    return 'aculta_portal_checkout_lms';
  }

  protected function contextId(): string {
    return 'lms';
  }

  protected function settingKey(): string {
    return 'lms_order_type';
  }

  protected function currentOrderType(CheckoutSettings $settings): string {
    return $settings->lmsOrderType();
  }

  protected function intro(): string {
    return (string) $this->t('Tipo de pedido usado nas novas compras de curso. Pedidos já feitos com o tipo "curso" continuam concedendo matrícula, aparecendo em Cursos e sem estorno. Cursos e doações não têm estorno, seja qual for o tipo escolhido. A lógica pedagógica não é alterada.');
  }

}
