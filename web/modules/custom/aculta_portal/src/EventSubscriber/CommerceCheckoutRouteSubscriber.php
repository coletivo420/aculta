<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\aculta_portal\Checkout\CheckoutPolicyManager;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Disponibilidade das rotas globais de carrinho e checkout do Commerce, conforme a política de checkout.
 *
 * Comportamento idêntico ao `CartCheckoutRouteSubscriber` do `aculta_donation`, que ele substitui: com a política
 * fechada, `commerce_cart.page`, `commerce_checkout.checkout` e `commerce_checkout.form` passam a `_access: FALSE`
 * (as duas últimas sem `_permission`). Não escolhe fluxo nem guarda preferência; as rotas continuam as do Commerce.
 *
 * Origem: coordenação portada do `CartCheckoutRouteSubscriber` de `commerce_donation_flow` 1.2.0
 * (GPL-2.0-or-later, Shawn Duncan e colaboradores), via `aculta_donation`. Licença em `aculta_donation/LICENSE.txt`.
 *
 * @see https://www.drupal.org/node/2187643
 */
final class CommerceCheckoutRouteSubscriber extends RouteSubscriberBase {

  public function __construct(private readonly CheckoutPolicyManager $policy) {}

  protected function alterRoutes(RouteCollection $collection): void {
    if ($this->policy->commonCheckoutRoutesAvailable()) {
      return;
    }
    $deny = 'FALSE';
    $cartRoute = $collection->get('commerce_cart.page');
    if ($cartRoute instanceof Route) {
      $cartRoute->setRequirement('_access', $deny);
    }
    foreach (['commerce_checkout.checkout', 'commerce_checkout.form'] as $name) {
      $route = $collection->get($name);
      if ($route instanceof Route) {
        $requirements = $route->getRequirements();
        unset($requirements['_permission']);
        $requirements['_access'] = $deny;
        $route->setRequirements($requirements);
      }
    }
  }

}
