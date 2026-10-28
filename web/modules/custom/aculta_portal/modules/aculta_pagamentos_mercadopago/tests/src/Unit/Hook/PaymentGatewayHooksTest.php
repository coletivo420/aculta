<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Hook;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_pagamentos_mercadopago\Hook\PaymentGatewayEntityHooks;
use Drupal\Core\Entity\EntityInterface;
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

}
