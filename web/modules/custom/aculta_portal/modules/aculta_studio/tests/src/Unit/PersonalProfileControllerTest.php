<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Controller\PersonalProfileController;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Verifies that the Studio profile page never becomes a public profile.
 */
#[CoversClass(PersonalProfileController::class)]
#[Group('aculta_studio')]
final class PersonalProfileControllerTest extends UnitTestCase {

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
   * The base UI uses only account owners and hides missing public links.
   */
  public function testOwnerOnlyPageWithoutOptionalUserModule(): void {
    $page = $this->controller(TRUE, TRUE, TRUE)->page();

    self::assertSame('studio-personal-profile', $page['#attributes']['data-portal-ui']);
    self::assertSame('Membro de teste', $page['identity']['name']['#plain_text']);
    self::assertSame('item_list', $page['actions']['#theme']);
    self::assertCount(1, $page['actions']['#items']);
    self::assertSame('aculta_portal.my_data',
      $page['actions']['#items'][0]['#url']->getRouteName());
    self::assertArrayNotHasKey('bio', $page);
    self::assertArrayNotHasKey('social', $page);
    self::assertSame(0, $page['#cache']['max-age']);
    self::assertContains('user', $page['#cache']['contexts']);
  }

  /**
   * The owner link contract, not a guessed nickname, controls public URLs.
   */
  public function testOptionalOwnerPublicLink(): void {
    $link = $this->createMock(PublicProfileLinkInterface::class);
    $link->expects(self::once())->method('build')
      ->willReturn(Url::fromUri('https://main.example/u/membro-de-teste'));

    $page = $this->controller(TRUE, TRUE, TRUE, $link)->page();
    self::assertCount(2, $page['actions']['#items']);
    $public = $page['actions']['#items'][1];
    self::assertSame('https://main.example/u/membro-de-teste',
      $public['#url']->getUri());
    self::assertSame('studio-profile-public-link',
      $public['#attributes']['data-portal-ui']);
  }

  /**
   * Builds the editor under a simulated account and owner storage.
   */
  private function controller(bool $authenticated, bool $permitted, bool $found, ?PublicProfileLinkInterface $link = NULL): PersonalProfileController {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('hasPermission')->willReturn($permitted);
    $account->method('id')->willReturn('7');

    $storage = $this->createMock(EntityStorageInterface::class);
    if ($found) {
      $user = $this->createMock(UserInterface::class);
      $user->method('id')->willReturn('7');
      $user->method('getDisplayName')->willReturn('Membro de teste');
      $user->method('getCacheContexts')->willReturn(['user']);
      $user->method('getCacheTags')->willReturn(['user:7']);
      $user->method('getCacheMaxAge')->willReturn(0);
      $storage->method('load')->willReturn($user);
    }
    else {
      $storage->method('load')->willReturn(NULL);
    }
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('user')->willReturn($storage);
    return new PersonalProfileController($entities, $account, $link);
  }

}
