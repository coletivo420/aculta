<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Página-base de Configurações > Pessoas. As ferramentas de administração de contas aparecem como abas desta página.
 *
 * Substitui a lista de links do Core (SystemController::systemAdminMenuBlockPage), que negaria acesso sem itens visíveis.
 */
final class ContaAdministracaoPaginaController extends ControllerBase {

  public function pagina(): array {
    return [
      '#markup' => '<p>' . $this->t('Ferramentas de administração de contas: papéis, permissões por papel, campos de perfil e controles de acesso. Escolha uma aba.') . '</p>',
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

}
