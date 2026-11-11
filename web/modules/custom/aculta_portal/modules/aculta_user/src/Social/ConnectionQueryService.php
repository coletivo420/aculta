<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Social;

use Drupal\aculta_user\Identity\PublicMemberIdentityInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\user\UserInterface;

/**
 * Provides paginated queries for user connections and search.
 */
class ConnectionQueryService {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface|AccountInterface $currentUser,
    protected readonly Connection $database,
    protected readonly FriendshipManagerInterface $friendshipManager,
    protected readonly ?PrivateMessageBanManagerInterface $banManager = NULL,
    protected readonly ?PublicMemberIdentityInterface $publicMemberIdentity = NULL,
  ) {}

  /**
   * Returns a paginated list of accepted connections for the given user.
   *
   * @param int|null $uid
   *   The user ID, or NULL to use the current user.
   * @param int $limit
   *   The maximum number of items to return.
   * @param int $offset
   *   The starting offset.
   * @param string $filter
   *   Optional name/nickname substring filter.
   *
   * @return array{
   *   items: array<int, array{
   *     uid: int,
   *     name: string,
   *     account: \Drupal\user\UserInterface,
   *     friendship: \Drupal\aculta_user\Entity\AcultaFriendshipInterface,
   *   }>,
   *   total: int,
   *   has_more: bool,
   *   }
   */
  public function getAcceptedConnections(?int $uid = NULL, int $limit = 5, int $offset = 0, string $filter = ''): array {
    $uid ??= (int) $this->currentUser->id();
    if ($uid < 1) {
      return ['items' => [], 'total' => 0, 'has_more' => FALSE];
    }

    $storage = $this->entityTypeManager->getStorage('aculta_friendship');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', FriendshipStatus::Accepted->value);

    $orGroup = $query->orConditionGroup()
      ->condition('member_low', $uid)
      ->condition('member_high', $uid);
    $query->condition($orGroup);
    $query->sort('changed', 'DESC');

    $ids = $query->execute();
    if (empty($ids)) {
      return ['items' => [], 'total' => 0, 'has_more' => FALSE];
    }

    /** @var \Drupal\aculta_user\Entity\AcultaFriendshipInterface[] $friendships */
    $friendships = $storage->loadMultiple($ids);
    $userStorage = $this->entityTypeManager->getStorage('user');

    $connectedUids = [];
    $friendshipMap = [];
    foreach ($friendships as $friendship) {
      $low = (int) $friendship->getMemberLowId();
      $high = (int) $friendship->getMemberHighId();
      $otherUid = ($low === $uid) ? $high : $low;
      if ($otherUid > 0 && $otherUid !== $uid) {
        $connectedUids[] = $otherUid;
        $friendshipMap[$otherUid] = $friendship;
      }
    }

    if (empty($connectedUids)) {
      return ['items' => [], 'total' => 0, 'has_more' => FALSE];
    }

    /** @var \Drupal\user\UserInterface[] $accounts */
    $accounts = $userStorage->loadMultiple($connectedUids);
    $filteredItems = [];
    $filterLower = mb_strtolower(trim($filter));

    foreach ($connectedUids as $otherUid) {
      $account = $accounts[$otherUid] ?? NULL;
      if (!$account instanceof UserInterface || !$account->isActive()) {
        continue;
      }

      // Check bans.
      if ($this->banManager !== NULL) {
        if ($this->banManager->isBanned($otherUid) || $this->banManager->isCurrentUserBannedByUser($otherUid)) {
          continue;
        }
      }

      // Resolve public name.
      $displayName = $this->publicMemberIdentity?->publicDisplayName($account);
      if ($displayName === NULL || trim($displayName) === '') {
        continue;
      }

      if ($filterLower !== '') {
        if (!str_contains(mb_strtolower($displayName), $filterLower)) {
          continue;
        }
      }

      $filteredItems[] = [
        'uid' => $otherUid,
        'name' => $displayName,
        'account' => $account,
        'friendship' => $friendshipMap[$otherUid],
      ];
    }

    $total = count($filteredItems);
    $sliced = array_slice($filteredItems, $offset, $limit);
    $hasMore = ($offset + $limit) < $total;

    return [
      'items' => $sliced,
      'total' => $total,
      'has_more' => $hasMore,
    ];
  }

  /**
   * Searches for new potential connections.
   *
   * @param string $term
   *   The search term (minimum 3 characters).
   * @param int $limit
   *   The maximum number of items to return.
   * @param int $offset
   *   The starting offset.
   *
   * @return array{
   *   items: array<int, array{
   *     uid: int,
   *     name: string,
   *     account: \Drupal\user\UserInterface,
   *     state: 'connected'|'pending_sent'|'pending_received'|'none',
   *     friendship: ?\Drupal\aculta_user\Entity\AcultaFriendshipInterface,
   *   }>,
   *   total: int,
   *   has_more: bool,
   *   }
   */
  public function searchCandidates(string $term, int $limit = 10, int $offset = 0): array {
    $currentUid = (int) $this->currentUser->id();
    if ($currentUid < 1 || mb_strlen(trim($term)) < 3) {
      return ['items' => [], 'total' => 0, 'has_more' => FALSE];
    }

    $termTrimmed = trim($term);
    $userStorage = $this->entityTypeManager->getStorage('user');
    $profileStorage = $this->entityTypeManager->getStorage('profile');

    // Query active profiles matching nickname or first name.
    $profileQuery = $profileStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1);

    $orGroup = $profileQuery->orConditionGroup()
      ->condition('field_nickname', '%' . $this->database->escapeLike($termTrimmed) . '%', 'LIKE')
      ->condition('field_first_name', '%' . $this->database->escapeLike($termTrimmed) . '%', 'LIKE');
    $profileQuery->condition($orGroup);

    $profileIds = $profileQuery->execute();
    if (empty($profileIds)) {
      return ['items' => [], 'total' => 0, 'has_more' => FALSE];
    }

    $profiles = $profileStorage->loadMultiple($profileIds);
    $candidateUids = [];
    foreach ($profiles as $profile) {
      if (!$profile instanceof EntityOwnerInterface) {
        continue;
      }
      $ownerId = (int) $profile->getOwnerId();
      if ($ownerId > 0 && $ownerId !== $currentUid) {
        $candidateUids[$ownerId] = $ownerId;
      }
    }

    if (empty($candidateUids)) {
      return ['items' => [], 'total' => 0, 'has_more' => FALSE];
    }

    /** @var \Drupal\user\UserInterface[] $accounts */
    $accounts = $userStorage->loadMultiple($candidateUids);
    $results = [];

    foreach ($accounts as $candidateUid => $account) {
      if (!$account->isActive()) {
        continue;
      }

      // Respect bilateral bans.
      if ($this->banManager !== NULL) {
        if ($this->banManager->isBanned($candidateUid) || $this->banManager->isCurrentUserBannedByUser($candidateUid)) {
          continue;
        }
      }

      // Verify privacy / public profile display name.
      $displayName = $this->publicMemberIdentity?->publicDisplayName($account);
      if ($displayName === NULL || trim($displayName) === '') {
        continue;
      }

      // Check friendship relationship status.
      $relationship = $this->friendshipManager->getRelationshipWith($candidateUid);
      $state = 'none';

      if ($relationship !== NULL) {
        $status = $relationship->getStatus();
        if ($status === FriendshipStatus::Accepted->value) {
          $state = 'connected';
        }
        elseif ($status === FriendshipStatus::Pending->value) {
          if ((int) $relationship->getRequesterId() === $currentUid) {
            $state = 'pending_sent';
          }
          else {
            $state = 'pending_received';
          }
        }
      }

      $results[] = [
        'uid' => $candidateUid,
        'name' => $displayName,
        'account' => $account,
        'state' => $state,
        'friendship' => $relationship,
      ];
    }

    // Sort by name.
    usort($results, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    $total = count($results);
    $sliced = array_slice($results, $offset, $limit);
    $hasMore = ($offset + $limit) < $total;

    return [
      'items' => $sliced,
      'total' => $total,
      'has_more' => $hasMore,
    ];
  }

}
