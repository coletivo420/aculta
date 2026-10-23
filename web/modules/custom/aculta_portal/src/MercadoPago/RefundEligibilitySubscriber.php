<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\Event\RefundEligibilityEvent;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Sales\MercadoPagoPaymentPolicy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Nega o estorno de doações e de cursos no gateway Mercado Pago.
 *
 * Doação é reconhecida pelo tipo de pedido e de item configurados em aculta_donation. Curso é reconhecido
 * pelo tipo de pedido e de item "curso" (inscrição em turma).
 */
final class RefundEligibilitySubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MercadoPagoPaymentPolicy $politica,
    private readonly ?CheckoutSettings $settings = NULL,
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
    // O tipo de pedido de curso é configurável: o configurado também não tem estorno (só acrescenta bloqueio).
    if (in_array($order->bundle(), $this->settings?->lmsOrderTypes() ?? [], TRUE)) {
      $event->deny('Doações e cursos não têm estorno.');
      return;
    }
    $itens = [];
    foreach ($order->getItems() as $item) {
      $itens[] = $item->bundle();
    }
    $config = $this->configFactory->get('aculta_donation.settings');
    if ($this->politica->bloqueiaEstorno(
      $order->bundle(),
      $itens,
      (string) $config->get('donate_order_type'),
      (string) $config->get('donate_item_type'),
    )) {
      $event->deny('Doações e cursos não têm estorno.');
    }
  }

}
