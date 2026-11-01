<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Toda rota do painel administrativo usa o tema administrativo, inclusive as de módulos contrib e do Core.
 *
 * O tema administrativo é aplicado pela opção `_admin_route`. Rotas do Core e de contrib (layout builder, traduções
 * de configuração, telas de entidade sem AdminHtmlRouteProvider) nem sempre a definem; sem ela, a página carrega o
 * tema global (ACULTA420). Esta regra cobre o prefixo do painel em um único ponto, para módulos atuais e futuros.
 * Uma decisão explícita do módulo dono (`_admin_route` definido, inclusive FALSE) prevalece.
 */
final class AdministrativeRouteSubscriber extends RouteSubscriberBase {

  private const PREFIXO = '/painel-administrativo';

  /**
   * Árvore de configuração de pessoas, com slugs em português. Só esta árvore é traduzida: a área operacional de pessoas
   * (/painel-administrativo/pessoas) mantém os caminhos do Core.
   */
  private const RAIZ_CONFIGURACAO_PESSOAS = '/painel-administrativo/configuracoes/people';

  /**
   * Ferramentas de administração de contas que saem de /pessoas (usuário individual) para Configurações > Pessoas.
   * Cada entrada move o caminho e todas as subpáginas dele. OAuth e perfis individuais continuam em /pessoas.
   */
  /**
   * Caminhos de usuário individual que ficam em /pessoas, com o nome em português.
   */
  private const RENOMEADOS_EM_PESSOAS = [
    '/painel-administrativo/pessoas/profiles' => '/painel-administrativo/pessoas/perfis',
  ];

  private const MOVIDOS_PARA_CONFIGURACAO = [
    '/painel-administrativo/pessoas/roles' => '/painel-administrativo/configuracoes/pessoas/papeis',
    '/painel-administrativo/pessoas/permissions' => '/painel-administrativo/configuracoes/pessoas/permissoes',
    '/painel-administrativo/pessoas/role-settings' => '/painel-administrativo/configuracoes/pessoas/configuracoes-de-papel',
    '/painel-administrativo/pessoas/flood-unblock' => '/painel-administrativo/configuracoes/pessoas/desbloqueio-sobrecarga',
  ];

  /**
   * Segmentos traduzidos dentro da árvore de configuração de pessoas.
   */
  private const SEGMENTOS = [
    'people' => 'pessoas',
    'accounts' => 'contas',
    'profile-types' => 'campos-de-perfil',
    'fields' => 'campos',
    'form-display' => 'formulario-exibicao',
    'display' => 'exibicao',
    'manage' => 'gerenciar',
    'translate' => 'traduzir',
    'add' => 'adicionar',
    'edit' => 'editar',
    'delete' => 'excluir',
    'duplicate' => 'duplicar',
    'disable' => 'desativar',
    'enable' => 'ativar',
    'captcha-points' => 'pontos-captcha',
    'flood-control' => 'controle-sobrecarga',
  ];

  /**
   * Roda por último: rotas registradas por outros alteradores (ex.: ui_patterns_library) também recebem a regra.
   */
  public static function getSubscribedEvents(): array {
    // Roda depois da renomeação /admin -> /painel-administrativo (prioridade -2048): só assim a raiz do painel já existe
    // no caminho quando a tradução dos segmentos olha para ela.
    return [RoutingEvents::ALTER => [['onAlterRoutes', -3000]]];
  }

  public function onAlterRoutes(RouteBuildEvent $event): void {
    $this->alterRoutes($event->getRouteCollection());
  }

  /**
   * Caminho com os segmentos traduzidos, se está na árvore de configuração de pessoas; senão, o próprio caminho.
   */
  private function traduzirCaminho(string $caminho): string {
    foreach (self::RENOMEADOS_EM_PESSOAS as $antigo => $novo) {
      if ($caminho === $antigo) {
        return $novo;
      }
    }
    foreach (self::MOVIDOS_PARA_CONFIGURACAO as $antigo => $novo) {
      if ($caminho === $antigo || str_starts_with($caminho, $antigo . '/')) {
        $caminho = $novo . substr($caminho, strlen($antigo));
        break;
      }
    }
    if (!str_starts_with($caminho, self::RAIZ_CONFIGURACAO_PESSOAS) && !str_starts_with($caminho, '/painel-administrativo/configuracoes/pessoas')) {
      return $caminho;
    }
    return implode('/', array_map(fn (string $segmento): string => self::SEGMENTOS[$segmento] ?? $segmento, explode('/', $caminho)));
  }

  protected function alterRoutes(RouteCollection $collection): void {
    // A página de papéis do Core (/painel-administrativo/pessoas/roles) é substituída pela página Papéis e perfis do
    // ACULTA: cadeia de papéis, operações de cada papel (mesmo menu do Core) e criação de papel.
    if ($lista = $collection->get('entity.user_role.collection')) {
      $padroes = $lista->getDefaults();
      unset($padroes['_entity_list']);
      $padroes['_form'] = '\\Drupal\\aculta_portal\\Role\\Form\\RoleHierarchyForm';
      $padroes['_title'] = 'Papéis e perfis';
      $lista->setDefaults($padroes);
      $requisitos = $lista->getRequirements();
      unset($requisitos['_permission']);
      $requisitos['_permission'] = 'administer permissions,administer aculta role hierarchy';
      $lista->setRequirements($requisitos);
    }
    // Tipos de perfil (Core) aparecem em Pessoas como a aba "Campos de Perfil".
    if ($tipos = $collection->get('entity.profile_type.collection')) {
      $padroes = $tipos->getDefaults();
      $padroes['_title'] = 'Campos de Perfil';
      $tipos->setDefaults($padroes);
    }
    // Configurações > Pessoas: página-base das abas de administração de contas (sem a lista de links do Core).
    if ($base = $collection->get('user.admin_index')) {
      $padroes = $base->getDefaults();
      $padroes['_controller'] = '\\Drupal\\aculta_portal\\Controller\\ContaAdministracaoPaginaController::pagina';
      $base->setDefaults($padroes);
      $requisitos = $base->getRequirements();
      unset($requisitos['_access_admin_menu_block_page']);
      $base->setRequirements($requisitos);
    }
    // Slugs em português na árvore de configuração de pessoas. A rota é substituída por outra, e não só tem o caminho
    // trocado: rota já compilada mantém o caminho antigo no índice de rotas.
    foreach ($collection->all() as $nome => $route) {
      $traduzido = $this->traduzirCaminho($route->getPath());
      if ($traduzido !== $route->getPath()) {
        $collection->add($nome, new Route($traduzido, $route->getDefaults(), $route->getRequirements(), $route->getOptions(), $route->getHost(), $route->getSchemes(), $route->getMethods(), $route->getCondition()));
        $route = $collection->get($nome);
      }
      // Só rotas sem decisão explícita: `FALSE` definido por um módulo (ex.: biblioteca de componentes do ui_patterns) é respeitado.
      if (str_starts_with($route->getPath(), self::PREFIXO) && $route->getOption('_admin_route') === NULL) {
        $route->setOption('_admin_route', TRUE);
      }
    }
  }

}
