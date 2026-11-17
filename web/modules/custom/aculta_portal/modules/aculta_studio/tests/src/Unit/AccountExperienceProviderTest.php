<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Experience\AccountExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
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
    // Url::fromRoute() precisa de um url_generator registrado nos testes
    // unitários.
    $generator = $this->createMock(UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturn('/conta-teste');
    $container = new ContainerBuilder();
    $container->set('url_generator', $generator);
    $container->set('string_translation', $this->getStringTranslationStub());
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

    self::assertSame('/conta-teste', $build['#props']['account_url']);
    self::assertSame('Minha conta', $build['#props']['account_link_title']);
    self::assertSame([], $build['#slots']['profile_link']);
    self::assertContains('user', $build['#cache']['contexts']);
    self::assertSame('Studio Dashboard', $build['#slots']['menu']['#props']['title']);
    self::assertArrayNotHasKey('items', $build['#slots']['menu']['#props']);
    self::assertSame(['sidebar_items' => [], 'dialog_items' => []], $build['#slots']['menu']['#slots']);
  }

  /**
   * Editorial copy is configurable while private slots remain provider-owned.
   */
  public function testDashboardUsesConfiguredPresentationCopyOnly(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['experiences_title', 'O que você pode fazer'],
      ['experiences_description', 'Acesse suas áreas.'],
      ['account_link_title', 'Seus dados'],
      ['account_link_description', 'Segurança e preferências'],
      ['account_link_label', 'Configurações da conta'],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects(self::exactly(5))
      ->method('get')
      ->with('aculta_studio.account_presentation')
      ->willReturn($config);

    $build = (new AccountExperienceProvider(configFactory: $config_factory))->build();

    self::assertSame('O que você pode fazer', $build['#props']['experiences_title']);
    self::assertSame('Acesse suas áreas.', $build['#props']['experiences_description']);
    self::assertSame('Seus dados', $build['#props']['account_link_title']);
    self::assertSame('Segurança e preferências', $build['#props']['account_link_description']);
    self::assertSame('Configurações da conta', $build['#props']['account_link_label']);
    self::assertFalse($build['#props']['show_identity']);
    self::assertSame([], $build['#slots']['profile_link']);
  }

}
