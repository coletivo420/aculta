<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Donation;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\aculta_donation\Controller\DonationController;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Doação rápida (/apoiar/{valor}/agora) com etapa de resumo antes do pagamento.
 *
 * Reaproveita a criação de pedido do submódulo aculta_donation (port de commerce_donation_flow) e só muda o passo de destino: `review` (resumo)
 * em vez de `payment`. O resumo lista a doação e os demais panes ativos, com link para editar, e o botão leva ao pagamento.
 */
final class DonationSummaryController implements ContainerInjectionInterface {

  public function __construct(
    private readonly ClassResolverInterface $classResolver,
    private readonly CartSessionInterface $cartSession,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('class_resolver'),
      $container->get('commerce_cart.cart_session'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Cria a doação com o valor da rota e redireciona para o resumo.
   */
  public function quick(RouteMatchInterface $route_match): RedirectResponse {
    $contrib = $this->classResolver->getInstanceFromDefinition(DonationController::class);
    // Cria o pedido e o valor (comportamento do módulo). A resposta original vai para payment; substituímos.
    $contrib->quickDonation($route_match);

    $ids = $this->cartSession->getCartIds();
    $pedidoId = end($ids);
    $pedido = $pedidoId ? $this->entityTypeManager->getStorage('commerce_order')->load($pedidoId) : NULL;
    if ($pedido === NULL) {
      return new RedirectResponse(Url::fromRoute('aculta_donation.donation.default')->toString());
    }
    // O atalho grava o valor em field_donation_amount, mas o preço unitário só é definido ao enviar o passo de
    // doação (DonationItemPaneBase::buildDonationItem), que este atalho pula. Sem isto, o pedido sai com total zero.
    foreach ($pedido->getItems() as $item) {
      if ($item->bundle() === 'donation' && $item->hasField('field_donation_amount') && !$item->field_donation_amount->isEmpty()) {
        $item->setUnitPrice($item->field_donation_amount->first()->toPrice(), TRUE);
        $item->save();
      }
    }
    $pedido->set('checkout_step', 'review');
    $pedido->save();

    $url = Url::fromRoute('aculta_donation.donation_controller_formPage', [
      'commerce_order' => $pedido->id(),
      'step' => 'review',
    ]);
    return new RedirectResponse($url->toString());
  }

}
