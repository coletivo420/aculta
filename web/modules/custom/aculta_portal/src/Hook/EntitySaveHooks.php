<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_pagamentos_mercadopago\Commerce\MercadoPago\MercadoPagoCredentials;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Entity lifecycle guards owned by the Portal.
 */
final class EntitySaveHooks {

  /**
   * Keeps the Mercado Pago gateway fail-closed on save.
   *
   * Enabling it requires credentials for the declared environment.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() !== 'commerce_payment_gateway'
      || $entity->id() !== MercadoPagoCredentials::GATEWAY_ID
      || !$entity->status()) {
      return;
    }

    $environment = MercadoPagoCredentials::currentEnvironment(dirname(DRUPAL_ROOT));
    if (!MercadoPagoCredentials::hasRuntimeCredentials($environment)) {
      throw new \LogicException(sprintf(
        'The Mercado Pago gateway cannot be enabled in the "%s" environment without its Public Key and Access Token.',
        $environment,
      ));
    }
  }

}
