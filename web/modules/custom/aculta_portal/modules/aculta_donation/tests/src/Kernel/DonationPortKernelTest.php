<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_donation\Kernel;

use Drupal\aculta_donation\Access\DonationRefundAccess;
use Drupal\aculta_donation\Checkout\DonationCheckoutProvider;
use Drupal\aculta_donation\Hook\DonationNoticeEventPublisher;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\aculta_messenger\Service\MessengerNoticePublisher;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\aculta_portal\Contract\PaymentOperationsInterface;
use Drupal\aculta_portal\Contract\SubscriptionStatusInterface;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoPaymentService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\aculta_pagamentos_mercadopago\Sales\MercadoPagoPaymentPolicy;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\RecurrenceRequestBuilder;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Ports the Donation flow's plugins, order creation, and refund-access rule.
 */
#[Group('aculta_donation')]
#[RunTestsInSeparateProcesses]
final class DonationPortKernelTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['commerce_cart', 'commerce_checkout', 'commerce_payment', 'aculta_donation'];

  /**
   * {@inheritdoc}
   *
   * Real contract classes avoid installing Messenger's full dependency and
   * configuration closure in this focused Kernel test.
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container->register('aculta_pagamentos_mercadopago.client_factory', MercadoPagoClientFactory::class);
    $container->register('aculta_pagamentos_mercadopago.recurrence_request_builder', RecurrenceRequestBuilder::class);
    $container->register('aculta_pagamentos_mercadopago.payment_policy', MercadoPagoPaymentPolicy::class);
    $container->register('aculta_pagamentos_mercadopago.logger', LoggerChannelInterface::class)
      ->setFactory([new Reference('logger.factory'), 'get'])
      ->addArgument('aculta_pagamentos_mercadopago');
    $container->register('aculta_pagamentos_mercadopago.payment_service', MercadoPagoPaymentService::class)
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.client_factory'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.payment_policy'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.logger'));
    $container->register('aculta_pagamentos_mercadopago.recurrence_service', MercadoPagoRecurrenceService::class)
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.client_factory'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.recurrence_request_builder'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.logger'));
    $container->register('aculta_pagamentos_mercadopago.subscription_recorder', MercadoPagoSubscriptionRecorder::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.recurrence_service'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.logger'))
      ->addArgument(new Reference('datetime.time'));
    $container->setAlias('aculta_portal.payment_provider.subscription_status', 'aculta_pagamentos_mercadopago.subscription_recorder');
    $container->setAlias('aculta_portal.payment_provider.subscription_operations', 'aculta_pagamentos_mercadopago.subscription_recorder');
    $container->setAlias('aculta_portal.payment_provider.payment_operations', 'aculta_pagamentos_mercadopago.payment_service');
    $container->setAlias('aculta_portal.payment_provider.refund_policy', 'aculta_pagamentos_mercadopago.payment_policy');
    $container->register('aculta_messenger.notice_event_publisher', MessengerNoticePublisher::class)
      ->addArgument(new Reference('event_dispatcher'));
    $container->register('aculta_portal.order_type_checkout_flows', OrderTypeCheckoutFlows::class)
      ->addArgument(new Reference('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('commerce_price.currency_importer')->import('BRL');
    if (!OrderItemType::load('donation')) {
      OrderItemType::create(['id' => 'donation', 'label' => 'Doação', 'orderType' => 'default'])->save();
    }
  }

  /**
   * Tests the real shared publisher and checkout contract implementations.
   */
  public function testDonationServicesResolveAgainstSharedContracts(): void {
    $this->assertInstanceOf(
      SubscriptionStatusInterface::class,
      $this->container->get('aculta_portal.payment_provider.subscription_status'),
    );
    $this->assertInstanceOf(
      PaymentOperationsInterface::class,
      $this->container->get('aculta_portal.payment_provider.payment_operations'),
    );
    $this->assertInstanceOf(
      MessengerNoticePublisher::class,
      $this->container->get('aculta_messenger.notice_event_publisher'),
    );
    $this->assertInstanceOf(
      DonationNoticeEventPublisher::class,
      $this->container->get(DonationNoticeEventPublisher::class),
    );
    $this->assertInstanceOf(
      DonationCheckoutProvider::class,
      $this->container->get('aculta_donation.checkout_provider'),
    );
  }

  /**
   * Tests discovery of Donation checkout flow, panes, and field widget.
   */
  public function testPluginsDoFluxoEstaoDescobertos(): void {
    $fluxos = $this->container->get('plugin.manager.commerce_checkout_flow');
    $this->assertSame(
      'Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow\DonationCheckoutFlow',
      $fluxos->getDefinition('fluxo_doacao', FALSE)['class'],
    );
    $paineis = $this->container->get('plugin.manager.commerce_checkout_pane');
    foreach (['commerce_donation_pane', 'commerce_memorial_pane', 'commerce_donation_add_pane', 'donation_thank_you'] as $id) {
      $this->assertStringStartsWith('Drupal\aculta_donation\\', $paineis->getDefinition($id, FALSE)['class']);
    }
    $this->assertStringStartsWith(
      'Drupal\aculta_donation\\',
      $this->container->get('plugin.manager.field.widget')->getDefinition('commerce_donation_flow_level_widget', FALSE)['class'],
    );
  }

  /**
   * Tests discovery of the Donation blocks.
   */
  public function testBlocosDeDoacaoEstaoDescobertos(): void {
    $blocos = $this->container->get('plugin.manager.block');
    foreach (['aculta_donation_donate', 'aculta_donation_quick', 'aculta_donation_back'] as $id) {
      $this->assertStringStartsWith('Drupal\aculta_donation\\', $blocos->getDefinition($id, FALSE)['class']);
    }
  }

  /**
   * Tests that a new Donation order starts with a zero-value donation item.
   */
  public function testNovoPedidoTemItemDeDoacaoComValorZero(): void {
    $pedido = $this->container->get('aculta_donation.new_order')->get('single', 'donation');
    $pedido->save();
    $this->assertTrue(DonationRefundAccess::pedidoTemDoacao($pedido));
    $item = $pedido->getItems()[0];
    $this->assertSame('donation', $item->bundle());
    $this->assertSame(0.0, (float) $item->getUnitPrice()->getNumber());
    $this->assertSame('single', $item->get('field_gift_type')->value);
  }

  /**
   * Tests donation refunds are denied while other operations remain allowed.
   */
  public function testDonationRefundIsDeniedAndOtherOperationsRemainAllowed(): void {
    $pedido = $this->container->get('aculta_donation.new_order')->get('single', 'donation');
    $pedido->save();
    $rota = $this->container->get('router.route_provider')->getRouteByName('entity.commerce_payment.operation_form');
    $servico = $this->container->get('aculta_donation.refund_access');
    $usuario = $this->container->get('current_user');

    $estorno = new RouteMatch('entity.commerce_payment.operation_form', $rota,
      ['commerce_order' => $pedido, 'operation' => DonationRefundAccess::OPERACAO_ESTORNO],
      ['commerce_order' => $pedido, 'operation' => DonationRefundAccess::OPERACAO_ESTORNO]);
    $this->assertFalse($servico->access($estorno, $usuario)->isAllowed());

    $outra = new RouteMatch('entity.commerce_payment.operation_form', $rota,
      ['commerce_order' => $pedido, 'operation' => 'void-payment'],
      ['commerce_order' => $pedido, 'operation' => 'void-payment']);
    $this->assertTrue($servico->access($outra, $usuario)->isAllowed());
  }

}
