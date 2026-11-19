<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Permission gate for future Green Stash entrypoints.
 *
 * This does not grant access to any Commerce entity: operations must still
 * enforce Commerce's native per-entity and per-store access handlers.
 */
final class GreenStashAccessPolicy {

  public const ACCESS = 'access aculta green stash';

  /**
   * Verifica a permissão de acesso ao Green Stash.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Conta verificada.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Resultado permitido quando a conta tem a permissão exigida.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, self::ACCESS);
  }

}
