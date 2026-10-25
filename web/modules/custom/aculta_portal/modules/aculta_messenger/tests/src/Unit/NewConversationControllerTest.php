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
use Drupal\private_message\Mapper\PrivateMessageMapperInterface;
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
      $this->createMock(PrivateMessageMapperInterface::class),
      $friendships,
      $this->createMock(FormBuilderInterface::class),
      $this->createMock(RendererInterface::class),
      $identity,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );

    $candidates = new \ReflectionMethod(NewConversationController::class, 'candidates');
    self::assertSame([['user' => $users[2], 'name' => 'nickname-86']], $candidates->invoke($controller, 'nickname'));
  }

  /**
   * Direct compose respects friend-only privacy and existing threads.
   */
  public function testDirectComposeAllowsOnlyFriendEligibleOrExistingRecipients(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('42');
    $account->method('hasRole')->willReturn(FALSE);
    $sender = $this->user(42);
    $recipient = $this->user(84);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::exactly(2))->method('load')->with(42)->willReturn($sender);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->expects(self::exactly(2))->method('getStorage')->with('user')->willReturn($userStorage);
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::exactly(2))->method('get')
      ->with('aculta_messenger', 84, 'message_privacy')->willReturn('friends');
    $friendships = $this->createMock(FriendshipManagerInterface::class);
    $friendships->expects(self::exactly(2))->method('areFriendsWith')->with(84)->willReturn(FALSE);
    $mapper = $this->createMock(PrivateMessageMapperInterface::class);
    $mapper->expects(self::exactly(2))->method('getThreadIdForMembers')->with([$sender, $recipient])
      ->willReturnOnConsecutiveCalls(FALSE, 77);
    $identity = $this->createMock(PublicMemberIdentityInterface::class);
    $identity->expects(self::exactly(2))->method('publicDisplayName')->with($recipient)->willReturn('membro-84');
    $bans = $this->createMock(PrivateMessageBanManagerInterface::class);
    $bans->expects(self::exactly(2))->method('isBanned')->with(84)->willReturn(FALSE);
    $bans->expects(self::exactly(2))->method('isCurrentUserBannedByUser')->with(84)->willReturn(FALSE);
    $controller = new NewConversationController(
      $entityTypes,
      $account,
      $userData,
      $bans,
      $mapper,
      $friendships,
      $this->createMock(FormBuilderInterface::class),
      $this->createMock(RendererInterface::class),
      $identity,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );
    $eligibleName = new \ReflectionMethod(NewConversationController::class, 'eligibleRecipientName');

    self::assertNull($eligibleName->invoke($controller, $recipient, TRUE));
    self::assertSame('membro-84', $eligibleName->invoke($controller, $recipient, TRUE));
  }

  /**
   * The direct compose route never bypasses either member's block.
   */
  public function testDirectComposeRejectsBlockedRecipientBeforeShowingIdentity(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('42');
    $recipient = $this->user(84);
    $bans = $this->createMock(PrivateMessageBanManagerInterface::class);
    $bans->expects(self::once())->method('isBanned')->with(84)->willReturn(TRUE);
    $bans->expects(self::never())->method('isCurrentUserBannedByUser');
    $identity = $this->createMock(PublicMemberIdentityInterface::class);
    $identity->expects(self::never())->method('publicDisplayName');
    $controller = new NewConversationController(
      $this->createMock(EntityTypeManagerInterface::class),
      $account,
      $this->createMock(UserDataInterface::class),
      $bans,
      $this->createMock(PrivateMessageMapperInterface::class),
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(FormBuilderInterface::class),
      $this->createMock(RendererInterface::class),
      $identity,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );
    $eligibleName = new \ReflectionMethod(NewConversationController::class, 'eligibleRecipientName');

    self::assertNull($eligibleName->invoke($controller, $recipient, TRUE));
  }

  /**
   * Creates a test member with all required messaging permissions.
   */
  private function user(int $uid): UserInterface {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn((string) $uid);
    $user->method('isActive')->willReturn(TRUE);
    $user->method('hasPermission')->willReturn(TRUE);
    return $user;
  }

}
