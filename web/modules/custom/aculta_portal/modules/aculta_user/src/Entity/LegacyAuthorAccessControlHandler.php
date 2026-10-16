<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Acesso à autoria histórica: só quem administra as autorias legadas (ACULTA Fórum, F2).
 *
 * O público lê o nome pelo memorial, que é controlado pelo próprio controller. Esta entidade nunca é visível
 * diretamente nem para visitante nem para conta comum.
 */
final class LegacyAuthorAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta legado');
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta legado');
  }

}
