<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Controller\NewConversationController;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
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
    $query->method('execute')->willReturn([84, 85, 86]);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with([84, 85, 86])->willReturn($users);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('user')->willReturn($storage);

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('42');
    $account->method('hasRole')->willReturn(FALSE);
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): ?string => $uid === 85 ? 'friends' : NULL);
    $bans = $this->createMock(PrivateMessageBanManagerInterface::class);
    $bans->expects(self::exactly(2))->method('isCurrentUserBannedByUser')->willReturn(FALSE);
    $friendships = $this->createMock(FriendshipManagerInterface::class);
    $friendships->expects(self::once())->method('areFriendsWith')->with(85)->willReturn(FALSE);
    $controller = new NewConversationController(
      $entityTypes,
      $account,
      $userData,
      $bans,
      $friendships,
      $this->createMock(FormBuilderInterface::class),
      $this->createMock(RendererInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );

    $candidates = new \ReflectionMethod(NewConversationController::class, 'candidates');
    self::assertSame([$users[2]], $candidates->invoke($controller, 'mem'));
  }

}
