<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Painel administrativo (MAIN): o item "Editar perfil" do Navigation não aponta para a identidade da Conta.
 *
 * `navigation.user_links.user.edit` apontava para `user.edit`, a rota de identidade do purpose ACCOUNT
 * (/identidade/editar), que responde 404 em MAIN. O item passa a usar aculta_portal.admin_self_edit,
 * que leva ao formulário do próprio usuário logado.
 */
final class AdminNavigationHooks {

  /** Item do menu do módulo Navigation para a edição do próprio perfil. */
  private const OWN_PROFILE_EDIT_LINK = 'navigation.user_links.user.edit';

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(array &$links): void {
    if (isset($links[self::OWN_PROFILE_EDIT_LINK])) {
      $links[self::OWN_PROFILE_EDIT_LINK]['route_name'] = 'aculta_portal.admin_self_edit';
    }
  }

}
