<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controle de acesso a locais de evento (aculta_event_location).
 *
 * Locais são públicos para visualização por padrão, mas respeitam hooks
 * de acesso, purposes do portal e permissão administrativa para mutações.
 */
final class EventLocationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($operation === 'view') {
      return AccessResult::allowed()->addCacheableDependency($entity);
    }

    if ($operation === 'update' || $operation === 'delete') {
      if ($account->hasPermission('administer aculta events')) {
        return AccessResult::allowed()->cachePerPermissions();
      }

      if (in_array('content_editor', $account->getRoles(), TRUE)) {
        if (\Drupal::hasService('aculta_user.organization_access_service')) {
          /** @var \Drupal\aculta_user\Organization\OrganizationAccessService $orgAccess */
          $orgAccess = \Drupal::service('aculta_user.organization_access_service');
          if ($orgAccess->isContentEditorScoped($entity, $account)) {
            return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
          }
        }
        return AccessResult::forbidden('Editor sem vínculo comprovado com a organização do local.')->cachePerUser()->addCacheableDependency($entity);
      }
    }

    return AccessResult::allowedIfHasPermission($account, 'administer aculta events');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta events')
      ->orIf(AccessResult::allowedIf(in_array('content_editor', $account->getRoles(), TRUE)));
  }

}
