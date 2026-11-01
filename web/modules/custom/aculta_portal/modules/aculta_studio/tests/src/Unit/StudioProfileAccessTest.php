<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Access\StudioProfileAccess;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The public-profile owner must be installed before Studio edit forms open.
 */
#[CoversClass(StudioProfileAccess::class)]
#[Group('aculta_studio')]
final class StudioProfileAccessTest extends UnitTestCase {

  /**
   * Requires the owner module and the account permission before access.
   */
  public function testOwnerRequiredAndAccountAuthorized(): void {
    $disabled = $this->access(FALSE);
    $enabled = $this->access(TRUE);

    self::assertFalse($disabled->access($this->account(TRUE, TRUE))->isAllowed());
    self::assertFalse($enabled->access($this->account(FALSE, TRUE))->isAllowed());
    self::assertFalse($enabled->access($this->account(TRUE, FALSE))->isAllowed());
    $allowed = $enabled->access($this->account(TRUE, TRUE));
    self::assertTrue($allowed->isAllowed());
    self::assertContains('user', $allowed->getCacheContexts());
  }

  /**
   * Builds the access service with the owner module enabled or disabled.
   */
  private function access(bool $enabled): StudioProfileAccess {
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $modules->method('moduleExists')->with('aculta_user')->willReturn($enabled);
    return new StudioProfileAccess($modules);
  }

  /**
   * Builds an account double with the given authentication and permission.
   */
  private function account(bool $authenticated, bool $permission): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('hasPermission')->with('access aculta portal')->willReturn($permission);
    return $account;
  }

}
