<?php

declare(strict_types=1);

namespace Drupal\aculta_ui_library\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controle de acesso estrito para as rotas da UI Library.
 *
 * Exige simultaneamente:
 * 1. Permissão 'administer blocks'.
 * 2. Papel de administrador ('administrator') ou UID 1.
 *
 * Nenhum usuário comum ou editor com permissão concedida acidentalmente pode
 * acessar as prévias reais de blocos administrativos.
 */
final class UiLibraryAccessCheck implements AccessInterface {

  /**
   * Verifica o acesso administrativo da UI Library.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $hasPermission = $account->hasPermission('administer blocks');
    $isAdmin = in_array('administrator', $account->getRoles(), TRUE) || (int) $account->id() === 1;

    return AccessResult::allowedIf($hasPermission && $isAdmin)
      ->addCacheContexts(['user.roles', 'user.permissions'])
      ->setCacheMaxAge(0);
  }

}
