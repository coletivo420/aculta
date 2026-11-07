<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Experience\AccountExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Url::fromRoute() precisa de um url_generator registrado nos testes unitários.
    $generator = $this->createMock(UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturn('/conta-teste');
    $container = new ContainerBuilder();
    $container->set('url_generator', $generator);
    \Drupal::setContainer($container);
  }

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
  public function testDashboardLinksSeparatePrivateAccountAndPublicProfile(): void {
    $build = (new AccountExperienceProvider())->build();

    self::assertSame('aculta_studio_account', $build['#theme']);
    self::assertSame('/conta-teste', $build['#account_url']);
    self::assertSame('Minha conta', (string) $build['#account_link_title']);
    self::assertSame([], $build['#profile_link']);
    self::assertContains('user', $build['#cache']['contexts']);
    self::assertSame('Studio Dashboard', $build['#menu_title']);
  }

  /**
   * Official Suite names and canonical routes are kept.
   */
  public function testSuiteLinksAreAddedOnlyAfterAuthorization(): void {
    $provider = new AccountExperienceProvider();
    $build = $provider->build();

    // The provider alone has no authority to enumerate suites. The dashboard
    // controller adds only manager-resolved links after access checks.
    self::assertSame([], $build['#experiences']);
  }

}
