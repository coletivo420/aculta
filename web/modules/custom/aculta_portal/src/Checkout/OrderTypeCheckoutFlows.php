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

  /** Flow que o Commerce usa quando o tipo de pedido não define nenhum (DefaultCheckoutFlowResolver). */
  public const COMMERCE_DEFAULT_FLOW = 'default';

  /**
   * Flow efetivo do tipo de pedido, com o mesmo fallback do Commerce; NULL quando o tipo não existe.
   */
  public function flowId(string $orderTypeId): ?string {
    if (!$this->entityTypeManager->hasDefinition('commerce_order_type')) {
      return NULL;
    }
    $type = $this->entityTypeManager->getStorage('commerce_order_type')->load($orderTypeId);
    if ($type === NULL) {
      return NULL;
    }
    $flow = $type->getThirdPartySetting('commerce_checkout', 'checkout_flow');
    return is_string($flow) && $flow !== '' ? $flow : self::COMMERCE_DEFAULT_FLOW;
  }

}
