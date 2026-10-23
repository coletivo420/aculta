<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Access\GreenStashAccessPolicy;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\aculta_commerce\Access\GreenStashAccessPolicy
 *
 * @group aculta_commerce
 */
final class GreenStashAccessPolicyTest extends UnitTestCase {

  /**
   * @dataProvider cases
   */
  public function testExplicitPermissionGate(bool $granted): void {
    $account = $this->createMock(AccountInterface::class);
    $account->expects(self::once())
      ->method('hasPermission')
      ->with(GreenStashAccessPolicy::ACCESS)
      ->willReturn($granted);

    $result = (new GreenStashAccessPolicy())->access($account);
    self::assertSame($granted, $result->isAllowed());
    self::assertContains('user.permissions', $result->getCacheContexts());
  }

  public static function cases(): array {
    return [
      'granted' => [TRUE],
      'denied' => [FALSE],
    ];
  }

}
