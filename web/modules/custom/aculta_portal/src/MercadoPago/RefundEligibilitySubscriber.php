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
    $itens = [];
    foreach ($order->getItems() as $item) {
      $itens[] = $item->bundle();
    }
    $config = $this->configFactory->get('aculta_donation.settings');
    $texto = static fn(mixed $valor): string => is_string($valor) ? $valor : '';
    if ($this->politica->bloqueiaEstorno(
      $order->bundle(),
      $itens,
      $texto($config->get('donate_order_type')),
      $texto($config->get('donate_item_type')),
      [...($this->settings?->lmsOrderTypes() ?? []), $texto($config->get('cart_order_type'))],
      [$texto($config->get('cart_item_type'))],
    )) {
      $event->deny('Doações e cursos não têm estorno.');
    }
  }

}
