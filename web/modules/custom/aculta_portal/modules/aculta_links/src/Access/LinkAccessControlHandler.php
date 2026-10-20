<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Keeps reusable destinations private to their owner and link administrators.
 */
class LinkAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer aculta links')) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    if (!in_array($operation, ['view', 'update', 'delete', 'view revision'], TRUE)) {
      return AccessResult::forbidden()
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    $is_owner = $entity instanceof EntityOwnerInterface
      && (string) $entity->getOwnerId() === (string) $account->id();
    $has_permission = $account->hasPermission('manage own aculta links');

    return AccessResult::allowedIf($is_owner && $has_permission)
      ->cachePerUser()
      ->cachePerPermissions()
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'create aculta links')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'administer aculta links'));
  }

}
