<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controle de acesso a locais de evento (aculta_event_location).
 *
 * Locais são públicos para visualização por padrão, mas respeitam hooks
 * de acesso, purposes do portal e permissão administrativa para mutações.
 */
final class EventLocationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($operation === 'view') {
      return AccessResult::allowed()->addCacheableDependency($entity);
    }

    return AccessResult::allowedIfHasPermission($account, 'administer aculta events');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta events');
  }

}
