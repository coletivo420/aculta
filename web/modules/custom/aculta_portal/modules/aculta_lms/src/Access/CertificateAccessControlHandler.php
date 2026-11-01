<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Keeps certificate records out of generic entity and Entity Print routes.
 *
 * Public verification and PDF delivery must pass the opaque-code controller,
 * which checks the current certificate state before rendering any snapshot.
 */
final class CertificateAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    return AccessResult::forbidden()->addCacheableDependency($entity);
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::forbidden();
  }

}
