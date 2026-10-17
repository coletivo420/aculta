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

  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(array &$links): void {
    foreach (self::DUPLICADOS as $id) {
      if (isset($links[$id])) {
        $links[$id]['enabled'] = FALSE;
      }
    }
  }

}
