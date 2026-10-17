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
   * Raízes de configuração e de pessoas com slugs em português (decisão do responsável, 2026-10-17).
   */
  private const RAIZES_TRADUZIDAS = ['/painel-administrativo/configuracoes/people', '/painel-administrativo/pessoas'];

  /**
   * Segmentos traduzidos nas raízes acima. Segmento fora da lista permanece como está.
   */
  private const SEGMENTOS = [
    'people' => 'pessoas',
    'accounts' => 'contas',
    'profile-types' => 'campos-de-perfil',
    'fields' => 'campos',
    'add-field' => 'adicionar-campo',
    'form-display' => 'formulario-exibicao',
    'display' => 'exibicao',
    'roles' => 'papeis',
    'permissions' => 'permissoes',
    'manage' => 'gerenciar',
    'translate' => 'traduzir',
    'add' => 'adicionar',
    'edit' => 'editar',
    'delete' => 'excluir',
    'duplicate' => 'duplicar',
    'disable' => 'desativar',
    'enable' => 'ativar',
    'profiles' => 'perfis',
    'captcha-points' => 'pontos-captcha',
    'agreement' => 'acordo',
    'agreements' => 'acordos',
  ];

  /**
   * Roda por último: rotas registradas por outros alteradores (ex.: ui_patterns_library) também recebem a regra.
   */
  public static function getSubscribedEvents(): array {
    // Roda depois da renomeação /admin -> /painel-administrativo (prioridade -2048, no Drupal e no DomainRouteSubscriber):
    // sem isso, as raízes do painel ainda não existem no caminho quando esta classe troca os segmentos.
    return [RoutingEvents::ALTER => [['onAlterRoutes', -3000]]];
  }

  public function onAlterRoutes(RouteBuildEvent $event): void {
    $this->alterRoutes($event->getRouteCollection());
  }

  /**
   * Caminho com os segmentos traduzidos, se o caminho está em uma das raízes; senão, o próprio caminho.
   */
  private function traduzirCaminho(string $caminho): string {
    foreach (self::RAIZES_TRADUZIDAS as $raiz) {
      if (str_starts_with($caminho, $raiz)) {
        return implode('/', array_map(fn (string $segmento): string => self::SEGMENTOS[$segmento] ?? $segmento, explode('/', $caminho)));
      }
    }
    return $caminho;
  }

  protected function alterRoutes(RouteCollection $collection): void {
    // A tela do módulo contrib role_inheritance sai da coleção de rotas (sem aba nem link de menu).
    $collection->remove('role_inheritance.config_role_inheritance');
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
    // Slugs em português nas raízes de configuração e de pessoas. A rota é substituída por uma nova, e não só tem o
    // caminho trocado: rota já compilada mantém o caminho antigo no índice de rotas.
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
