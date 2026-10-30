<?php

declare(strict_types=1);

namespace Drupal\aculta_events\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds the protected administrative calendar export route.
 */
final class AdministrativeExportRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $collection->add('aculta_events.admin_export', new Route(
      '/painel-administrativo/eventos/exportar',
      [
        '_controller' => '\\Drupal\\aculta_events\\Controller\\AdministrativeEventExportController::export',
        '_title' => 'Exportar eventos',
      ],
      ['_permission' => 'administer aculta events'],
      [
        '_admin_route' => TRUE,
        '_aculta_domain_purpose' => 'eventos',
        '_aculta_domain_purpose_authoritative' => TRUE,
      ],
    ));
  }

}
