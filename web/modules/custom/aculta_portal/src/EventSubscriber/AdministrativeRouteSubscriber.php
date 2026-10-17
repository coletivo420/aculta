<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\Core\Routing\RouteSubscriberBase;
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
   * Roda por último: rotas registradas por outros alteradores (ex.: ui_patterns_library) também recebem a regra.
   */
  public static function getSubscribedEvents(): array {
    return [RoutingEvents::ALTER => [['onAlterRoutes', -1000]]];
  }

  public function onAlterRoutes(RouteBuildEvent $event): void {
    $this->alterRoutes($event->getRouteCollection());
  }

  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($collection->all() as $route) {
      // Só rotas sem decisão explícita: `FALSE` definido por um módulo (ex.: biblioteca de componentes do ui_patterns) é respeitado.
      if (str_starts_with($route->getPath(), self::PREFIXO) && $route->getOption('_admin_route') === NULL) {
        $route->setOption('_admin_route', TRUE);
      }
    }
  }

}
