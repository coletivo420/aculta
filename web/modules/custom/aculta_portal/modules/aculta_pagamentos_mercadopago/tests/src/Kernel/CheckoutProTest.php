<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Kernel;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Core\Form\FormState;
use Drupal\key\Entity\Key;
use Drupal\commerce_price\Price;
use Drupal\aculta_pagamentos_mercadopago\PluginForm\Checkout\MercadoPagoPreferenceItems;
use Drupal\aculta_pagamentos_mercadopago\Commerce\MercadoPago\WebhookGuard;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoChargebackNotificationService;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the Mercado Pago Checkout Pro payment gateway plugin.
 */
#[Group('aculta_pagamentos_mercadopago')]
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
    $this->installConfig('key');
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
        'autofee' => TRUE,
      ],
    ]);
    $previous_public_key = getenv('MERCADOPAGO_TEST_PUBLIC_KEY');
    $previous_access_token = getenv('MERCADOPAGO_TEST_ACCESS_TOKEN');
    putenv('MERCADOPAGO_TEST_PUBLIC_KEY=TEST_PUBLIC_KEY_SENTINEL');
    putenv('MERCADOPAGO_TEST_ACCESS_TOKEN=TEST_ACCESS_TOKEN_SENTINEL');
    try {
      // Configuration may be safely saved while runtime secrets are absent.
      // Restore the sandbox pair before exercising the configured plugin.
      putenv('MERCADOPAGO_TEST_PUBLIC_KEY');
      putenv('MERCADOPAGO_TEST_ACCESS_TOKEN');
      try {
        $payment_gateway->save();
      }
      finally {
        putenv('MERCADOPAGO_TEST_PUBLIC_KEY=TEST_PUBLIC_KEY_SENTINEL');
        putenv('MERCADOPAGO_TEST_ACCESS_TOKEN=TEST_ACCESS_TOKEN_SENTINEL');
      }

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
      $configuration_form = $plugin->buildConfigurationForm([], new FormState());
      $this->assertArrayNotHasKey('autofee', $configuration_form);
      $this->assertArrayHasKey('credentials_environment', $configuration_form);
      $this->assertArrayNotHasKey('credentials_test', $configuration_form);
      $this->assertArrayNotHasKey('credentials_stage', $configuration_form);
      $this->assertArrayNotHasKey('credentials_prod', $configuration_form);
      $this->assertArrayNotHasKey('client_id', $configuration_form);
      $this->assertArrayNotHasKey('client_secret', $configuration_form);
      $this->assertStringContainsString('Payer details are removed', (string) $configuration_form['debug_logging']['#description']);
      $this->assertStringNotContainsString('webhook data', (string) $configuration_form['debug_logging']['#description']);
      $configuration_form['#parents'] = ['configuration', 'aculta_mercadopago_checkout_pro'];
      $form_state = new FormState();
      $form_state->setValue($configuration_form['#parents'], [
        'display_label' => 'Mercado Pago',
        'mode' => 'test',
        'payment_method_types' => [],
        'collect_billing_information' => FALSE,
        'redirect_mode' => 'redirect',
        'country' => 'AR',
        'payment_options_wrapper' => [],
        'max_installments' => 12,
        'debug_logging' => FALSE,
      ]);
      $plugin->submitConfigurationForm($configuration_form, $form_state);
      $this->assertFalse($plugin->getConfiguration()['autofee']);

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

  /**
   * The native Commerce webhook requires the environment Key and fails closed.
   */
  public function testNativeWebhookRouteRequiresEnvironmentSignature(): void {
    $gateway = PaymentGateway::create([
      'id' => 'mercado_pago',
      'label' => 'Mercado Pago',
      'status' => TRUE,
      'plugin' => 'aculta_mercadopago_checkout_pro',
      'configuration' => ['mode' => 'test'],
    ]);
    $previous_public_key = getenv('MERCADOPAGO_TEST_PUBLIC_KEY');
    $previous_access_token = getenv('MERCADOPAGO_TEST_ACCESS_TOKEN');
    putenv('MERCADOPAGO_TEST_PUBLIC_KEY=KERNEL_TEST_PUBLIC_KEY');
    putenv('MERCADOPAGO_TEST_ACCESS_TOKEN=KERNEL_TEST_ACCESS_TOKEN');
    $gateway->save();
    Key::create([
      'id' => 'mercadopago_webhook_secret',
      'label' => 'Test webhook signing secret',
      'key_type' => 'authentication',
      'key_provider' => 'env',
      'key_provider_settings' => [
        'env_variable' => 'MERCADOPAGO_WEBHOOK_SECRET',
        'base64_encoded' => FALSE,
        'strip_line_breaks' => TRUE,
      ],
      'key_input' => 'none',
    ])->save();

    $previous_secret = getenv('MERCADOPAGO_WEBHOOK_SECRET');
    putenv('MERCADOPAGO_WEBHOOK_SECRET=kernel-test-only-secret');
    try {
      $request = $this->webhookRequest('kernel-test-only-secret');
      $event = new RequestEvent($this->container->get('kernel'), $request, HttpKernelInterface::MAIN_REQUEST);
      $this->container->get('aculta_pagamentos_mercadopago.webhook_subscriber')->onKernelRequest($event);
      $this->assertNull($event->getResponse());
      $this->assertSame(['topic' => 'payment', 'id' => '123'], $request->query->all());

      $forged_request = $this->webhookRequest('wrong-test-secret');
      $forged_event = new RequestEvent($this->container->get('kernel'), $forged_request, HttpKernelInterface::MAIN_REQUEST);
      $this->container->get('aculta_pagamentos_mercadopago.webhook_subscriber')->onKernelRequest($forged_event);
      $this->assertSame(401, $forged_event->getResponse()?->getStatusCode());

      // The Portal invariant keeps this gateway enabled even if another
      // configuration write attempts to disable it.
      $gateway->setStatus(FALSE);
      $gateway->save();
      $this->assertTrue($gateway->status());
      $enabled_request = $this->webhookRequest('kernel-test-only-secret');
      $enabled_event = new RequestEvent($this->container->get('kernel'), $enabled_request, HttpKernelInterface::MAIN_REQUEST);
      $this->container->get('aculta_pagamentos_mercadopago.webhook_subscriber')->onKernelRequest($enabled_event);
      $this->assertNull($enabled_event->getResponse());
    }
    finally {
      $previous_secret === FALSE ? putenv('MERCADOPAGO_WEBHOOK_SECRET') : putenv('MERCADOPAGO_WEBHOOK_SECRET=' . $previous_secret);
      $previous_public_key === FALSE ? putenv('MERCADOPAGO_TEST_PUBLIC_KEY') : putenv('MERCADOPAGO_TEST_PUBLIC_KEY=' . $previous_public_key);
      $previous_access_token === FALSE ? putenv('MERCADOPAGO_TEST_ACCESS_TOKEN') : putenv('MERCADOPAGO_TEST_ACCESS_TOKEN=' . $previous_access_token);
    }
  }

  /**
   * Creates a synthetic signed native webhook request.
   */
  private function webhookRequest(string $signing_secret): Request {
    $timestamp = (string) (int) (microtime(TRUE) * 1000);
    $request_id = 'kernel-test-request';
    $hash = hash_hmac('sha256', 'id:123;request-id:' . $request_id . ';ts:' . $timestamp . ';', $signing_secret);
    $request = Request::create('/commerce_payment/notify/mercado_pago?data.id=123&type=payment', 'POST');
    $request->attributes->set('_route', 'commerce_payment.notify');
    $request->attributes->set('commerce_payment_gateway', 'mercado_pago');
    $request->headers->set('x-signature', 'ts=' . $timestamp . ',v1=' . $hash);
    $request->headers->set('x-request-id', $request_id);
    return $request;
  }

}
