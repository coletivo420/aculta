<?php

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Kernel tests for the Mercado Pago Checkout Pro payment gateway plugin.
 *
 * @group aculta_pagamentos_mercadopago
 */
class CheckoutProTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
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
      'plugin' => 'mercado_pago_checkout_pro',
      'configuration' => [
        'mode' => 'test',
        'redirect_mode' => 'redirect',
        'country' => 'AR',
      ],
    ]);
    $payment_gateway->save();

    $plugin = $payment_gateway->getPlugin();
    $this->assertSame('mercado_pago_checkout_pro', $plugin->getPluginId());
    $this->assertSame('test', $plugin->getMode());

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

}
