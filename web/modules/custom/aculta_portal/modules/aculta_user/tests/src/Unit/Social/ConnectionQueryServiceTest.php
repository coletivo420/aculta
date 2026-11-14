<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Social;

use Drupal\profile\Entity\ProfileInterface;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\aculta_user\Identity\PublicMemberIdentityInterface;
use Drupal\aculta_user\Social\ConnectionQueryService;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\aculta_user\Social\FriendshipStatus;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the ConnectionQueryService logic.
 */
#[CoversClass(ConnectionQueryService::class)]
final class ConnectionQueryServiceTest extends UnitTestCase {

  /**
   * Confirma que visitante anônimo recebe lista de conexões vazia.
   */
  public function testAnonymousReturnsEmptyConnections(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(0);
    $database = $this->createMock(Connection::class);
    $friendshipManager = $this->createMock(FriendshipManagerInterface::class);

    $service = new ConnectionQueryService($entityTypeManager, $currentUser, $database, $friendshipManager);
    $result = $service->getAcceptedConnections();

    self::assertSame(['items' => [], 'total' => 0, 'has_more' => FALSE], $result);
  }

  /**
   * Confirma que busca com menos de três caracteres retorna vazio.
   */
  public function testSearchTermUnderThreeCharsReturnsEmpty(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(10);
    $database = $this->createMock(Connection::class);
    $friendshipManager = $this->createMock(FriendshipManagerInterface::class);

    $service = new ConnectionQueryService($entityTypeManager, $currentUser, $database, $friendshipManager);
    $result = $service->searchCandidates('ab');

    self::assertSame(['items' => [], 'total' => 0, 'has_more' => FALSE], $result);
  }

  /**
   * Confirma que conexões aceitas filtram membros banidos e sem nome público.
   */
  public function testAcceptedConnectionsFiltersBannedAndPrivate(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $friendshipStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage = $this->createMock(EntityStorageInterface::class);

    $entityTypeManager->method('getStorage')->willReturnCallback(function (string $type) use ($friendshipStorage, $userStorage) {
      return match ($type) {
        'aculta_friendship' => $friendshipStorage,
        'user' => $userStorage,
        default => NULL,
      };
    });

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(1);

    $database = $this->createMock(Connection::class);
    $friendshipManager = $this->createMock(FriendshipManagerInterface::class);
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $publicIdentity = $this->createMock(PublicMemberIdentityInterface::class);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orConditionGroup')->willReturn($query);
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn([10, 20, 30]);

    $friendshipStorage->method('getQuery')->willReturn($query);

    // Friendship 1: with uid 2 (active, public, not banned) -> KEEP.
    $f1 = $this->createMock(AcultaFriendshipInterface::class);
    $f1->method('getMemberLowId')->willReturn(1);
    $f1->method('getMemberHighId')->willReturn(2);

    // Friendship 2: with uid 3 (banned by current user) -> EXCLUDE.
    $f2 = $this->createMock(AcultaFriendshipInterface::class);
    $f2->method('getMemberLowId')->willReturn(1);
    $f2->method('getMemberHighId')->willReturn(3);

    // Friendship 3: with uid 4 (private/no public name) -> EXCLUDE.
    $f3 = $this->createMock(AcultaFriendshipInterface::class);
    $f3->method('getMemberLowId')->willReturn(1);
    $f3->method('getMemberHighId')->willReturn(4);

    $friendshipStorage->method('loadMultiple')->willReturn([10 => $f1, 20 => $f2, 30 => $f3]);

    $u2 = $this->createMock(UserInterface::class);
    $u2->method('id')->willReturn(2);
    $u2->method('isActive')->willReturn(TRUE);

    $u3 = $this->createMock(UserInterface::class);
    $u3->method('id')->willReturn(3);
    $u3->method('isActive')->willReturn(TRUE);

    $u4 = $this->createMock(UserInterface::class);
    $u4->method('id')->willReturn(4);
    $u4->method('isActive')->willReturn(TRUE);

    $userStorage->method('loadMultiple')->willReturn([2 => $u2, 3 => $u3, 4 => $u4]);

    // Bans.
    $banManager->method('isBanned')->willReturnCallback(fn(int $uid): bool => $uid === 3);
    $banManager->method('isCurrentUserBannedByUser')->willReturn(FALSE);

    // Public names.
    $publicIdentity->method('publicDisplayName')->willReturnCallback(function (UserInterface $user): ?string {
      return match ((int) $user->id()) {
        2 => 'Alice',
        3 => 'Bob',
        // Private.
        4 => NULL,
        default => NULL,
      };
    });

    $service = new ConnectionQueryService($entityTypeManager, $currentUser, $database, $friendshipManager, $banManager, $publicIdentity);
    $result = $service->getAcceptedConnections(limit: 5, offset: 0);

    self::assertSame(1, $result['total']);
    self::assertCount(1, $result['items']);
    self::assertSame(2, $result['items'][0]['uid']);
    self::assertSame('Alice', $result['items'][0]['name']);
    self::assertFalse($result['has_more']);
  }

