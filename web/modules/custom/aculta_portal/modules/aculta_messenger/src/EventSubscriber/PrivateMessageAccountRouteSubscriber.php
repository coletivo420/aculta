<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\EventSubscriber;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Assigns native Private Message routes to the single account purpose. */
final class PrivateMessageAccountRouteSubscriber implements EventSubscriberInterface {

  /**
   * Declara a assinatura das rotas, depois do DomainRouteSubscriber.
   *
   * @return array
   *   Mapa de eventos para os métodos tratadores.
   */
  public static function getSubscribedEvents(): array {
    // Runs after the Portal's DomainRouteSubscriber (-2049).
    return [RoutingEvents::ALTER => ['onAlterRoutes', -2050]];
  }

  /**
   * Atribui as rotas nativas de mensagens privadas ao propósito de conta.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   Evento de construção das rotas.
   */
  public function onAlterRoutes(RouteBuildEvent $event): void {
    foreach ($event->getRouteCollection() as $route) {
      $path = $route->getPath();
      if (($path === '/private-messages' || str_starts_with($path, '/private-messages/'))
        || ($path === '/private-message' || str_starts_with($path, '/private-message/'))) {
        if (!$route->getOption('_admin_route')) {
          $route->setOption('_aculta_domain_purpose', 'account');
        }
      }
    }
  }

}
