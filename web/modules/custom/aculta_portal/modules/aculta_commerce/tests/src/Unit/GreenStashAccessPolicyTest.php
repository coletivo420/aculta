<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Access\GreenStashAccessPolicy;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(\Drupal\aculta_commerce\Access\GreenStashAccessPolicy::class)]
#[Group('aculta_commerce')]
final class GreenStashAccessPolicyTest extends UnitTestCase {

  #[DataProvider('cases')]
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
