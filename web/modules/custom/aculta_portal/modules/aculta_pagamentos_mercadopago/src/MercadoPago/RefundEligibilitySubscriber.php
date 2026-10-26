<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\Event\RefundEligibilityEvent;
use Drupal\aculta_pagamentos_mercadopago\Sales\MercadoPagoPaymentPolicy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Nega o estorno de doações, assinaturas e cursos no gateway Mercado Pago.
 */
final class RefundEligibilitySubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MercadoPagoPaymentPolicy $politica,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [RefundEligibilityEvent::NAME => 'onRefundEligibility'];
  }

  /**
   * Nega o estorno quando o pedido é de doação ou de curso.
   */
  public function onRefundEligibility(RefundEligibilityEvent $event): void {
    $order = $event->getPayment()->getOrder();
    if ($order === NULL) {
      return;
    }
    $itens = [];
    foreach ($order->getItems() as $item) {
      $itens[] = $item->bundle();
    }
    $donation = $this->configFactory->get('aculta_donation.settings');
    $checkout = $this->configFactory->get('aculta_portal.checkout_settings');
    $texto = static fn(mixed $valor): string => is_string($valor) ? $valor : '';
    $lmsOrderType = $texto($checkout->get('lms_order_type'));
    $lmsOrderType = $lmsOrderType !== '' ? $lmsOrderType : 'curso';
    if ($this->politica->bloqueiaEstorno(
      $order->bundle(),
      $itens,
      $texto($donation->get('donate_order_type')),
      $texto($donation->get('donate_item_type')),
      array_values(array_unique([$lmsOrderType, 'curso', $texto($donation->get('cart_order_type'))])),
      [$texto($donation->get('cart_item_type'))],
    )) {
      $event->deny('Doações e cursos não têm estorno.');
    }
  }

}
