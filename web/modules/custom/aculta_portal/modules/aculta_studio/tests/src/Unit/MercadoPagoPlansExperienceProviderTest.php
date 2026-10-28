<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Experience\MercadoPagoPlansExperienceProvider;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Studio entrypoint and its owner permission. */
#[CoversClass(MercadoPagoPlansExperienceProvider::class)]
#[Group('aculta_studio')]
final class MercadoPagoPlansExperienceProviderTest extends UnitTestCase {

  /**
   * Verifies the stable identity and optional module availability.
   */
  public function testIdentityAndAvailability(): void {
    $provider = $this->provider(TRUE);
    self::assertSame('mercado_pago_plans', $provider->id());
    self::assertSame('Planos Mercado Pago', $provider->label());
    self::assertSame('account', $provider->purpose());
    self::assertSame('aculta_pagamentos_mercadopago.studio_plans', $provider->routeName());
    self::assertTrue($provider->available());
    self::assertSame($provider->routeName(), $provider->build()['link']['#url']->getRouteName());
    self::assertFalse($this->provider(FALSE)->available());
  }

  /**
   * Verifies authenticated portal and donation-admin access requirements.
   */
  public function testAccessRequiresAuthenticatedDonationAdministrator(): void {
    self::assertTrue($this->provider(TRUE)->access($this->account(TRUE, TRUE, TRUE))->isAllowed());
    self::assertFalse($this->provider(TRUE)->access($this->account(FALSE, TRUE, TRUE))->isAllowed());
    self::assertFalse($this->provider(TRUE)->access($this->account(TRUE, FALSE, TRUE))->isAllowed());
    self::assertFalse($this->provider(TRUE)->access($this->account(TRUE, TRUE, FALSE))->isAllowed());
    self::assertFalse($this->provider(FALSE)->access($this->account(TRUE, TRUE, TRUE))->isAllowed());
  }

  /**
   * Creates a provider with the owner module enabled or disabled.
   */
  private function provider(bool $enabled): MercadoPagoPlansExperienceProvider {
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $modules->method('moduleExists')->with('aculta_pagamentos_mercadopago')->willReturn($enabled);
    return new MercadoPagoPlansExperienceProvider($modules);
  }

  /**
   * Creates an account with selected permissions and authentication state.
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
