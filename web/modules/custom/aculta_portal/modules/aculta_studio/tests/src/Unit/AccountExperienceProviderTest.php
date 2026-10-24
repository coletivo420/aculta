<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Experience\AccountExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the personal experience metadata, access and safe account links.
 */
#[CoversClass(AccountExperienceProvider::class)]
#[Group('aculta_studio')]
final class AccountExperienceProviderTest extends UnitTestCase {

  /**
   * The account entry is registered for the account purpose only.
   */
  public function testRegistryMetadataAndPurpose(): void {
    $provider = new AccountExperienceProvider();
    $manager = new StudioExperienceManager();
    $manager->addProvider($provider);

    self::assertSame('conta', $provider->id());
    self::assertSame('Minha Conta', $provider->label());
    self::assertSame('account', $provider->purpose());
    self::assertSame('aculta_studio.account', $provider->routeName());
    self::assertTrue($provider->available());
    self::assertSame($provider, $manager->get('conta'));

    $account = $this->createMock(AccountInterface::class);
    self::assertFalse($manager->access('conta', 'forum', $account)->isAllowed());
  }

  /**
   * A visitor or a member without Portal permission cannot access the entry.
   */
  public function testRequiresAuthenticationAndPermission(): void {
    $provider = new AccountExperienceProvider();
    $visitor = $this->createMock(AccountInterface::class);
    $visitor->method('isAuthenticated')->willReturn(FALSE);
    $visitor->method('hasPermission')->willReturn(TRUE);
    self::assertFalse($provider->access($visitor)->isAllowed());

    $restricted = $this->createMock(AccountInterface::class);
    $restricted->method('isAuthenticated')->willReturn(TRUE);
    $restricted->method('hasPermission')->willReturn(FALSE);
    self::assertFalse($provider->access($restricted)->isAllowed());

    $member = $this->createMock(AccountInterface::class);
    $member->method('isAuthenticated')->willReturn(TRUE);
    $member->method('hasPermission')->with('access aculta portal')->willReturn(TRUE);
    $access = $provider->access($member);
    self::assertTrue($access->isAllowed());
    self::assertContains('user', $access->getCacheContexts());
  }

  /**
   * The new entry links to existing forms, never to /minha-conta.
   */
  public function testLinksReuseExistingAccountRoutes(): void {
    $build = (new AccountExperienceProvider())->build();

    self::assertSame('studio-account-home', $build['#attributes']['data-portal-ui']);
    self::assertSame('item_list', $build['sections']['#theme']);
    $routes = array_map(static fn (array $link): string =>
      $link['#url']->getRouteName(), $build['sections']['#items']);
    self::assertSame([
      'aculta_portal.my_data',
      'aculta_studio.account_address',
      'aculta_studio.account_documents',
      'aculta_studio.account_security',
      'aculta_studio.account_password',
      'aculta_studio.account_connections',
      'aculta_portal.support_my',
      'aculta_portal.account_settings',
      'aculta_portal.dashboard',
    ], $routes);
    self::assertContains('user', $build['#cache']['contexts']);
  }

}
