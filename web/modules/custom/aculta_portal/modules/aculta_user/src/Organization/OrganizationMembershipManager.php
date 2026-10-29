<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Organization;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\Entity\OrganizationMember;

/**
 * Gerenciador de vínculos de membros a organizações (ARQ-05, D-01).
 */
final class OrganizationMembershipManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  public function addMembership(AccountInterface|int $user, string $org_id, string $role = 'member', string $status = 'pending'): OrganizationMember {
    $uid = $user instanceof AccountInterface ? (int) $user->id() : $user;
    $storage = $this->entityTypeManager->getStorage('aculta_org_member');

    $existing = $storage->loadByProperties([
      'uid' => $uid,
      'org_id' => $org_id,
    ]);

    if (!empty($existing)) {
      /** @var \Drupal\aculta_user\Entity\OrganizationMember $member */
      $member = reset($existing);
      $member->set('role_in_org', $role);
      $member->set('status', $status);
      $member->save();
      return $member;
    }

    /** @var \Drupal\aculta_user\Entity\OrganizationMember $member */
    $member = $storage->create([
      'uid' => $uid,
      'org_id' => $org_id,
      'role_in_org' => $role,
      'status' => $status,
    ]);
    $member->save();

    return $member;
  }

  public function approveMembership(OrganizationMember $member, AccountInterface $moderator, ?string $notes = NULL): void {
    $member->set('status', 'approved');
    $member->set('approved_by', (int) $moderator->id());
    $member->set('approved_at', $this->time->getRequestTime());
    if ($notes !== NULL) {
      $member->set('notes', $notes);
    }
    $member->save();
  }

  public function rejectMembership(OrganizationMember $member, AccountInterface $moderator, string $reason): void {
    $member->set('status', 'rejected');
    $member->set('approved_by', (int) $moderator->id());
    $member->set('approved_at', $this->time->getRequestTime());
    $member->set('notes', $reason);
    $member->save();
  }

  public function revokeMembership(OrganizationMember $member, AccountInterface $moderator, string $reason): void {
    $member->set('status', 'revoked');
    $member->set('approved_by', (int) $moderator->id());
    $member->set('approved_at', $this->time->getRequestTime());
    $member->set('notes', $reason);
    $member->save();
  }

  public function getMemberships(AccountInterface|int $user, ?string $status = NULL): array {
    $uid = $user instanceof AccountInterface ? (int) $user->id() : $user;
    $properties = ['uid' => $uid];
    if ($status !== NULL) {
      $properties['status'] = $status;
    }

    /** @var \Drupal\aculta_user\Entity\OrganizationMember[] $memberships */
    $memberships = $this->entityTypeManager->getStorage('aculta_org_member')->loadByProperties($properties);
    return $memberships;
  }

  public function hasApprovedMembership(AccountInterface|int $user, string $org_id): bool {
    $uid = $user instanceof AccountInterface ? (int) $user->id() : $user;
    if ($uid <= 0) {
      return FALSE;
    }

    $ids = $this->entityTypeManager->getStorage('aculta_org_member')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('org_id', $org_id)
      ->condition('status', 'approved')
      ->range(0, 1)
      ->execute();

    return !empty($ids);
  }

}