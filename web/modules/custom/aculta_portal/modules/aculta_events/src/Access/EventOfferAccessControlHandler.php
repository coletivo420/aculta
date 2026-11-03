<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Keeps the structural offer entity closed until organizer workflows exist.
 */
final class EventOfferAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    $access = $account->hasPermission('administer aculta events')
      ? AccessResult::allowed()
      : AccessResult::forbidden();
    return $access->cachePerPermissions()
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return ($account->hasPermission('administer aculta events')
      ? AccessResult::allowed()
      : AccessResult::forbidden())
      ->cachePerPermissions();
  }

}
