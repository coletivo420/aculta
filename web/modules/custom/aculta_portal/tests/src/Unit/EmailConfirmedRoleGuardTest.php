<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Account\EmailConfirmedRoleGuard;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Invariável do papel email_confirmed: remoção é detectada; concessão e conta nova não.
 */
#[Group('aculta_portal')]
class EmailConfirmedRoleGuardTest extends UnitTestCase {

  /**
   * Conta com os papéis informados.
   */
  private function account(array $roles): UserInterface {
    $account = $this->createMock(UserInterface::class);
    $account->method('hasRole')->willReturnCallback(static fn (string $role): bool => in_array($role, $roles, TRUE));
    return $account;
  }

  /**
   * Remoção do papel é detectada.
   */
  public function testRemocaoEDetectada(): void {
    $this->assertTrue(EmailConfirmedRoleGuard::isRemoval($this->account(['authenticated']), $this->account(['authenticated', 'email_confirmed'])));
  }

  /**
   * Papel mantido ou concedido não é remoção.
   */
  public function testMantidoOuConcedidoNaoEhRemocao(): void {
    $this->assertFalse(EmailConfirmedRoleGuard::isRemoval($this->account(['email_confirmed']), $this->account(['email_confirmed'])));
    $this->assertFalse(EmailConfirmedRoleGuard::isRemoval($this->account(['email_confirmed']), $this->account([])));
  }

  /**
   * Conta nova (sem original) não é remoção.
   */
  public function testContaNovaNaoEhRemocao(): void {
    $this->assertFalse(EmailConfirmedRoleGuard::isRemoval($this->account([]), NULL));
  }

}
