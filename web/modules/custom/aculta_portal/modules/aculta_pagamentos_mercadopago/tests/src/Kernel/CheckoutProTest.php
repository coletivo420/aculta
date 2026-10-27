<?php

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Kernel;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\aculta_pagamentos_mercadopago\PluginForm\Checkout\MercadoPagoPreferenceItems;
use Drupal\aculta_pagamentos_mercadopago\Commerce\MercadoPago\WebhookGuard;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoChargebackNotificationService;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the Mercado Pago Checkout Pro payment gateway plugin.
 *
 * @group aculta_pagamentos_mercadopago
 */
#[RunTestsInSeparateProcesses]
class CheckoutProTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'key',
    'commerce_payment',
    'aculta_pagamentos_mercadopago',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_payment_method');
    $this->installConfig('commerce_payment');
  }

  /**
   * Tests the gateway plugin default configuration and mode.
   */
  public function testGatewayPluginConfiguration(): void {
    $payment_gateway = PaymentGateway::create([
      'id' => 'mercado_pago',
      'label' => 'Mercado Pago',
      'status' => TRUE,
      'plugin' => 'aculta_mercadopago_checkout_pro',
      'configuration' => [
        'mode' => 'test',
        'redirect_mode' => 'redirect',
        'country' => 'AR',
      ],
    ]);
    $previous_public_key = getenv('MERCADOPAGO_TEST_PUBLIC_KEY');
    $previous_access_token = getenv('MERCADOPAGO_TEST_ACCESS_TOKEN');
    putenv('MERCADOPAGO_TEST_PUBLIC_KEY=TEST_PUBLIC_KEY_SENTINEL');
    putenv('MERCADOPAGO_TEST_ACCESS_TOKEN=TEST_ACCESS_TOKEN_SENTINEL');
    try {
      $payment_gateway->save();

      $this->assertInstanceOf(WebhookGuard::class, $this->container->get('aculta_pagamentos_mercadopago.webhook_guard'));
      $this->assertInstanceOf(MercadoPagoChargebackNotificationService::class, $this->container->get('aculta_pagamentos_mercadopago.chargeback_notifications'));

      $this->assertTrue($payment_gateway->status());
      $stored_configuration = $payment_gateway->getPluginConfiguration();
      $this->assertSame('', $stored_configuration['public_key_test']);
      $this->assertSame('', $stored_configuration['access_token_test']);

      $plugin = $payment_gateway->getPlugin();
      $this->assertSame('aculta_mercadopago_checkout_pro', $plugin->getPluginId());
      $this->assertSame('test', $plugin->getMode());
      $this->assertSame('aculta_mercadopago', $plugin->getPaymentType()->getPluginId());

      $payment = $this->container->get('entity_type.manager')->getStorage('commerce_payment')->create([
        'payment_gateway' => $payment_gateway->id(),
        'order_id' => 1,
        'amount' => new Price('10.00', 'BRL'),
      ]);
      $this->assertSame('aculta_mercadopago', $payment->bundle());
      $payment->getState()->applyTransitionById('wait_for_payment');
      $this->assertSame('pending', $payment->getState()->getId());

      $default = $plugin->defaultConfiguration();
      $expected_keys = [
        'public_key_test',
        'access_token_test',
        'public_key_stage',
        'access_token_stage',
        'public_key_prod',
        'access_token_prod',
        'client_id',
        'client_secret',
        'redirect_mode',
        'country',
        'payment_types',
        'payment_method',
        'max_installments',
        'autofee',
        'debug_logging',
      ];
      foreach ($expected_keys as $key) {
        $this->assertArrayHasKey($key, $default, "Default configuration should contain '$key'");
      }
      $this->assertSame('redirect', $default['redirect_mode']);
      $this->assertSame(12, $default['max_installments']);
      $this->assertFalse($default['autofee']);
      $this->assertFalse($default['debug_logging']);
    }
    finally {
      $previous_public_key === FALSE ? putenv('MERCADOPAGO_TEST_PUBLIC_KEY') : putenv('MERCADOPAGO_TEST_PUBLIC_KEY=' . $previous_public_key);
      $previous_access_token === FALSE ? putenv('MERCADOPAGO_TEST_ACCESS_TOKEN') : putenv('MERCADOPAGO_TEST_ACCESS_TOKEN=' . $previous_access_token);
    }
  }

  /**
   * Uses Commerce's actual adjusted line total in the provider preference.
   */
  public function testCommerceLinePromotionIsReflectedInPreferenceItems(): void {
    $this->container->get('commerce_price.currency_importer')->import('BRL');
    $order_item = OrderItem::create([
      'type' => 'test',
      'title' => 'Produto de teste',
      'quantity' => '2',
      'unit_price' => new Price('10.00', 'BRL'),
    ]);
    $order_item->save();
    $order_item->addAdjustment(new Adjustment([
      'type' => 'custom',
      'label' => 'Desconto de teste',
      'amount' => new Price('-2.00', 'BRL'),
    ]));

    $adjusted_total = $order_item->getAdjustedTotalPrice();
    $this->assertSame('18.00', $adjusted_total->getNumber());
    $items = MercadoPagoPreferenceItems::build([
      [
        'item' => ['title' => $order_item->getTitle(), 'quantity' => (int) $order_item->getQuantity()],
        'amount' => $adjusted_total,
      ],
    ], new Price('18.00', 'BRL'));

    $this->assertCount(1, $items);
    $this->assertSame('Produto de teste (2 un.)', $items[0]['title']);
    $this->assertSame(18.0, $items[0]['unit_price'] * $items[0]['quantity']);
  }

}
