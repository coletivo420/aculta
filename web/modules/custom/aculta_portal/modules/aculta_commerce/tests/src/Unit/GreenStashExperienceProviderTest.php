<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Access\GreenStashAccessPolicy;
use Drupal\aculta_commerce\Experience\GreenStashExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @group aculta_commerce
 */
final class GreenStashExperienceProviderTest extends UnitTestCase {

  public function testExperienceContractAndNoDataExposure(): void {
    $provider = new GreenStashExperienceProvider(new GreenStashAccessPolicy());

    self::assertInstanceOf(StudioExperienceProviderInterface::class, $provider);
    self::assertSame('green_stash', $provider->id());
    self::assertSame('Green Stash', $provider->label());
    self::assertSame('account', $provider->purpose());
    self::assertSame('aculta_commerce.green_stash', $provider->routeName());
    self::assertTrue($provider->available());

    $render = $provider->build();
    self::assertSame('container', $render['#type']);
    self::assertSame('green-stash-entry', $render['#attributes']['data-portal-ui']);
    self::assertArrayNotHasKey('orders', $render);
    self::assertArrayNotHasKey('products', $render);
  }

  /**
   * @dataProvider permissionCases
   */
  public function testPermissionIsRequired(bool $granted): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->with(GreenStashAccessPolicy::ACCESS)
      ->willReturn($granted);
    $provider = new GreenStashExperienceProvider(new GreenStashAccessPolicy());
    self::assertSame($granted, $provider->access($account)->isAllowed());
  }

  public static function permissionCases(): array {
    return [
      'allowed' => [TRUE],
      'denied' => [FALSE],
    ];
  }

}
