<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Menu administrativo sem itens duplicados das abas de Pessoas.
 *
 * Papéis e campos de perfil já aparecem como abas da seção Pessoas. Os itens de menu que levam às mesmas páginas são
 * desativados, para que cada página tenha um único nome na navegação.
 */
final class AdministrativeMenuHooks {

  /** Itens do menu desativados por duplicarem uma aba de Pessoas. */
  private const DUPLICADOS = [
    'aculta_portal.role_hierarchy_settings',
    'entity.profile_type.collection',
  ];

  /**
   * Itens de Configurações > Pessoas que viram abas da própria página (ver aculta_portal.links.task.yml). Saem da lista
   * da página para que cada ferramenta tenha um único nome.
   */
  private const EM_ABAS = [
    'aculta_portal.config_papeis_e_perfis',
    'aculta_portal.config_permissoes_por_papel',
    'aculta_portal.config_configuracoes_de_papel',
    'aculta_portal.config_campos_de_perfil',
    'aculta_portal.config_flood_unblock',
    'captcha.settings',
    'flood_control.admin',
    'entity.user.admin_form',
    'agreement.admin',
  ];

  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(array &$links): void {
    foreach (array_merge(self::DUPLICADOS, self::EM_ABAS) as $id) {
      if (isset($links[$id])) {
        $links[$id]['enabled'] = FALSE;
      }
    }
  }

}
