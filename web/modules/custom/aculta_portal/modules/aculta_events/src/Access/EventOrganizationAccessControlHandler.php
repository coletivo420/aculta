<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_events\Entity\EventOrganization;

/**
 * Controle de acesso a Organizações Culturais (ARQ-05).
 */
final class EventOrganizationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if (!$entity instanceof EventOrganization) {
      return AccessResult::neutral();
    }

    if ($operation === 'view') {
      $status = (string) $entity->get('field_status')->value;
      if ($status === 'rascunho') {
        return AccessResult::allowedIfHasPermission($account, 'view aculta event drafts')
          ->orIf(AccessResult::allowedIfHasPermission($account, 'administer aculta events'))
          ->addCacheableDependency($entity);
      }
      return AccessResult::allowed()->addCacheableDependency($entity);
    }

    if ($operation === 'update') {
      if ($account->hasPermission('administer aculta events')) {
        return AccessResult::allowed()->cachePerPermissions();
      }

      // Enquanto não reivindicada, qualquer usuário que possa criar eventos pode editar o perfil.
      if ($entity->isUnclaimed()) {
        $createAccess = \Drupal::service('aculta_events.permission_policy')->createEventAccessResult($account);
        if ($createAccess->isAllowed()) {
          return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
        }
      }

      // Reivindicada: titular ou membros aprovados com papel de edição/copropriedade.
      if ($entity->isClaimed()) {
        $titularId = $entity->getOwnerId();
        if ($titularId !== NULL && $titularId === (int) $account->id()) {
          return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
        }

        if (\Drupal::hasService('aculta_user.organization_access_service')) {
          /** @var \Drupal\aculta_user\Organization\OrganizationAccessService $orgAccess */
          $orgAccess = \Drupal::service('aculta_user.organization_access_service');
          if ($orgAccess->canActOnBehalf($account, (string) $entity->id())) {
            return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
          }
        }
      }

      return AccessResult::forbidden('Usuário sem poderes de edição sobre a organização.')->cachePerUser()->addCacheableDependency($entity);
    }

    if ($operation === 'delete') {
      if ($account->hasPermission('administer aculta events')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
      if ($entity->isClaimed() && $entity->getOwnerId() === (int) $account->id()) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
      }
      return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($entity);
    }

    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return \Drupal::service('aculta_events.permission_policy')->createEventAccessResult($account);
  }

  /**
   * Acesso à tela de gestão de membros e delegações da organização.
   */
  public static function membersAccess(AccountInterface $account, EventOrganization $aculta_event_organization): AccessResult {
    if ($account->hasPermission('administer aculta events') || $account->hasPermission('administer aculta claims')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Apenas organizações reivindicadas possuem gestão interna de membros.
    if ($aculta_event_organization->isUnclaimed()) {
      return AccessResult::forbidden('Organização não reivindicada não possui gestão de membros.')->addCacheableDependency($aculta_event_organization);
    }

    // Titular tem acesso irrestrito à gestão de membros.
    if ($aculta_event_organization->getOwnerId() === (int) $account->id()) {
      return AccessResult::allowed()->cachePerUser()->addCacheableDependency($aculta_event_organization);
    }

    // Coproprietário com poder delegado de gerenciar membros.
    if (\Drupal::hasService('aculta_user.organization_access_service')) {
      /** @var \Drupal\aculta_user\Organization\OrganizationAccessService $orgAccess */
      $orgAccess = \Drupal::service('aculta_user.organization_access_service');
      if ($orgAccess->canManageOrganization($account, (string) $aculta_event_organization->id())) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($aculta_event_organization);
      }
    }

    return AccessResult::forbidden('Apenas o titular ou coproprietário com delegação podem gerenciar acessos.')
      ->cachePerUser()
      ->addCacheableDependency($aculta_event_organization);
  }

}