  /**
   * Confirma que a busca de candidatos aplica estados de amizade e banimentos.
   */
  public function testSearchCandidatesWithStatusesAndBans(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $profileStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage = $this->createMock(EntityStorageInterface::class);

    $entityTypeManager->method('getStorage')->willReturnCallback(function (string $type) use ($profileStorage, $userStorage) {
      return match ($type) {
        'profile' => $profileStorage,
        'user' => $userStorage,
        default => NULL,
      };
    });

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(1);

    $database = $this->createMock(Connection::class);
    $database->method('escapeLike')->willReturnCallback(fn(string $s): string => $s);

    $friendshipManager = $this->createMock(FriendshipManagerInterface::class);
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $publicIdentity = $this->createMock(PublicMemberIdentityInterface::class);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orConditionGroup')->willReturn($query);
    $query->method('execute')->willReturn([101, 102, 103, 104]);

    $profileStorage->method('getQuery')->willReturn($query);

    // Profile mocks.
    $p1 = $this->createMock(ProfileInterface::class);
    // Connected.
    $p1->method('getOwnerId')->willReturn(2);
    $p2 = $this->createMock(ProfileInterface::class);
    // Pending sent.
    $p2->method('getOwnerId')->willReturn(3);
    $p3 = $this->createMock(ProfileInterface::class);
    // Pending received.
    $p3->method('getOwnerId')->willReturn(4);
    $p4 = $this->createMock(ProfileInterface::class);
    // None.
    $p4->method('getOwnerId')->willReturn(5);

    $profileStorage->method('loadMultiple')->willReturn([101 => $p1, 102 => $p2, 103 => $p3, 104 => $p4]);

    $u2 = $this->createMock(UserInterface::class);
    $u2->method('id')->willReturn(2);
    $u2->method('isActive')->willReturn(TRUE);

    $u3 = $this->createMock(UserInterface::class);
    $u3->method('id')->willReturn(3);
    $u3->method('isActive')->willReturn(TRUE);

    $u4 = $this->createMock(UserInterface::class);
    $u4->method('id')->willReturn(4);
    $u4->method('isActive')->willReturn(TRUE);

    $u5 = $this->createMock(UserInterface::class);
    $u5->method('id')->willReturn(5);
    $u5->method('isActive')->willReturn(TRUE);

    $userStorage->method('loadMultiple')->willReturn([2 => $u2, 3 => $u3, 4 => $u4, 5 => $u5]);

    $banManager->method('isBanned')->willReturn(FALSE);
    $banManager->method('isCurrentUserBannedByUser')->willReturn(FALSE);

    $publicIdentity->method('publicDisplayName')->willReturnCallback(function (UserInterface $user): ?string {
      return 'User ' . $user->id();
    });

    // Friendships.
    $fConnected = $this->createMock(AcultaFriendshipInterface::class);
    $fConnected->method('getStatus')->willReturn(FriendshipStatus::Accepted->value);

    $fSent = $this->createMock(AcultaFriendshipInterface::class);
    $fSent->method('getStatus')->willReturn(FriendshipStatus::Pending->value);
    $fSent->method('getRequesterId')->willReturn(1);

    $fReceived = $this->createMock(AcultaFriendshipInterface::class);
    $fReceived->method('getStatus')->willReturn(FriendshipStatus::Pending->value);
    $fReceived->method('getRequesterId')->willReturn(4);

    $friendshipManager->method('getRelationshipWith')->willReturnCallback(function (int $uid) use ($fConnected, $fSent, $fReceived) {
      return match ($uid) {
        2 => $fConnected,
        3 => $fSent,
        4 => $fReceived,
        default => NULL,
      };
    });

    $service = new ConnectionQueryService($entityTypeManager, $currentUser, $database, $friendshipManager, $banManager, $publicIdentity);
    $result = $service->searchCandidates('User', limit: 10, offset: 0);

    self::assertSame(4, $result['total']);
    self::assertCount(4, $result['items']);
    self::assertSame('connected', $result['items'][0]['state']);
    self::assertSame('pending_sent', $result['items'][1]['state']);
    self::assertSame('pending_received', $result['items'][2]['state']);
    self::assertSame('none', $result['items'][3]['state']);
  }

}
