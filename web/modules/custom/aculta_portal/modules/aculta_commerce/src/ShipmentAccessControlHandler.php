<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for ACULTA shipments.
 *
 * Customers may view shipments that belong to their own orders. Operational
 * changes require the administrative permission; the checkout creates and
 * updates shipments during order processing without an entity access check.
 */
class ShipmentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\aculta_commerce\Entity\ShipmentInterface $entity */
    if ($account->hasPermission('administer aculta shipments')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if ($operation === 'view') {
      $order = $entity->getOrder();
      $is_owner = $order && (int) $order->getCustomerId() === (int) $account->id();
      return AccessResult::allowedIf($is_owner)
        ->cachePerUser()
        ->addCacheableDependency($entity);
    }
    return AccessResult::forbidden()->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer aculta shipments',
      'access checkout',
    ], 'OR');
  }

}
