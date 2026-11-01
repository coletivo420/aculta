<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Página do carrinho em português (`/carrinho`), conforme docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md.
 *
 * Troca só o caminho da rota do Commerce, sem alterar o nome da rota nem as rotas de checkout e pagamento.
 * O caminho antigo (`/cart`) responde 404: slug publicado é estável e não há redirecionamento.
 */
final class CartFriendlyRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('commerce_cart.page');
    if ($route === NULL) {
      return;
    }
    $route->setPath('/carrinho');
  }

}
