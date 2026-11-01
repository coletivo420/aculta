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

  /**
   * Confere se a conta possui poderes de atuar em nome da organização.
   */
  public function canActOnBehalf(AccountInterface $account, string $org_id): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }

    $role = $this->membershipManager->getMemberRole($account, $org_id);
    if ($role === NULL) {
      return FALSE;
    }

    return in_array($role, ['owner', 'co_owner', 'publisher', 'editor', 'admin'], TRUE);
  }

  /**
   * Confere se a conta pode gerenciar acessos e membros da organização.
   */
  public function canManageOrganization(AccountInterface $account, string|int $org_id): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }

    if ($account->hasPermission('administer aculta events') || $account->hasPermission('administer aculta claims')) {
      return TRUE;
    }

    return $this->membershipManager->canManageMembers($account, (string) $org_id);
  }

  /**
   * Confere se a conta possui permissão de publicar eventos pela organização.
   */
  public function canPublishForOrganization(AccountInterface $account, string|int $org_id): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }

    if ($account->hasPermission('administer aculta events') || $account->hasPermission('administer aculta claims')) {
      return TRUE;
    }

    return $this->membershipManager->canPublish($account, (string) $org_id);
  }

  /**
   * Confere se a conta pode publicar ou retirar do ar um evento específico.
   */
  public function canPublishEvent(EntityInterface $entity, AccountInterface $account): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }

    if ($account->hasPermission('administer aculta events')) {
      return TRUE;
    }

    if (!in_array('content_editor', $account->getRoles(), TRUE)) {
      return FALSE;
    }

    if ($entity->hasField('field_organizacao') && !$entity->get('field_organizacao')->isEmpty()) {
      $orgField = $entity->get('field_organizacao');
      $orgId = (string) ($orgField->target_id ?? $orgField->value);
      return $this->canPublishForOrganization($account, $orgId);
    }

    if ($entity->hasField('field_organizador')) {
      $organizerId = (int) $entity->get('field_organizador')->target_id;
      if ($organizerId > 0 && $organizerId === (int) $account->id()) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Confere se um editor de conteúdo possui escopo autorizado sobre a entidade.
   */
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

    // Entidade de Organização diretamente.
    if ($entity->getEntityTypeId() === 'aculta_event_organization') {
      if ($entity->hasField('reivindicada') && empty($entity->get('reivindicada')->value)) {
        return TRUE;
      }

      if ($entity->hasField('titular')) {
        $titularId = (int) $entity->get('titular')->target_id;
        if ($titularId > 0 && $titularId === $uid) {
          return TRUE;
        }
      }

      return $this->canActOnBehalf($account, (string) $entity->id());
    }

    // Autor direto do evento.
    if ($entity->hasField('field_organizador')) {
      $organizerId = (int) $entity->get('field_organizador')->target_id;
      if ($organizerId > 0 && $organizerId === $uid) {
        return TRUE;
      }
    }

    // Organização principal do evento.
    if ($entity->hasField('field_organizacao')) {
      $orgField = $entity->get('field_organizacao');
      if (!$orgField->isEmpty()) {
        $targetOrg = $orgField->entity;
        if ($targetOrg !== NULL) {
          if ($targetOrg->hasField('reivindicada') && empty($targetOrg->get('reivindicada')->value)) {
            return TRUE;
          }
          if ($targetOrg->hasField('titular')) {
            $titularId = (int) $targetOrg->get('titular')->target_id;
            if ($titularId > 0 && $titularId === $uid) {
              return TRUE;
            }
          }
        }

        $orgId = (string) ($orgField->target_id ?? $orgField->value);
        if ($this->canActOnBehalf($account, $orgId)) {
          return TRUE;
        }
      }
    }

    // Coorganizadores do evento: concedem acesso de edição (update).
    if ($entity->hasField('field_coorganizadores')) {
      $coOrgsField = $entity->get('field_coorganizadores');
      foreach ($coOrgsField as $item) {
        $coOrgEntity = $item->entity;
        if ($coOrgEntity !== NULL && $coOrgEntity->hasField('titular')) {
          $titularId = (int) $coOrgEntity->get('titular')->target_id;
          if ($titularId > 0 && $titularId === $uid) {
            return TRUE;
          }
        }
        $coOrgId = (string) ($item->target_id ?? $item->value);
        if ($coOrgId !== '' && $this->canActOnBehalf($account, $coOrgId)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
