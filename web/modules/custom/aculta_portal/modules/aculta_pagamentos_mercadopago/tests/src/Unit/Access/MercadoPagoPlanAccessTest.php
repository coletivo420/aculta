<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Access;

use Drupal\aculta_pagamentos_mercadopago\Access\MercadoPagoPlanAccess;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests direct route access to Mercado Pago plan administration.
 */
#[CoversClass(MercadoPagoPlanAccess::class)]
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoPlanAccessTest extends UnitTestCase {

  /**
   * Requires an authenticated Portal user with the donation-admin permission.
   */
  public function testRequiresAccountPortalAndDonationPermissions(): void {
    $access = new MercadoPagoPlanAccess();
    self::assertTrue($access->access($this->account(TRUE, TRUE, TRUE))->isAllowed());
    self::assertFalse($access->access($this->account(FALSE, TRUE, TRUE))->isAllowed());
    self::assertFalse($access->access($this->account(TRUE, FALSE, TRUE))->isAllowed());
    self::assertFalse($access->access($this->account(TRUE, TRUE, FALSE))->isAllowed());
  }

  /**
   * Creates a test account with the requested access combination.
   */
  private function account(bool $authenticated, bool $portal, bool $donations): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('hasPermission')->willReturnCallback(static fn (string $permission): bool => match ($permission) {
      'access aculta portal' => $portal,
      'administrar doacoes' => $donations,
      default => FALSE,
    });
    return $account;
  }

}
