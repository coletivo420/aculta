<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Hook;

use Drupal\aculta_pagamentos_mercadopago\Commerce\MercadoPago\MercadoPagoCredentials;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Mercado Pago entity lifecycle guards.
 */
final class EntitySaveHooks {

  /**
   * Keeps the Mercado Pago gateway active without persisting credentials.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() !== 'commerce_payment_gateway'
      || $entity->id() !== MercadoPagoCredentials::GATEWAY_ID) {
      return;
    }

    // Config overrides supply runtime keys in memory; never persist them.
    $entity->setPluginConfiguration(MercadoPagoCredentials::clearCredentialValues($entity->getPluginConfiguration()));
  }

}
