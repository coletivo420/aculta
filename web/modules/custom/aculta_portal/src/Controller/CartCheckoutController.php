<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\aculta_portal\Cart\CartConsolidator;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * "Finalizar carrinho": junta os itens em um pedido só e abre o checkout do Commerce, no domínio principal.
 */
final class CartCheckoutController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'aculta_portal.cart_consolidator')]
    private readonly CartConsolidator $consolidator,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposes,
  ) {}

  public function finalizar(): RedirectResponse {
    $pedido = $this->consolidator->consolidar();
    if ($pedido === NULL) {
      return new RedirectResponse(\Drupal\Core\Url::fromRoute('commerce_cart.page')->toString());
    }
    // Passo inicial: o Commerce corrige para o passo correto quando o passo pedido não existe no fluxo.
    $passo = $pedido->get('checkout_step')->value ?: 'login';
    $url = $this->purposes->routeUrl('main', 'commerce_checkout.form', [
      'commerce_order' => $pedido->id(),
      'step' => $passo,
    ]);
    return new TrustedRedirectResponse($url !== NULL ? $url->toString() : '/');
  }

}
