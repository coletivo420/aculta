<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Fluxo de doação em português (`/apoiar`), conforme docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md.
 *
 * Troca só os caminhos das rotas do módulo de doação do Commerce. Os nomes das rotas e os parâmetros não mudam.
 * O caminho antigo (`/donate`) responde 404 após o deploy: slug publicado é estável e não há redirecionamento.
 */
final class DonationFriendlyRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $paths = [
      'aculta_donation.donation.default' => '/apoiar',
      'aculta_donation.donation.quick' => '/apoiar/{amount}/agora',
      'aculta_donation.donation_controller_formPage' => '/apoiar/{commerce_order}/{step}',
    ];
    foreach ($paths as $name => $path) {
      $route = $collection->get($name);
      if ($route !== NULL) {
        $route->setPath($path);
      }
    }
  }

}
