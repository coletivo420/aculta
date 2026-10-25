<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_lms_h5p\Plugin\views\access\aculta_H5PAccessUserPoints;
use Drupal\Tests\UnitTestCase;

/** Tests account-scoped access to the H5P results view. */
final class aculta_H5PAccessUserPointsTest extends UnitTestCase {

  public function testGlobalPermissionAllowsResultsOutsideUserProfile(): void {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRouteName')->willReturn('view.h5p_results.page');
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnMap([
      ['access all h5p results', TRUE],
    ]);

    self::assertTrue($this->plugin($routeMatch)->access($account));
  }

  public function testOwnResultsRequireCanonicalProfileAndOwnPermission(): void {
    $routeAccount = $this->account('42');
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRouteName')->willReturn('entity.user.canonical');
    $routeMatch->method('getParameter')->with('user')->willReturn($routeAccount);
    $viewer = $this->account('42', ['access own h5p results']);

    self::assertTrue($this->plugin($routeMatch)->access($viewer));
    self::assertFalse($this->plugin($routeMatch)->access($this->account('7', ['access own h5p results'])));
    self::assertFalse($this->plugin($routeMatch)->access($this->account('42')));
  }

  public function testOwnPermissionDoesNotGrantAccessOnOtherRoutes(): void {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRouteName')->willReturn('view.h5p_results.page');

    self::assertFalse($this->plugin($routeMatch)->access($this->account('42', ['access own h5p results'])));
  }

  private function plugin(RouteMatchInterface $routeMatch): aculta_H5PAccessUserPoints {
    return new aculta_H5PAccessUserPoints([], 'h5paccessuserpoints', [], $routeMatch);
  }

  /** Creates an account double with only the requested permissions. */
  private function account(string $id, array $permissions = []): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($id);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, $permissions, TRUE),
    );
    return $account;
  }

}
