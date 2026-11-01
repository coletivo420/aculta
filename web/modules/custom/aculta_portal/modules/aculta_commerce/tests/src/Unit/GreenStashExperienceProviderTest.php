<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Access\GreenStashAccessPolicy;
use Drupal\aculta_commerce\Experience\GreenStashExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('aculta_commerce')]
/**
 * Tests the integrated ACULTA Studio Green Stash experience provider.
 *
 * @coversDefaultClass \\Drupal\\aculta_commerce\\Experience\\GreenStashExperienceProvider
 */
final class GreenStashExperienceProviderTest extends UnitTestCase {

  /**
   * Tests the provider contract without exposing operational data.
   */
  public function testExperienceContractAndNoDataExposure(): void {
    $provider = new GreenStashExperienceProvider(new GreenStashAccessPolicy());

    self::assertInstanceOf(StudioExperienceProviderInterface::class, $provider);
    self::assertSame('green_stash', $provider->id());
    self::assertSame('ACULTA STUDIO GREEN STASH', $provider->label());
    self::assertSame('shop', $provider->purpose());
    self::assertSame('aculta_commerce.green_stash', $provider->routeName());
    self::assertTrue($provider->available());

    $render = $provider->build();
    self::assertSame('container', $render['#type']);
    self::assertSame('green-stash-entry', $render['#attributes']['data-portal-ui']);
    self::assertArrayNotHasKey('orders', $render);
    self::assertArrayNotHasKey('products', $render);
  }

  /**
   * Tests that the provider follows its access permission.
   *
   * @param bool $granted
   *   Whether access is granted.
   */
  #[DataProvider('permissionCases')]
  public function testPermissionIsRequired(bool $granted): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->with(GreenStashAccessPolicy::ACCESS)
      ->willReturn($granted);
    $provider = new GreenStashExperienceProvider(new GreenStashAccessPolicy());
    self::assertSame($granted, $provider->access($account)->isAllowed());
  }

  /**
   * Provides access permission cases.
   *
   * @return array
   *   Permission cases.
   */
  public static function permissionCases(): array {
    return [
      'allowed' => [TRUE],
      'denied' => [FALSE],
    ];
  }

}
