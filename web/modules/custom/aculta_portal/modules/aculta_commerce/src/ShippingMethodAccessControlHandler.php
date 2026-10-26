<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for ACULTA shipping methods.
 *
 * Shipping methods are internal configuration-like records. Viewing their
 * labels and rates is public; changing them requires the administrative
 * permission.
 */
class ShippingMethodAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer aculta shipping methods')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if (in_array($operation, ['view', 'view label'], TRUE)) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return AccessResult::forbidden()->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta shipping methods');
  }

}
