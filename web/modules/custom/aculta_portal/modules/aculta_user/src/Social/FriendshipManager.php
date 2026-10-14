<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Social;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\user\UserInterface;

/** Implements the ACULTA member friendship workflow. */
final class FriendshipManager implements FriendshipManagerInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly LockBackendInterface $lock,
    private readonly FloodInterface $flood,
  ) {}

  public function request(UserInterface $target): AcultaFriendshipInterface {
    $requesterId = $this->activeCurrentUid();
    $targetId = (int) $target->id();
    if (!$target->isActive() || $targetId < 1 || $targetId === $requesterId) {
      throw new \InvalidArgumentException('Friendship requests require a different active member.');
    }

    [$low, $high] = $this->orderedPair($requesterId, $targetId);
    $lockId = "aculta_user:friendship:$low:$high";
    if (!$this->lock->acquire($lockId, 5.0)) {
      throw new \RuntimeException('This member relationship is being updated.');
    }

    try {
      $relationship = $this->loadPair($low, $high);
      if ($relationship !== NULL) {
        if ($relationship->getStatus() === FriendshipStatus::Accepted->value
          || ($relationship->getStatus() === FriendshipStatus::Pending->value
            && $relationship->getRequesterId() === $requesterId)) {
          return $relationship;
        }
        if ($relationship->getStatus() === FriendshipStatus::Pending->value) {
          throw new \DomainException('A friendship request is already pending for this pair.');
        }
        $relationship->set('requester', $requesterId);
        $relationship->set('recipient', $targetId);
        $relationship->set('status', FriendshipStatus::Pending->value);
      }
      else {
        $relationship = $this->entityTypeManager->getStorage('aculta_friendship')->create([
          'id' => "$low:$high",
          'member_low' => $low,
          'member_high' => $high,
          'requester' => $requesterId,
          'recipient' => $targetId,
          'status' => FriendshipStatus::Pending->value,
        ]);
      }
      if (!$this->flood->isAllowed('aculta_user.friendship_request', 10, 3600, (string) $requesterId)) {
        throw new \DomainException('The friendship request rate limit has been reached.');
      }
      $relationship->save();
      $this->flood->register('aculta_user.friendship_request', 3600, (string) $requesterId);
      return $relationship;
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  public function accept(AcultaFriendshipInterface $friendship): void {
    $this->transition($friendship, FriendshipStatus::Pending, FriendshipStatus::Accepted, 'recipient');
  }

  public function decline(AcultaFriendshipInterface $friendship): void {
    $this->transition($friendship, FriendshipStatus::Pending, FriendshipStatus::Declined, 'recipient');
  }

  public function cancel(AcultaFriendshipInterface $friendship): void {
    $this->transition($friendship, FriendshipStatus::Pending, FriendshipStatus::Cancelled, 'requester');
  }

  public function remove(AcultaFriendshipInterface $friendship): void {
    $this->transition($friendship, FriendshipStatus::Accepted, FriendshipStatus::Removed, 'participant');
  }

  public function areFriendsWith(int $targetUid): bool {
    return $this->getRelationshipWith($targetUid)?->getStatus() === FriendshipStatus::Accepted->value;
  }

  public function getRelationshipWith(int $targetUid): ?AcultaFriendshipInterface {
    $currentUid = $this->activeCurrentUid();
    if ($targetUid < 1 || $targetUid === $currentUid) {
      return NULL;
    }
    [$low, $high] = $this->orderedPair($currentUid, $targetUid);
    return $this->loadPair($low, $high);
  }

  public function getIncomingRequests(): array {
    $uid = $this->activeCurrentUid();
    $storage = $this->entityTypeManager->getStorage('aculta_friendship');
    $ids = $storage->getQuery()
      // Entity access query support is not implemented for this entity. This
      // query is constrained to the current user's recipient UID, and every
      // loaded record is checked again with its participant access handler.
      ->accessCheck(FALSE)
      ->condition('recipient', $uid)
      ->condition('status', FriendshipStatus::Pending->value)
      ->sort('created', 'DESC')
      ->range(0, 100)
      ->execute();
    $relationships = $storage->loadMultiple($ids);
    $requesterIds = [];
    foreach ($relationships as $relationship) {
      if ($relationship instanceof AcultaFriendshipInterface
        && $relationship->access('view', $this->currentUser)->isAllowed()) {
        $requesterIds[] = $relationship->getRequesterId();
      }
    }
    $requesters = $this->entityTypeManager->getStorage('user')->loadMultiple(array_unique($requesterIds));
    $requests = [];
    foreach ($relationships as $relationship) {
      $requester = $relationship instanceof AcultaFriendshipInterface
        ? ($requesters[$relationship->getRequesterId()] ?? NULL)
        : NULL;
      if ($relationship instanceof AcultaFriendshipInterface && $requester instanceof UserInterface && $requester->isActive()) {
        $requests[] = $relationship;
      }
    }
    return $requests;
  }

  private function activeCurrentUid(): int {
    $uid = (int) $this->currentUser->id();
    $account = $uid > 0 ? $this->entityTypeManager->getStorage('user')->load($uid) : NULL;
    if (!$account instanceof UserInterface || !$account->isActive()) {
      throw new \DomainException('An active authenticated member is required.');
    }
    return $uid;
  }

  private function transition(AcultaFriendshipInterface $candidate, FriendshipStatus $from, FriendshipStatus $to, string $actorField): void {
    $uid = $this->activeCurrentUid();
    if (!$candidate->access('update', $this->currentUser)->isAllowed()) {
      throw new \DomainException('The current member cannot update this relationship.');
    }
    $low = $candidate->getMemberLowId();
    $high = $candidate->getMemberHighId();
    $lockId = "aculta_user:friendship:$low:$high";
    if (!$this->lock->acquire($lockId, 5.0)) {
      throw new \RuntimeException('This member relationship is being updated.');
    }
    try {
      $friendship = $this->loadPair($low, $high);
      $actorId = match ($actorField) {
        'requester' => $friendship?->getRequesterId(),
        'recipient' => $friendship?->getRecipientId(),
        default => $uid,
      };
      if ($friendship === NULL || (string) $friendship->id() !== (string) $candidate->id()
        || $friendship->getStatus() !== $from->value || $actorId !== $uid) {
        throw new \DomainException('This relationship is no longer available for that action.');
      }
      $friendship->set('status', $to->value);
      $friendship->save();
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  private function loadPair(int $low, int $high): ?AcultaFriendshipInterface {
    $storage = $this->entityTypeManager->getStorage('aculta_friendship');
    $entity = $storage->load("$low:$high");
    return $entity instanceof AcultaFriendshipInterface
      && $entity->access('view', $this->currentUser)->isAllowed()
      ? $entity
      : NULL;
  }

  /** @return array{int, int} */
  private function orderedPair(int $first, int $second): array {
    return $first < $second ? [$first, $second] : [$second, $first];
  }

}
