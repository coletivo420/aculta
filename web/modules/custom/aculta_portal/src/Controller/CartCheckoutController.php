<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\aculta_portal\Cart\CartConsolidator;
use Drupal\aculta_portal\Checkout\CheckoutStepResolver;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Finalizes the cart and opens the Commerce checkout on the main domain.
 */
final class CartCheckoutController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'aculta_portal.cart_consolidator')]
    private readonly CartConsolidator $consolidator,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposes,
    #[Autowire(service: 'aculta_portal.checkout_step_resolver')]
    private readonly CheckoutStepResolver $checkoutStepResolver,
  ) {}

  /**
   * Redirects the consolidated cart to the first valid checkout step.
   */
  public function finalizar(): RedirectResponse {
    $pedido = $this->consolidator->consolidar();
    if ($pedido === NULL) {
      return new RedirectResponse(Url::fromRoute('commerce_cart.page')->toString());
    }
    // A etapa salva pode estar oculta para a sessão atual (ex.: o visitante já
    // entrou e o pane de login deixou de existir). Nunca direciona para uma
    // etapa invisível, pois o Commerce pode conservar esse valor e renderizar o
    // login.
    $passo = $this->checkoutStepResolver->resolve($pedido);
    $url = $this->purposes->routeUrl('main', 'commerce_checkout.form', [
      'commerce_order' => $pedido->id(),
      'step' => $passo,
    ]);
    return new TrustedRedirectResponse($url !== NULL ? $url->toString() : '/');
  }

}
