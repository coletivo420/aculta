<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Hook;

use Drupal\aculta_pagamentos_mercadopago\Hook\EntitySaveHooks;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Mercado Pago credentials stay outside stored configuration.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class EntitySaveHooksTest extends UnitTestCase {

  /**
   * Ensures credential values are cleared before gateway configuration saves.
   */
  public function testGatewayCredentialsAreClearedBeforeSave(): void {
    $configuration = [
      'mode' => 'test',
      'country' => 'BR',
      'public_key_test' => 'test-public-key',
      'access_token_test' => 'test-access-token',
      'public_key_prod' => 'production-public-key',
      'access_token_prod' => 'production-access-token',
      'client_id' => 'client-id',
      'client_secret' => 'client-secret',
    ];
    $expected = [
      'mode' => 'test',
      'country' => 'BR',
      'public_key_test' => '',
      'access_token_test' => '',
      'public_key_prod' => '',
      'access_token_prod' => '',
      'client_id' => '',
      'client_secret' => '',
    ];
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('getEntityTypeId')->willReturn('commerce_payment_gateway');
    $gateway->method('id')->willReturn('mercado_pago');
    $gateway->method('status')->willReturn(FALSE);
    $gateway->method('getPluginConfiguration')->willReturn($configuration);
    $gateway->expects($this->once())
      ->method('setPluginConfiguration')
      ->with($expected)
      ->willReturnSelf();

    (new EntitySaveHooks())->entityPresave($gateway);
  }

}
