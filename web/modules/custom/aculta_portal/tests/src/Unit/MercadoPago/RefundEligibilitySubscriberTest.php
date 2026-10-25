<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\Event\RefundEligibilityEvent;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\MercadoPago\RefundEligibilitySubscriber;
use Drupal\aculta_portal\Sales\MercadoPagoPaymentPolicy;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Estorno negado para doação e curso, inclusive para o tipo de pedido de curso configurado (P-CART E).
 */
#[Group('aculta_portal')]
final class RefundEligibilitySubscriberTest extends UnitTestCase {

  private function allowed(string $orderType, array $itemTypes, array $checkout = [], bool $withSettings = TRUE, array $donation = []): bool {
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
      CheckoutSettings::CONFIG => $checkout,
    ]);
    $subscriber = new RefundEligibilitySubscriber($factory, new MercadoPagoPaymentPolicy(), $withSettings ? new CheckoutSettings($factory) : NULL);
    $event = new RefundEligibilityEvent($payment);
    $subscriber->onRefundEligibility($event);
    return $event->isAllowed();
  }

  #[DataProvider('negados')]
  public function testNegaCursoEDoacaoComoAntes(string $orderType, array $itemTypes): void {
    $this->assertFalse($this->allowed($orderType, $itemTypes));
  }

  public static function negados(): array {
    return [
      'pedido curso' => ['curso', ['curso']],
      'pedido doação' => ['doacao', ['donation']],
      'item curso em pedido da loja' => ['default', ['curso']],
      'assinatura com item de outro tipo' => ['assinatura', ['default']],
    ];
  }

  public function testPedidoDaLojaSegueEstornavel(): void {
    $this->assertTrue($this->allowed('default', ['default']));
  }

  public function testTipoDePedidoDeCursoConfiguradoTambemNaoTemEstorno(): void {
    // Itens que não são `curso` escapariam da lista de bloqueio fixa; o tipo configurado fecha essa brecha.
    $this->assertFalse($this->allowed('aula', ['default'], ['lms_order_type' => 'aula']));
  }

  public function testSemConfigurarOMesmoTipoNaoEBloqueadoPorEngano(): void {
    $this->assertTrue($this->allowed('aula', ['default'], []));
  }

  public function testDoacaoPeloCarrinhoNaoTemEstorno(): void {
    $this->assertFalse($this->allowed('doacao_carrinho', ['default'], [], TRUE, ['cart_order_type' => 'doacao_carrinho']));
    $this->assertFalse($this->allowed('default', ['item_doacao_carrinho'], [], TRUE, ['cart_item_type' => 'item_doacao_carrinho']));
  }

  public function testCarrinhoDeDoacaoNaoConfiguradoNaoBloqueiaALoja(): void {
    $this->assertTrue($this->allowed('default', ['default'], [], TRUE, ['cart_order_type' => NULL, 'cart_item_type' => NULL]));
  }

}
