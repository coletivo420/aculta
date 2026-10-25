<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Restricts Wiki proposals to their author and authorized editorial staff.
 */
final class WikiProposalAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer wiki proposals')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if (in_array($operation, ['view', 'view label', 'view revision'], TRUE)) {
      $canViewAny = AccessResult::allowedIfHasPermission($account, 'view any wiki proposals');
      $isOwner = (int) $entity->getOwnerId() === (int) $account->id();
      $canViewOwn = AccessResult::allowedIf($isOwner)
        ->andIf(AccessResult::allowedIfHasPermission($account, 'view own wiki proposals'))
        ->cachePerUser();
      return $canViewAny->orIf($canViewOwn);
    }

    if (in_array($operation, ['update', 'delete', 'revert', 'delete revision'], TRUE)) {
      return AccessResult::allowedIfHasPermission($account, 'moderate wiki proposals');
    }

    return AccessResult::neutral()->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'submit wiki proposals')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'administer wiki proposals'));
  }

}
