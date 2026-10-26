<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Hook;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_pagamentos_mercadopago\Hook\PaymentGatewayEntityHooks;
use Drupal\aculta_pagamentos_mercadopago\Hook\PaymentGatewayFormHooks;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Form\PaymentGatewayForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Mercado Pago gateway administration protections.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class PaymentGatewayHooksTest extends UnitTestCase {

  /**
   * Tests that the Mercado Pago gateway cannot be edited directly.
   */
  public function testGatewayUpdateIsForbidden(): void {
    $gateway = $this->createMock(EntityInterface::class);
    $gateway->method('getEntityTypeId')->willReturn('commerce_payment_gateway');
    $gateway->method('id')->willReturn('mercado_pago');
    $result = (new PaymentGatewayEntityHooks())->entityAccess($gateway, 'update', $this->createMock(AccountInterface::class));
    $this->assertTrue($result->isForbidden());
  }

  /**
   * Tests that other gateway access remains unaffected.
   */
  public function testOtherGatewayAccessIsNeutral(): void {
    $gateway = $this->createMock(EntityInterface::class);
    $gateway->method('getEntityTypeId')->willReturn('commerce_payment_gateway');
    $gateway->method('id')->willReturn('other_gateway');
    $result = (new PaymentGatewayEntityHooks())->entityAccess($gateway, 'update', $this->createMock(AccountInterface::class));
    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests that gateway tokens are masked and unused credentials disabled.
   */
  public function testGatewayFormMasksTokensAndDisablesUnusedCredentials(): void {
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('id')->willReturn('mercado_pago');
    $formObject = $this->getMockBuilder(PaymentGatewayForm::class)->disableOriginalConstructor()->onlyMethods(['getEntity'])->getMock();
    $formObject->method('getEntity')->willReturn($gateway);
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);
    $form = [
      'configuration' => [
        'form' => [
          'credentials_test' => ['access_token_test' => ['#type' => 'textfield', '#value' => 'secret']],
          'credentials_prod' => ['access_token_prod' => ['#type' => 'textfield', '#value' => 'secret']],
          'credentials_stage' => [
            'public_key_stage' => ['#value' => 'secret'],
            'access_token_stage' => ['#value' => 'secret'],
          ],
          'client_id' => ['#value' => 'secret'],
          'client_secret' => ['#value' => 'secret'],
        ],
      ],
    ];

    (new PaymentGatewayFormHooks())->formAlter($form, $formState, 'payment_gateway_edit_form');

    $this->assertSame('password', $form['configuration']['form']['credentials_test']['access_token_test']['#type']);
    $this->assertSame('', $form['configuration']['form']['credentials_test']['access_token_test']['#default_value']);
    $this->assertArrayNotHasKey('#value', $form['configuration']['form']['credentials_prod']['access_token_prod']);
    $this->assertTrue($form['configuration']['form']['credentials_stage']['public_key_stage']['#disabled']);
    $this->assertTrue($form['configuration']['form']['credentials_stage']['access_token_stage']['#disabled']);
    $this->assertTrue($form['configuration']['form']['client_id']['#disabled']);
    $this->assertTrue($form['configuration']['form']['client_secret']['#disabled']);
  }

}
