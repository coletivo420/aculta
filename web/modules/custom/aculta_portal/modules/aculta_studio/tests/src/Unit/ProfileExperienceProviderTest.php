<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Experience\ProfileExperienceProvider;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests independence and owner availability of the Studio Perfil experience.
 */
#[CoversClass(ProfileExperienceProvider::class)]
#[Group('aculta_studio')]
final class ProfileExperienceProviderTest extends UnitTestCase {

  public function testIndependentIdentity(): void {
    $provider = $this->provider(TRUE);
    self::assertSame('perfil', $provider->id());
    self::assertSame('Meu Perfil', $provider->label());
    self::assertSame('account', $provider->purpose());
    self::assertSame('aculta_studio.profile', $provider->routeName());
    self::assertTrue($provider->available());
    self::assertSame('aculta_studio.profile', $provider->build()['link']['#url']->getRouteName());
  }

  public function testAvailabilityAndAccess(): void {
    $member = $this->account(TRUE, TRUE);
    $visitor = $this->account(FALSE, TRUE);
    $restricted = $this->account(TRUE, FALSE);

    self::assertFalse($this->provider(FALSE)->available());
    self::assertFalse($this->provider(FALSE)->access($member)->isAllowed());
    self::assertFalse($this->provider(TRUE)->access($visitor)->isAllowed());
    self::assertFalse($this->provider(TRUE)->access($restricted)->isAllowed());
    self::assertTrue($this->provider(TRUE)->access($member)->isAllowed());
  }

  private function provider(bool $enabled): ProfileExperienceProvider {
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $modules->method('moduleExists')->with('aculta_user')->willReturn($enabled);
    return new ProfileExperienceProvider($modules);
  }

  private function account(bool $auth, bool $permission): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($auth);
    $account->method('hasPermission')->with('access aculta portal')->willReturn($permission);
    return $account;
  }

}
