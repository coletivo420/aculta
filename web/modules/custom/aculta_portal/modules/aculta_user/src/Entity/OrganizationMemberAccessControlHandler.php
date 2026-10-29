<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controle de acesso a vínculos de organização.
 */
final class OrganizationMemberAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer aculta claims')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if ($operation === 'view') {
      $uid = (int) $entity->get('uid')->target_id;
      if ($uid === (int) $account->id()) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
      }
    }

    return AccessResult::forbidden()->cachePerUser();
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta claims');
  }

}