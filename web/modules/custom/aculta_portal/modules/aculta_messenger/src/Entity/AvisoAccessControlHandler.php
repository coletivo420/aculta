<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Aviso: a pessoa vê só os próprios avisos. Administração de avisos tem permissão própria.
 */
final class AvisoAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer aculta avisos')) {
      return AccessResult::allowed()->addCacheContexts(['user.permissions']);
    }
    return AccessResult::allowedIf($operation === 'view' && (int) $entity->get('uid')->target_id === (int) $account->id())
      ->addCacheContexts(['user'])
      ->cachePerUser();
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta avisos');
  }

}
