<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_ui_library\Unit;

use Drupal\aculta_ui_library\Access\UiLibraryAccessCheck;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Valida os testes negativos e positivos do controle de acesso da UI Library.
 */
final class UiLibraryAccessCheckTest extends UnitTestCase {

  private UiLibraryAccessCheck $accessCheck;

  protected function setUp(): void {
    parent::setUp();
    $this->accessCheck = new UiLibraryAccessCheck();
  }

  /**
   * Testa que visitante (anônimo) é negado.
   */
  public function testAnonymousAccessDenied(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(0);
    $account->method('getRoles')->willReturn(['anonymous']);
    $account->method('hasPermission')->with('administer blocks')->willReturn(FALSE);

    $result = $this->accessCheck->access($account);
    self::assertFalse($result->isAllowed());
  }

  /**
   * Testa que usuário autenticado comum é negado.
   */
  public function testAuthenticatedCommonUserDenied(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(42);
    $account->method('getRoles')->willReturn(['authenticated']);
    $account->method('hasPermission')->with('administer blocks')->willReturn(FALSE);

    $result = $this->accessCheck->access($account);
    self::assertFalse($result->isAllowed());
  }

  /**
   * Testa que editor de conteúdo comum é negado.
   */
  public function testContentEditorDenied(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(50);
    $account->method('getRoles')->willReturn(['authenticated', 'content_editor']);
    $account->method('hasPermission')->with('administer blocks')->willReturn(FALSE);

    $result = $this->accessCheck->access($account);
    self::assertFalse($result->isAllowed());
  }

  /**
   * Testa que usuário com 'administer blocks' mas SEM papel de administrador é negado.
   */
  public function testPermissionWithoutAdminRoleDenied(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(88);
    $account->method('getRoles')->willReturn(['authenticated', 'content_editor']);
    $account->method('hasPermission')->with('administer blocks')->willReturn(TRUE);

    $result = $this->accessCheck->access($account);
    self::assertFalse($result->isAllowed(), 'Usuário com permissão administer blocks sem papel de administrador deve ser negado.');
  }

  /**
   * Testa que administrador com papel 'administrator' e permissão é permitido.
   */
  public function testAdministratorRoleAllowed(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(15);
    $account->method('getRoles')->willReturn(['authenticated', 'administrator']);
    $account->method('hasPermission')->with('administer blocks')->willReturn(TRUE);

    $result = $this->accessCheck->access($account);
    self::assertTrue($result->isAllowed());
  }

  /**
   * Testa que superusuário UID 1 com permissão é permitido.
   */
  public function testUid1Allowed(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(1);
    $account->method('getRoles')->willReturn(['authenticated', 'administrator']);
    $account->method('hasPermission')->with('administer blocks')->willReturn(TRUE);

    $result = $this->accessCheck->access($account);
    self::assertTrue($result->isAllowed());
  }

}
