<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Hook;

use Drupal\aculta_pagamentos_mercadopago\Commerce\MercadoPago\MercadoPagoCredentials;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mercado Pago entity lifecycle guards.
 */
final class EntitySaveHooks {

  public function __construct(
    #[Autowire('%app.root%')]
    private readonly string $appRoot,
  ) {}

  /**
   * Keeps the Mercado Pago gateway active without persisting credentials.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() !== 'commerce_payment_gateway'
      || $entity->id() !== MercadoPagoCredentials::GATEWAY_ID
      || !$entity instanceof PaymentGatewayInterface) {
      return;
    }

    $environment = MercadoPagoCredentials::currentEnvironment(dirname($this->appRoot));
    if (!MercadoPagoCredentials::hasRuntimeCredentials($environment)) {
      throw new \LogicException('O gateway Mercado Pago não pode ser salvo sem as credenciais do ambiente ativo.');
    }

    $entity->setStatus(TRUE);
    // Config overrides supply runtime keys in memory; never persist them.
    $entity->setPluginConfiguration(MercadoPagoCredentials::clearCredentialValues($entity->getPluginConfiguration()));
  }

}
