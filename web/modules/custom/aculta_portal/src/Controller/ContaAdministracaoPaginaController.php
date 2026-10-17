<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Página-base de Configurações > Pessoas. Abre a primeira aba (Papéis); as ferramentas de administração de contas são
 * abas desta seção.
 *
 * Substitui a lista de links do Core (SystemController::systemAdminMenuBlockPage), que negaria acesso sem itens visíveis.
 * Sem página própria, a entrada não fica vazia: a aba só aparece numa página que tenha aba apontando para ela.
 */
final class ContaAdministracaoPaginaController extends ControllerBase {

  public function pagina(): RedirectResponse {
    return $this->redirect('entity.user_role.collection');
  }

}
