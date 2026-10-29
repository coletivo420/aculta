<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Organization;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Serviço de autorização e escopo de organização (ARQ-05, D-01, C-03).
 */
final class OrganizationAccessService {

  public function __construct(
    private readonly OrganizationMembershipManager $membershipManager,
  ) {}

  public function canActOnBehalf(AccountInterface $account, string $org_id): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }

    return $this->membershipManager->hasApprovedMembership($account, $org_id);
  }

  public function isContentEditorScoped(EntityInterface $entity, AccountInterface $account): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }

    if ($account->hasPermission('administer aculta events') || $account->hasPermission('administer nodes')) {
      return TRUE;
    }

    if (!in_array('content_editor', $account->getRoles(), TRUE)) {
      return FALSE;
    }

    $uid = (int) $account->id();

    if ($entity->hasField('field_organizador')) {
      $organizerId = (int) $entity->get('field_organizador')->target_id;
      if ($organizerId > 0 && $organizerId === $uid) {
        return TRUE;
      }
    }

    if ($entity->hasField('field_organizacao')) {
      $orgField = $entity->get('field_organizacao');
      if (!$orgField->isEmpty()) {
        $orgId = (string) ($orgField->target_id ?? $orgField->value);
        if ($this->canActOnBehalf($account, $orgId)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}