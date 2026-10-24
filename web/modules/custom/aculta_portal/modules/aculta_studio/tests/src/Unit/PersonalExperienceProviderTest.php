<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Experience\PersonalExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the personal experience metadata, access and safe account links.
 */
#[CoversClass(PersonalExperienceProvider::class)]
#[Group('aculta_studio')]
final class PersonalExperienceProviderTest extends UnitTestCase {

  /**
   * The personal entry is registered for the account purpose only.
   */
  public function testRegistryMetadataAndPurpose(): void {
    $provider = new PersonalExperienceProvider();
    $manager = new StudioExperienceManager();
    $manager->addProvider($provider);

    self::assertSame('pessoal', $provider->id());
    self::assertSame('Meu Espaço', $provider->label());
    self::assertSame('account', $provider->purpose());
    self::assertSame('aculta_studio.personal', $provider->routeName());
    self::assertTrue($provider->available());
    self::assertSame($provider, $manager->get('pessoal'));

    $account = $this->createMock(AccountInterface::class);
    self::assertFalse($manager->access('pessoal', 'forum', $account)->isAllowed());
  }

  /**
   * A visitor or a member without Portal permission cannot access the entry.
   */
  public function testRequiresAuthenticationAndPermission(): void {
    $provider = new PersonalExperienceProvider();
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
    $build = (new PersonalExperienceProvider())->build();

    self::assertSame('studio-personal-entry', $build['#attributes']['data-portal-ui']);
    self::assertSame('item_list', $build['links']['#theme']);
    $routes = array_map(static fn (array $link): string =>
      $link['#url']->getRouteName(), $build['links']['#items']);
    self::assertSame([
      '<front>',
      'aculta_studio.personal_profile',
      'aculta_portal.my_data',
      'aculta_portal.security',
      'aculta_portal.account_settings',
    ], $routes);
    self::assertContains('user', $build['#cache']['contexts']);
  }

}
