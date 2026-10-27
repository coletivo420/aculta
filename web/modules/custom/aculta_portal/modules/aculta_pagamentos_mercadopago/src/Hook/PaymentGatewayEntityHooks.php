<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Access policy for the Mercado Pago gateway configuration.
 */
final class PaymentGatewayEntityHooks {

  /**
   * Denies direct edits to the Mercado Pago gateway.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    if ($entity->getEntityTypeId() !== 'commerce_payment_gateway'
      || $entity->id() !== 'mercado_pago'
      || $operation !== 'update') {
      return AccessResult::neutral();
    }

    return AccessResult::forbidden(
      'This gateway is managed through reviewed configuration and environment values; editing it here could persist credentials.',
    );
  }

}
