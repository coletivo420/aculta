<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Assigns Portuguese admin paths to the native Site Alert management routes. */
final class NoticeAdminRouteSubscriber extends RouteSubscriberBase {

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    // Run after DomainRouteSubscriber and Rename Admin Paths have finalized
    // administrative paths, then put the native Site Alert forms in the ACULTA
    // Avisos URL namespace.
    return [RoutingEvents::ALTER => [['onAlterRoutes', -2050]]];
  }

  /** {@inheritdoc} */
  protected function alterRoutes(RouteCollection $collection): void {
    $paths = [
      'entity.site_alert.collection' => '/painel-administrativo/avisos/temporarios/lista',
      'entity.site_alert.add_form' => '/painel-administrativo/avisos/temporarios/criar',
      'entity.site_alert.edit_form' => '/painel-administrativo/avisos/temporarios/{site_alert}/editar',
      'entity.site_alert.delete_form' => '/painel-administrativo/avisos/temporarios/{site_alert}/excluir',
    ];
    foreach ($paths as $name => $path) {
      if ($route = $collection->get($name)) {
        $route->setPath($path);
      }
    }

    // Reuse Core's native edit/delete form routes while limiting these ACULTA
    // paths to the persistent notice bundle.
    foreach ([
      'entity.node.edit_form' => [
        'aculta_messenger.admin_persistent_notice_edit',
        '/painel-administrativo/avisos/persistentes/{node}/editar',
      ],
      'entity.node.delete_form' => [
        'aculta_messenger.admin_persistent_notice_delete',
        '/painel-administrativo/avisos/persistentes/{node}/excluir',
      ],
    ] as $sourceName => [$name, $path]) {
      $source = $collection->get($sourceName);
      if (!$source instanceof Route) {
        continue;
      }
      $route = clone $source;
      $route->setPath($path);
      $route->setRequirement('_custom_access', 'aculta_messenger.persistent_notice_route_access:access');
      $collection->add($name, $route);
    }
  }

}
