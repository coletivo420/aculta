<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Checkout flow nativo associado a um tipo de pedido do Commerce.
 *
 * O vínculo vive na entidade do tipo de pedido (configuração do `commerce_checkout`); este serviço só lê,
 * para que os providers declarem o flow sem duplicar nem reatribuir a decisão do Commerce.
 */
final class OrderTypeCheckoutFlows {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /** NULL quando o tipo de pedido não existe ou não tem flow associado. */
  public function flowId(string $orderTypeId): ?string {
    if (!$this->entityTypeManager->hasDefinition('commerce_order_type')) {
      return NULL;
    }
    $type = $this->entityTypeManager->getStorage('commerce_order_type')->load($orderTypeId);
    $flow = $type?->getThirdPartySetting('commerce_checkout', 'checkout_flow');
    return is_string($flow) && $flow !== '' ? $flow : NULL;
  }

}
