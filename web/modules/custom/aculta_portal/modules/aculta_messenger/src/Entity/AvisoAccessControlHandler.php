<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Aviso: a pessoa vê só os próprios avisos. Administração de avisos tem permissão própria.
 */
final class AvisoAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer aculta avisos')) {
      return AccessResult::allowed()->addCacheContexts(['user.permissions']);
    }
    $uid = $entity instanceof ContentEntityInterface ? (int) $entity->get('uid')->target_id : 0;
    return AccessResult::allowedIf($operation === 'view' && $uid > 0 && $uid === (int) $account->id())
      ->addCacheContexts(['user'])
      ->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta avisos');
  }

}
