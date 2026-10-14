<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/** Restricts relationship records to their two participants. */
final class AcultaFriendshipAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if (!in_array($operation, ['view', 'update', 'delete'], TRUE)) {
      return AccessResult::neutral()->cachePerPermissions();
    }
    if ($account->hasPermission('administer aculta friendships')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if (!$entity instanceof AcultaFriendshipInterface) {
      return AccessResult::forbidden()->cachePerUser();
    }

    $participant = in_array((int) $account->id(), [$entity->getMemberLowId(), $entity->getMemberHighId()], TRUE);
    if ($operation === 'delete') {
      return AccessResult::forbiddenIf($participant)
        ->addCacheContexts(['user'])
        ->addCacheableDependency($entity);
    }
    return AccessResult::allowedIf($participant)
      ->cachePerUser()
      ->addCacheableDependency($entity);
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->cachePerUser();
  }

}
