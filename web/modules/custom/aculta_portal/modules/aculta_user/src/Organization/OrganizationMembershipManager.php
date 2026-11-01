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

  /**
   * Adiciona ou atualiza o vínculo de um membro a uma organização.
   */
  public function addMembership(
    AccountInterface|int $user,
    string $org_id,
    string $role = 'member',
    string $status = 'pending',
    bool $canManage = FALSE,
    bool $canPublish = FALSE,
  ): OrganizationMember {
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
      $member->set('can_manage_members', $canManage);
      $member->set('can_publish', $canPublish);
      $member->save();
      return $member;
    }

    /** @var \Drupal\aculta_user\Entity\OrganizationMember $member */
    $member = $storage->create([
      'uid' => $uid,
      'org_id' => $org_id,
      'role_in_org' => $role,
      'status' => $status,
      'can_manage_members' => $canManage,
      'can_publish' => $canPublish,
    ]);
    $member->save();

    return $member;
  }

  /**
   * Aprova o vínculo de um membro a uma organização.
   */
  public function approveMembership(OrganizationMember $member, AccountInterface $moderator, ?string $notes = NULL): void {
    $member->set('status', 'approved');
    $member->set('approved_by', (int) $moderator->id());
    $member->set('approved_at', $this->time->getRequestTime());
    if ($notes !== NULL) {
      $member->set('notes', $notes);
    }
    $member->save();
  }

  /**
   * Rejeita o vínculo de um membro a uma organização.
   */
  public function rejectMembership(OrganizationMember $member, AccountInterface $moderator, string $reason): void {
    $member->set('status', 'rejected');
    $member->set('approved_by', (int) $moderator->id());
    $member->set('approved_at', $this->time->getRequestTime());
    $member->set('notes', $reason);
    $member->save();
  }

  /**
   * Revoga o vínculo de um membro a uma organização.
   */
  public function revokeMembership(OrganizationMember $member, AccountInterface $moderator, string $reason): void {
    $member->set('status', 'revoked');
    $member->set('approved_by', (int) $moderator->id());
    $member->set('approved_at', $this->time->getRequestTime());
    $member->set('notes', $reason);
    $member->save();
  }

  /**
   * Retorna os vínculos de um usuário com organizações.
   */
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

  /**
   * Confere se um usuário possui vínculo aprovado com uma organização.
   */
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

  /**
   * Suspende todos os membros aprovados de uma organização.
   *
   * Utilizado após aprovação de reivindicação de posse para que o novo
   * titular revise e confirme explicitamente cada pessoa (ARQ-05).
   */
  public function suspendMemberships(string $org_id): int {
    $storage = $this->entityTypeManager->getStorage('aculta_org_member');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('org_id', $org_id)
      ->condition('status', 'approved')
      ->execute();

    if (empty($ids)) {
      return 0;
    }

    $members = $storage->loadMultiple($ids);
    foreach ($members as $member) {
      /** @var \Drupal\aculta_user\Entity\OrganizationMember $member */
      $member->setStatus('suspended');
      $member->set('notes', 'Acesso suspenso preventivamente para revisão pelo novo titular.');
      $member->save();
    }

    return count($members);
  }

  /**
   * Confirma um membro suspenso com papel e poderes definidos pelo titular.
   */
  public function confirmSuspendedMember(
    OrganizationMember $member,
    string $role,
    bool $canManage,
    bool $canPublish,
    AccountInterface $titular,
  ): void {
    $member->setStatus('approved');
    $member->setRoleInOrg($role);
    $member->setCanManageMembers($canManage);
    $member->setCanPublish($canPublish);
    $member->set('approved_by', (int) $titular->id());
    $member->set('approved_at', $this->time->getRequestTime());
    $member->set('notes', 'Acesso confirmado e revisado pelo titular.');
    $member->save();
  }

  /**
   * Retorna os membros associados a uma organização.
   *
   * @param string $org_id
   *   O ID da organização.
   * @param string|null $status
   *   Filtro de status opcional.
   *
   * @return \Drupal\aculta_user\Entity\OrganizationMember[]
   *   Vetor de entidades de membros encontrados.
   */
  public function getOrgMembers(string $org_id, ?string $status = NULL): array {
    $properties = ['org_id' => $org_id];
    if ($status !== NULL) {
      $properties['status'] = $status;
    }

    /** @var \Drupal\aculta_user\Entity\OrganizationMember[] $members */
    $members = $this->entityTypeManager->getStorage('aculta_org_member')->loadByProperties($properties);
    return $members;
  }

  /**
   * Retorna o papel ativo do membro na organização, ou NULL se não aprovado.
   */
  public function getMemberRole(AccountInterface|int $user, string $org_id): ?string {
    $uid = $user instanceof AccountInterface ? (int) $user->id() : $user;
    if ($uid <= 0) {
      return NULL;
    }

    $members = $this->entityTypeManager->getStorage('aculta_org_member')->loadByProperties([
      'uid' => $uid,
      'org_id' => $org_id,
      'status' => 'approved',
    ]);

    if (empty($members)) {
      return NULL;
    }

    /** @var \Drupal\aculta_user\Entity\OrganizationMember $member */
    $member = reset($members);
    return $member->getRoleInOrg();
  }

  /**
   * Verifica se o membro possui capacidade delegada de gerenciar acessos.
   */
  public function canManageMembers(AccountInterface|int $user, string $org_id): bool {
    $uid = $user instanceof AccountInterface ? (int) $user->id() : $user;
    if ($uid <= 0) {
      return FALSE;
    }

    $members = $this->entityTypeManager->getStorage('aculta_org_member')->loadByProperties([
      'uid' => $uid,
      'org_id' => $org_id,
      'status' => 'approved',
    ]);

    if (empty($members)) {
      return FALSE;
    }

    /** @var \Drupal\aculta_user\Entity\OrganizationMember $member */
    $member = reset($members);
    if ($member->getRoleInOrg() === 'owner') {
      return TRUE;
    }

    return $member->canManageMembers();
  }

  /**
   * Verifica se o membro possui capacidade de publicar ou despublicar eventos.
   */
  public function canPublish(AccountInterface|int $user, string $org_id): bool {
    $uid = $user instanceof AccountInterface ? (int) $user->id() : $user;
    if ($uid <= 0) {
      return FALSE;
    }

    $members = $this->entityTypeManager->getStorage('aculta_org_member')->loadByProperties([
      'uid' => $uid,
      'org_id' => $org_id,
      'status' => 'approved',
    ]);

    if (empty($members)) {
      return FALSE;
    }

    /** @var \Drupal\aculta_user\Entity\OrganizationMember $member */
    $member = reset($members);
    if ($member->getRoleInOrg() === 'owner' || $member->getRoleInOrg() === 'publisher') {
      return TRUE;
    }

    return $member->canPublish();
  }

}
