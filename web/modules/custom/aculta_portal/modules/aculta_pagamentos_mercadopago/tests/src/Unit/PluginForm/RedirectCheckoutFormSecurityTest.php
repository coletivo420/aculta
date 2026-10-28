<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\PluginForm;

use Drupal\aculta_pagamentos_mercadopago\PluginForm\Checkout\RedirectCheckoutForm;
use Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway\CheckoutPro;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use MercadoPago\Client\Common\RequestOptions;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies Mercado Pago checkout diagnostics do not expose customer data.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class RedirectCheckoutFormSecurityTest extends UnitTestCase {

  /**
   * Checkout preferences keep pending and in-process provider states enabled.
   */
  public function testPreferenceDoesNotEnableBinaryMode(): void {
    $method = new \ReflectionMethod(RedirectCheckoutForm::class, 'applyPreferenceDefaults');
    $request = $method->invoke(NULL, ['binary_mode' => TRUE, 'auto_return' => 'wrong', 'external_reference' => '42']);

    $this->assertFalse($request['binary_mode']);
    $this->assertSame('approved', $request['auto_return']);
    $this->assertSame('42', $request['external_reference']);
  }

  /**
   * Reuses the provider idempotency key for the same order and request only.
   */
  public function testPreferenceCreationUsesStableOrderAndPayloadKey(): void {
    $keys = [];
    $requests = [];
    $client = new class($keys, $requests) {

      public function __construct(private array &$keys, private array &$requests) {}

      /** Captures only the synthetic payload and idempotency key. */
      public function create(array $request, RequestOptions $options): object {
        $headers = $options->getCustomHeaders() ?? [];
        $this->keys[] = $headers['x-idempotency-key'] ?? '';
        $this->requests[] = $request;
        return (object) ['id' => 'preference-test'];
      }

    };
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $factory = $this->createMock(MercadoPagoClientFactory::class);
    $factory->expects($this->exactly(4))
      ->method('criar')
      ->with('preferencias', $gateway)
      ->willReturn($client);
    $form = (new \ReflectionClass(RedirectCheckoutForm::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty(RedirectCheckoutForm::class, 'clientFactory'))->setValue($form, $factory);
    $method = new \ReflectionMethod(RedirectCheckoutForm::class, 'createPreference');
    $request = ['external_reference' => '42', 'items' => [['id' => 'sample', 'unit_price' => 10.0]]];

    $first = $method->invoke($form, $gateway, $request, '42');
    $retry = $method->invoke($form, $gateway, $request, '42');
    $changed_request = [
      'external_reference' => '42',
      'items' => [['id' => 'sample', 'unit_price' => 12.0]],
    ];
    $changed = $method->invoke($form, $gateway, $changed_request, '42');
    $other_order = $method->invoke($form, $gateway, $request, '43');

    $this->assertSame('preference-test', $first->id);
    $this->assertSame('preference-test', $retry->id);
    $this->assertSame('preference-test', $changed->id);
    $this->assertSame('preference-test', $other_order->id);
    $this->assertNotSame('', $keys[0]);
    $this->assertSame($keys[0], $keys[1]);
    $this->assertNotSame($keys[0], $keys[2]);
    $this->assertNotSame($keys[0], $keys[3]);
    $this->assertSame($request, $requests[0]);
  }

  /**
   * Checkout only exposes a scalar preference ID and trusted provider URL.
   */
  public function testPreferenceRedirectRequiresValidIdAndMercadoPagoOrigin(): void {
    $method = new \ReflectionMethod(RedirectCheckoutForm::class, 'preferenceRedirect');
    $method->setAccessible(TRUE);

    $valid = $method->invoke(NULL, (object) [
      'id' => 'preference-1',
      'init_point' => 'https://www.mercadopago.com/mla/checkout/start?pref_id=preference-1',
    ]);
    $this->assertSame([
      'id' => 'preference-1',
      'url' => 'https://www.mercadopago.com/mla/checkout/start?pref_id=preference-1',
    ], $valid);
    $regional = $method->invoke(NULL, (object) [
      'id' => 'subscription-1',
      'init_point' => 'https://www.mercadopago.com.ar/subscriptions/checkout?preapproval_id=subscription-1',
    ]);
    $this->assertNotNull($regional);
    $this->assertNull($method->invoke(NULL, (object) [
      'id' => 'preference-1',
      'init_point' => 'https://mercadopago.com.attacker.invalid/checkout',
    ]));
    $this->assertNull($method->invoke(NULL, (object) [
      'id' => ['malformed'],
      'init_point' => 'https://www.mercadopago.com/checkout',
    ]));
  }

  /**
   * Refuses checkout before provider calls when the active token is missing.
   */
  public function testMissingRuntimeTokenFailsClosedBeforePreferenceCreation(): void {
    $plugin = $this->getMockBuilder(CheckoutPro::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getAccessToken', 'getConfiguration'])
      ->getMock();
    $plugin->expects($this->once())->method('getAccessToken')->willReturn('');
    $plugin->expects($this->once())->method('getConfiguration')->willReturn([]);

    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->expects($this->once())->method('getPlugin')->willReturn($plugin);
    $payment = $this->createMock(PaymentInterface::class);
    $payment->expects($this->once())->method('getPaymentGateway')->willReturn($gateway);
    $payment->expects($this->never())->method('getOrder');

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addError')
      ->with($this->callback(static fn ($message): bool => $message instanceof TranslatableMarkup
        && $message->getUntranslatedString() === 'Mercado Pago is not configured. Please contact the administrator.'));
    $form = new RedirectCheckoutForm(
      $this->createMock(ConfigFactoryInterface::class),
      $messenger,
      $this->createMock(FileUrlGeneratorInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
    );
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(static fn (string $message): string => $message);
    $form->setStringTranslation($translation);
    $form->setEntity($payment);

    $result = $form->buildConfigurationForm(
      ['#return_url' => '/return', '#cancel_url' => '/cancel'],
      $this->createMock(FormStateInterface::class),
    );

    $this->assertArrayNotHasKey('#action', $result);
  }

  /**
   * Debug data keeps the preference summary and removes payer personal data.
   */
  public function testDebugRequestOmitsPayerDetails(): void {
    $request = [
      'external_reference' => '42',
      'items' => [['title' => 'Produto de teste']],
      'payer' => [
        'name' => 'PERSONAL_NAME_SENTINEL',
        'email' => 'PRIVATE_EMAIL_SENTINEL',
        'address' => ['street_name' => 'PRIVATE_ADDRESS_SENTINEL'],
      ],
    ];
    $method = new \ReflectionMethod(RedirectCheckoutForm::class, 'sanitizePreferenceDebugRequest');
    $safe_request = $method->invoke(NULL, $request);

    $this->assertSame('42', $safe_request['external_reference']);
    $this->assertArrayNotHasKey('payer', $safe_request);
    $this->assertStringNotContainsString('PRIVATE_', serialize($safe_request));
    $this->assertStringNotContainsString('PERSONAL_', serialize($safe_request));
  }

  /**
   * Preference API failures log only their status, not exception text.
   */
  public function testPreferenceFailureLoggerUsesSafeStatusOnly(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Mercado Pago preference creation failed (HTTP @status).', ['@status' => 503]);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->expects($this->once())
      ->method('get')
      ->with('aculta_pagamentos_mercadopago')
      ->willReturn($logger);

    $form = (new \ReflectionClass(RedirectCheckoutForm::class))->newInstanceWithoutConstructor();
    $logger_factory = new \ReflectionProperty(RedirectCheckoutForm::class, 'loggerFactory');
    $logger_factory->setValue($form, $factory);
    $method = new \ReflectionMethod(RedirectCheckoutForm::class, 'logPreferenceFailure');
    $method->invoke($form, 503);
  }

}
