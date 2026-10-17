<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Abas de Pessoas: só o que é de usuário individual (lista, cadastro, perfis e OAuth).
 *
 * Administração de contas (papéis, permissões por papel, configuração de papel, campos de perfil e flood unblock)
 * fica em Configurações > Pessoas (ver aculta_portal.links.menu.yml). Só as abas de base /pessoas saem: as abas das
 * próprias páginas de papéis e de configuração continuam.
 */
final class AdministrativeLocalTaskHooks {

  /** Abas de Pessoas migradas para Configurações > Pessoas. */
  private const MIGRADAS = [
    'entity.user_role.collection',
    'user.admin_permissions',
    'user.role.settings',
    'aculta_portal.campos_de_perfil',
    'flood_control.flood_unblock_task',
  ];

  #[Hook('local_tasks_alter')]
  public function localTasksAlter(array &$local_tasks): void {
    foreach (self::MIGRADAS as $id) {
      if (isset($local_tasks[$id]) && ($local_tasks[$id]['base_route'] ?? NULL) === 'entity.user.collection') {
        unset($local_tasks[$id]);
      }
    }
    // Perfis de usuário: nome em português na seção Pessoas.
    if (isset($local_tasks['entity.profile.collection']) && ($local_tasks['entity.profile.collection']['base_route'] ?? NULL) === 'entity.user.collection') {
      $local_tasks['entity.profile.collection']['title'] = new TranslatableMarkup('Perfis');
    }
  }

}
