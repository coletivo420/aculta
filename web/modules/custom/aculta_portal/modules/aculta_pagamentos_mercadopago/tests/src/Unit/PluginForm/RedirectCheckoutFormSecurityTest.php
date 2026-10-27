<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\PluginForm;

use Drupal\aculta_pagamentos_mercadopago\PluginForm\Checkout\RedirectCheckoutForm;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies Mercado Pago checkout diagnostics do not expose customer data.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class RedirectCheckoutFormSecurityTest extends UnitTestCase {

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
