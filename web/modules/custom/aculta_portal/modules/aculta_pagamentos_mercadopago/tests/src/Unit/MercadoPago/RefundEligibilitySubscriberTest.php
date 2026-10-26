<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\Event\RefundEligibilityEvent;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\RefundEligibilitySubscriber;
use Drupal\aculta_pagamentos_mercadopago\Sales\MercadoPagoPaymentPolicy;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Nega estornos para doações e cursos.
 *
 * Inclui o tipo de pedido de curso configurado (P-CART E).
 */
#[Group('aculta_pagamentos_mercadopago')]
final class RefundEligibilitySubscriberTest extends UnitTestCase {

  /**
   * Performs the allowed operation.
   */
  private function allowed(
    string $orderType,
    array $itemTypes,
    array $checkout = [],
    bool $withSettings = TRUE,
    array $donation = [],
  ): bool {
    $items = array_map(function (string $type): OrderItemInterface {
      $item = $this->createMock(OrderItemInterface::class);
      $item->method('bundle')->willReturn($type);
      return $item;
    }, $itemTypes);
    $order = $this->createMock(OrderInterface::class);
    $order->method('bundle')->willReturn($orderType);
    $order->method('getItems')->willReturn($items);
    $payment = $this->createMock(PaymentInterface::class);
    $payment->method('getOrder')->willReturn($order);
    $factory = $this->getConfigFactoryStub([
      'aculta_donation.settings' => $donation + ['donate_order_type' => 'doacao', 'donate_item_type' => 'donation'],
      'aculta_portal.checkout_settings' => $withSettings ? $checkout : [],
    ]);
    $subscriber = new RefundEligibilitySubscriber($factory, new MercadoPagoPaymentPolicy());
    $event = new RefundEligibilityEvent($payment);
    $subscriber->onRefundEligibility($event);
    return $event->isAllowed();
  }

  /**
   * Tests the nega curso e doacao como antes behavior.
   */
  #[DataProvider('negados')]
  public function testRejectsCourseAndDonationRefunds(string $orderType, array $itemTypes): void {
    $this->assertFalse($this->allowed($orderType, $itemTypes));
  }

  /**
   * Performs the negados operation.
   */
  public static function negados(): array {
    return [
      'pedido curso' => ['curso', ['curso']],
      'pedido doação' => ['doacao', ['donation']],
      'item curso em pedido da loja' => ['default', ['curso']],
      'assinatura com item de outro tipo' => ['assinatura', ['default']],
    ];
  }

  /**
   * Tests the pedido da loja segue estornavel behavior.
   */
  public function testPedidoDaLojaSegueEstornavel(): void {
    $this->assertTrue($this->allowed('default', ['default']));
  }

  /**
   * Tests that the configured course order type also has no refund.
   */
  public function testTipoDePedidoDeCursoConfiguradoTambemNaoTemEstorno(): void {
    // Itens que não são `curso` escapariam da lista de bloqueio fixa; o tipo
    // configurado fecha essa brecha.
    $this->assertFalse($this->allowed('aula', ['default'], ['lms_order_type' => 'aula']));
  }

  /**
   * Tests the sem configurar o mesmo tipo nao e bloqueado por engano behavior.
   */
  public function testUnconfiguredMatchingTypeIsNotBlocked(): void {
    $this->assertTrue($this->allowed('aula', ['default']));
  }

  /**
   * Tests the doacao pelo carrinho nao tem estorno behavior.
   */
  public function testDoacaoPeloCarrinhoNaoTemEstorno(): void {
    $this->assertFalse($this->allowed('doacao_carrinho', ['default'], [], TRUE, ['cart_order_type' => 'doacao_carrinho']));
    $this->assertFalse($this->allowed('default', ['item_doacao_carrinho'], [], TRUE, ['cart_item_type' => 'item_doacao_carrinho']));
  }

  /**
   * Tests the carrinho de doacao nao configurado nao bloqueia a loja behavior.
   */
  public function testUnconfiguredDonationCartDoesNotBlockStore(): void {
    $this->assertTrue($this->allowed('default', ['default'], [], TRUE, [
      'cart_order_type' => NULL,
      'cart_item_type' => NULL,
    ]));
  }

}
