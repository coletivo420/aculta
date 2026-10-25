<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Controller\NewConversationController;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\aculta_user\Identity\PublicMemberIdentityInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that private-conversation search returns only eligible recipients.
 */
#[CoversClass(NewConversationController::class)]
#[Group('aculta_messenger')]
final class NewConversationControllerTest extends UnitTestCase {

  /**
   * Excludes accounts without inbox permissions and friend-only nonfriends.
   */
  public function testSearchFiltersIneligibleRecipients(): void {
    $users = [];
    foreach ([84, 85, 86] as $uid) {
      $user = $this->createMock(UserInterface::class);
      $user->method('id')->willReturn((string) $uid);
      $user->method('isActive')->willReturn(TRUE);
      $user->method('hasPermission')->willReturnCallback(static fn(string $permission): bool => $uid !== 84
        && in_array($permission, ['use private messaging system', 'access user profiles'], TRUE));
      $users[] = $user;
    }

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([501, 502, 503]);
    $profiles = [];
    foreach ([84, 85, 86] as $uid) {
      $profile = $this->createMock(ProfileInterface::class);
      $profile->method('getOwnerId')->willReturn((string) $uid);
      $profile->method('hasField')->with('field_nickname')->willReturn(TRUE);
      $nickname = $this->createMock(FieldItemListInterface::class);
      $nickname->method('isEmpty')->willReturn(FALSE);
      $profile->method('get')->with('field_nickname')->willReturn($nickname);
      $profiles[] = $profile;
    }
    $profileStorage = $this->createMock(EntityStorageInterface::class);
    $profileStorage->method('getQuery')->willReturn($query);
    $profileStorage->method('loadMultiple')->with([501, 502, 503])->willReturn($profiles);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadMultiple')->with([84, 85, 86])->willReturn($users);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->willReturnMap([
      ['profile', $profileStorage],
      ['user', $userStorage],
    ]);

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('42');
    $account->method('hasRole')->willReturn(FALSE);
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): ?string => $uid === 85 ? 'friends' : NULL);
    $bans = $this->createMock(PrivateMessageBanManagerInterface::class);
    $bans->expects(self::exactly(2))->method('isCurrentUserBannedByUser')->willReturn(FALSE);
    $friendships = $this->createMock(FriendshipManagerInterface::class);
    $friendships->expects(self::once())->method('areFriendsWith')->with(85)->willReturn(FALSE);
    $identity = $this->createMock(PublicMemberIdentityInterface::class);
    $identity->method('publicDisplayName')->willReturnCallback(static fn(UserInterface $user): string => 'nickname-' . $user->id());
    $controller = new NewConversationController(
      $entityTypes,
      $account,
      $userData,
      $bans,
      $friendships,
      $this->createMock(FormBuilderInterface::class),
      $this->createMock(RendererInterface::class),
      $identity,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );

    $candidates = new \ReflectionMethod(NewConversationController::class, 'candidates');
    self::assertSame([['user' => $users[2], 'name' => 'nickname-86']], $candidates->invoke($controller, 'nickname'));
  }

}
