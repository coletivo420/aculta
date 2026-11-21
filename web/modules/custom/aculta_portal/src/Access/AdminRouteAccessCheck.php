<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Access;

use Drupal\Core\Access\AccessCheckInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Garante que rotas administrativas sejam acessadas exclusivamente por administrator.
 *
 * Política transversal ACULTA:
 * Somente contas com o papel institucional 'administrator' (ou UID 1) acessam o backend,
 * incluindo todas as rotas com _admin_route: true, prefixo /painel-administrativo/ ou /admin/.
 * Papéis operacionais não administradores (ex.: moderador, content_editor, lms_teacher)
 * recebem neutral/forbidden conforme as regras institucionais e operam no frontend.
 */
final class AdminRouteAccessCheck implements AccessCheckInterface {

  public const ADMIN_PREFIX_PT = '/painel-administrativo';
  public const ADMIN_PREFIX_CORE = '/admin';

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route): bool {
    // Aplica-se se a rota está explicitamente marcada como _admin_route
    // ou se seu caminho inicia com o prefixo administrativo.
    if ((bool) $route->getOption('_admin_route')) {
      return TRUE;
    }

    $path = $route->getPath();
    return str_starts_with($path, self::ADMIN_PREFIX_PT . '/')
      || $path === self::ADMIN_PREFIX_PT
      || str_starts_with($path, self::ADMIN_PREFIX_CORE . '/')
      || $path === self::ADMIN_PREFIX_CORE;
  }

  /**
   * Verifica o acesso para rotas administrativas.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   A rota acessada.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A conta do usuário atual.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   AccessResult::allowed() se for administrador ou UID 1; senão AccessResult::forbidden().
   */
  public function access(Route $route, AccountInterface $account): AccessResultInterface {
    // UID 1 sempre tem acesso de superusuário do Drupal.
    if ((int) $account->id() === 1) {
      return AccessResult::allowed()->addCacheContexts(['user']);
    }

    // Apenas quem possui a role 'administrator' pode acessar o backend.
    if (in_array('administrator', $account->getRoles(), TRUE)) {
      return AccessResult::allowed()->addCacheContexts(['user.roles']);
    }

    return AccessResult::forbidden('Acesso ao backend administrativo restrito a administradores.')
      ->addCacheContexts(['user.roles']);
  }

}
