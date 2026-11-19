<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_studio\Plugin\MarleyTab\RevistaTab;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Exercises the Revista tab behaviour instead of reading its source strings.
 *
 * The previous version grepped RevistaTab.php and MarleyListingBuilder.php for
 * substrings. This runs the tab: module availability, the role-based access
 * gate with its cache metadata, and the sections (author scoping, create and
 * moderation placement) that the sidebar contract depends on.
 */
#[Group('aculta_studio')]
final class RevistaMyWorkContractTest extends UnitTestCase {

  /**
   * Restores the global container after the Url-backed sections test.
   */
  protected function tearDown(): void {
    \Drupal::setContainer(new ContainerBuilder());
    parent::tearDown();
  }

  /**
   * The tab is available only when the Magazine module is enabled.
   */
  public function testAvailabilityFollowsMagazineModule(): void {
    $handler = $this->createMock(ModuleHandlerInterface::class);
    $handler->method('moduleExists')->with('aculta_magazine')->willReturn(TRUE);
    self::assertTrue($this->tab($handler, $this->config())->isAvailable());

    $handler = $this->createMock(ModuleHandlerInterface::class);
    $handler->method('moduleExists')->with('aculta_magazine')->willReturn(FALSE);
    self::assertFalse($this->tab($handler, $this->config())->isAvailable());
  }

  /**
   * Any configured editorial capability grants access; outsiders are denied.
   */
  public function testAccessFollowsConfiguredEditorialRoles(): void {
    $editor = $this->account(['content_editor']);
    $outsider = $this->account(['authenticated']);

    $allowed = $this->tab($this->moduleHandler(), $this->config())->access($editor);
    self::assertTrue($allowed->isAllowed());
    self::assertContains('user.roles', $allowed->getCacheContexts());
    self::assertContains('config:aculta_magazine.settings', $allowed->getCacheTags());

    $denied = $this->tab($this->moduleHandler(), $this->config())->access($outsider);
    self::assertFalse($denied->isAllowed());
  }

  /**
   * "Meu trabalho" stays author-scoped, with the sidebar suppressed.
   */
  public function testSectionsKeepMyWorkAuthorScopedAndSidebarOff(): void {
    $this->installUrlContainer();
    $sections = $this->tab($this->moduleHandler(), $this->config())->sections($this->account(['content_editor', 'administrator']));

    self::assertArrayHasKey('publicacoes', $sections);
    self::assertInstanceOf(TranslatableMarkup::class, $sections['publicacoes']['label']);
    self::assertSame('Meu trabalho', $sections['publicacoes']['label']->getUntranslatedString());
    self::assertTrue($sections['publicacoes']['suppress_sidebar']);

    // Every listing under "Meu trabalho" is scoped to the current author.
    foreach ($sections['publicacoes']['tabs'] as $tab) {
      self::assertSame('mine', $tab['listing']['scope']);
      self::assertSame('revista', $tab['listing']['bundle']);
    }

    $states = array_map(
      static fn (array $tab): ?string => $tab['listing']['state'],
      $sections['publicacoes']['tabs'],
    );
    self::assertContains('draft', $states);
    self::assertContains('review', $states);
    self::assertContains('published', $states);
    self::assertContains('archived', $states);

    // Creation is offered as an action, not as a sidebar section.
    $action = $sections['publicacoes']['actions'][0];
    self::assertSame('aculta_magazine.studio_add_popup', $action['url']->getRouteName());
    self::assertSame('conteudo-criar', $action['attributes']['data-action']);

    self::assertArrayHasKey('criar', $sections);
    self::assertFalse($sections['criar']['sidebar']);
    self::assertTrue($sections['criar']['suppress_sidebar']);
  }

  /**
   * The moderation tab appears only for accounts allowed to moderate comments.
   */
  public function testModerationSectionRequiresCommentModerator(): void {
    $this->installUrlContainer();

    $moderator = $this->account(['administrator'], TRUE);
    $withModeration = $this->tab($this->moduleHandler(), $this->config())->sections($moderator);
    self::assertArrayHasKey('comentarios', $withModeration);
    self::assertSame('Comentários e moderação', $withModeration['comentarios']['label']->getUntranslatedString());
    self::assertFalse($withModeration['comentarios']['sidebar']);
    self::assertSame('comment', $withModeration['comentarios']['tabs']['pendentes']['listing']['entity']);

    $editor = $this->account(['content_editor']);
    $withoutModeration = $this->tab($this->moduleHandler(), $this->config())->sections($editor);
    self::assertArrayNotHasKey('comentarios', $withoutModeration);
  }

  /**
   * Builds the tab with the requested collaborators.
   */
  private function tab(ModuleHandlerInterface $handler, ConfigFactoryInterface $config): RevistaTab {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(
      static fn (string $string): string => $string,
    );
    return new RevistaTab(
      [],
      'revista',
      [],
      $handler,
      $translation,
      new DomainSiteManager(
        $this->createMock(EntityTypeManagerInterface::class),
        $this->createMock(DomainNegotiatorInterface::class),
        $config,
        new RequestStack(),
      ),
      $this->createMock(EntityTypeManagerInterface::class),
      $config,
    );
  }

  /**
   * Builds a module handler that reports the Magazine module as present.
   */
  private function moduleHandler(): ModuleHandlerInterface {
    $handler = $this->createMock(ModuleHandlerInterface::class);
    $handler->method('moduleExists')->with('aculta_magazine')->willReturn(TRUE);
    return $handler;
  }

  /**
   * Builds the Magazine settings configuration.
   */
  private function config(): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(
      static fn (string $key): array => match ($key) {
        'authoring_roles', 'review_roles' => ['content_editor'],
        'comments_roles' => ['administrator'],
        default => [],
      },
    );
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('aculta_magazine.settings')->willReturn($config);
    return $factory;
  }

  /**
   * Builds an account with the given roles and optional moderation permission.
   */
  private function account(array $roles, bool $canModerate = FALSE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('getRoles')->willReturn($roles);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => $canModerate && $permission === 'administer comments',
    );
    return $account;
  }

  /**
   * Provides the Url services that sections() needs without a full Drupal boot.
   */
  private function installUrlContainer(): void {
    $container = new ContainerBuilder();
    $access = $this->createMock(AccessManagerInterface::class);
    $access->method('checkNamedRoute')->willReturn(TRUE);
    $container->set('access_manager', $access);
    $generator = $this->createMock(UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturn('/gerado');
    $container->set('url_generator', $generator);
    \Drupal::setContainer($container);
  }

}
