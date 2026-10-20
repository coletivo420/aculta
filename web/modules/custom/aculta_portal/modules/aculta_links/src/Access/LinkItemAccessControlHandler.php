<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_links\Entity\LinkItem;

/**
 * Restricts a page item to the owner of its page and destination.
 */
final class LinkItemAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer aculta links')) {
      return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
    }

    if (!$entity instanceof LinkItem || !in_array($operation, [
      'view',
      'update',
      'delete',
      'view revision',
      'view all revisions',
      'revert',
      'delete revision',
    ], TRUE)) {
      return AccessResult::forbidden()->cachePerPermissions()->addCacheableDependency($entity);
    }

    $page = $entity->get('page')->entity;
    $link = $entity->get('link')->entity;
    if (!$page || !$link) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    $can_manage = $account->hasPermission('manage own aculta links');
    $owns_item = (string) $entity->getOwnerId() === (string) $account->id();
    $owns_page = (string) $page->getOwnerId() === (string) $account->id();
    $owns_link = (string) $link->getOwnerId() === (string) $account->id();
    $result = AccessResult::allowedIf($can_manage && $owns_item && $owns_page && $owns_link)
      ->cachePerUser()
      ->cachePerPermissions()
      ->addCacheableDependency($entity)
      ->addCacheableDependency($page)
      ->addCacheableDependency($link);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'manage own aculta links')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'administer aculta links'));
  }

}
