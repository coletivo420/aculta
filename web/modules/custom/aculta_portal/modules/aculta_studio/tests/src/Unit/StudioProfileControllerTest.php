<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Controller\StudioProfileController;
use Drupal\aculta_user\Bio\AccountBioCard;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\aculta_user\Social\SocialLinksCard;
use Drupal\aculta_user\Social\SocialNetworks;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\ProfileStorageInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Owner-only editor with SDC composition, authorized links and cache.
 */
#[CoversClass(StudioProfileController::class)]
#[Group('aculta_studio')]
final class StudioProfileControllerTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    // ACULTA User's original Bio/Social cards call Url::toString(TRUE)
    // while preparing modal links. Mock Core URL generation for Unit tests.
    $generator = $this->createMock(UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturnCallback(
      static fn (string $route): GeneratedUrl => (new GeneratedUrl())->setGeneratedUrl('/' . $route)
    );
    $container->set('url_generator', $generator);
    \Drupal::setContainer($container);
  }

  /**
   * Anonymous accounts cannot request their profile editor.
   */
  public function testAnonymousDenied(): void {
    $this->expectException(AccessDeniedHttpException::class);
    $this->controller(FALSE, TRUE, TRUE)->page();
  }

  /**
   * Logged-in members without Portal permission fail closed.
   */
  public function testPortalPermissionRequired(): void {
    $this->expectException(AccessDeniedHttpException::class);
    $this->controller(TRUE, FALSE, TRUE)->page();
  }

  /**
   * Missing user entities never render another member's profile.
   */
  public function testMissingUserDenied(): void {
    $this->expectException(AccessDeniedHttpException::class);
    $this->controller(TRUE, TRUE, FALSE)->page();
  }

  /**
   * Optional owner absence only shows identity and a valid nickname action.
   */
  public function testOwnerOnlyPageWithoutOptionalUserModule(): void {
    $page = $this->controller(TRUE, TRUE, TRUE)->page();

    self::assertSame('component', $page['#type']);
    self::assertSame('aculta420:profile-editor', $page['#component']);
    self::assertSame('studio-profile-editor', $page['#attributes']['data-portal-ui']);
    $identity = $page['#slots']['identity'];
    self::assertSame('component', $identity['#type']);
    self::assertSame('aculta420:profile-identity', $identity['#component']);
    self::assertSame('Apelido não informado', $identity['#props']['display_name']);
    self::assertStringNotContainsString('conta-privada', $identity['#props']['display_name']);
    self::assertArrayNotHasKey('avatar', $identity['#slots']);
    self::assertArrayNotHasKey('public_link', $identity['#slots']);
    self::assertSame('item_list', $identity['#slots']['actions']['#theme']);
    self::assertCount(1, $identity['#slots']['actions']['#items']);
    self::assertSame('aculta_studio.profile_nickname', $identity['#slots']['actions']['#items'][0]['#url']->getRouteName());
    self::assertArrayNotHasKey('biography', $page['#slots']);
    self::assertArrayNotHasKey('social', $page['#slots']);
    self::assertSame(0, $page['#cache']['max-age']);
    self::assertContains('user', $page['#cache']['contexts']);
    self::assertContains('user:7', $page['#cache']['tags']);
  }

  /**
   * The public link is strictly owner-granted and confined to its slot.
   */
  public function testOptionalOwnerPublicLink(): void {
    $link = $this->createMock(PublicProfileLinkInterface::class);
    $link->expects(self::once())->method('build')
      ->willReturn(Url::fromUri('https://main.example/u/membro-de-teste'));

    $page = $this->controller(TRUE, TRUE, TRUE, $link)->page();
    $identity = $page['#slots']['identity'];
    self::assertCount(1, $identity['#slots']['actions']['#items']);
    $public = $identity['#slots']['public_link'];
    self::assertSame('https://main.example/u/membro-de-teste', $public['#url']->getUri());
    self::assertSame('studio-profile-public-link', $public['#attributes']['data-portal-ui']);
  }

  /**
   * ACULTA User supplies read-only bio and network states, not Studio storage.
   *
   * The legacy modal links are removed only from the own-profile view, while
   * dedicated, accessible Form API routes become its section actions.
   */
  public function testOwnerCardsAreComposedWithoutDuplicatingTheirEditors(): void {
    $storage = $this->createMock(ProfileStorageInterface::class);
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('hasField')->willReturn(FALSE);
    $profile->method('getCacheTags')->willReturn(['profile:15']);
    $storage->method('loadByUser')->willReturn($profile);

    $account = $this->account(TRUE, TRUE);
    $user = $this->user();
    $users = $this->createMock(EntityStorageInterface::class);
    $users->method('load')->with('7')->willReturn($user);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(
      static fn (string $type) => $type === 'profile' ? $storage : $users
    );
    $bio = new AccountBioCard($entities);
    $social = new SocialLinksCard($entities, new SocialNetworks());
    $page = (new StudioProfileController($entities, $account, NULL, $bio, $social))->page();
    $identity = $page['#slots']['identity'];
    $bioSlot = $page['#slots']['biography'];
    $socialSlot = $page['#slots']['social'];

    self::assertSame('aculta420:profile-bio', $bioSlot['#component']);
    self::assertSame('aculta420:profile-social', $socialSlot['#component']);
    self::assertSame('aculta420:profile-identity', $identity['#component']);
    self::assertSame('textarea', $bioSlot['#slots']['body']['text']['#type']);
    self::assertTrue($bioSlot['#slots']['body']['text']['#disabled']);
    self::assertArrayNotHasKey('edit', $bioSlot['#slots']['body']);
    self::assertArrayNotHasKey('title', $bioSlot['#slots']['body']);
    self::assertSame(['profile:15'], $bioSlot['#slots']['body']['#cache']['tags']);
    self::assertSame('aculta_studio.profile_bio', $bioSlot['#slots']['actions']['#items'][0]['#url']->getRouteName());

    $networkRows = $socialSlot['#slots']['items']['list']['items'];
    self::assertSame(['instagram', 'facebook', 'reddit'], array_keys($networkRows));
    foreach ($networkRows as $network) {
      self::assertArrayNotHasKey('edit', $network);
      self::assertArrayHasKey('text', $network);
    }
    self::assertSame(['profile:15'], $socialSlot['#slots']['items']['#cache']['tags']);
    self::assertCount(3, $socialSlot['#slots']['actions']['#items']);
    self::assertSame('instagram', $socialSlot['#slots']['actions']['#items'][0]['#url']->getRouteParameters()['rede']);
    self::assertSame('reddit', $socialSlot['#slots']['actions']['#items'][2]['#url']->getRouteParameters()['rede']);

    $identityActions = $identity['#slots']['actions']['#items'];
    self::assertCount(3, $identityActions);
    self::assertSame('aculta_user.foto', $identityActions[1]['#url']->getRouteName());
    self::assertContains('core/drupal.dialog.ajax', $identityActions[1]['#attached']['library']);
    self::assertSame('aculta_studio.profile_privacy', $identityActions[2]['#url']->getRouteName());
    self::assertArrayNotHasKey('actions', $page['#slots']);
    self::assertArrayNotHasKey('content', $page);
    self::assertSame(0, $page['#cache']['max-age']);
  }

  /**
   * A blocked account must not load any profile card or public URL.
   */
  public function testDeniedAccessPrecedesEveryProfileLookup(): void {
    $account = $this->account(TRUE, FALSE);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $link = $this->createMock(PublicProfileLinkInterface::class);
    $link->expects(self::never())->method('build');
    $this->expectException(AccessDeniedHttpException::class);
    (new StudioProfileController($entities, $account, $link))->page();
  }

  private function account(bool $authenticated, bool $permitted): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('hasPermission')->willReturn($permitted);
    $account->method('id')->willReturn('7');
    return $account;
  }

  private function user(): UserInterface {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn('7');
    // This potentially private account value must never appear in Profile.
    $user->method('getDisplayName')->willReturn('conta-privada@example.invalid');
    $user->method('getCacheContexts')->willReturn(['user']);
    $user->method('getCacheTags')->willReturn(['user:7']);
    $user->method('getCacheMaxAge')->willReturn(0);
    $user->method('hasField')->willReturn(FALSE);
    return $user;
  }

  /**
   * Builds the editor under a simulated account and owner storage.
   */
  private function controller(bool $authenticated, bool $permitted, bool $found, ?PublicProfileLinkInterface $link = NULL): StudioProfileController {
    $account = $this->account($authenticated, $permitted);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($found ? $this->user() : NULL);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('user')->willReturn($storage);
    return new StudioProfileController($entities, $account, $link);
  }

}
