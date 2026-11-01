<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Commerce\MercadoPago;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;

/**
 * Supplies the Mercado Pago gateway fields for the active environment.
 *
 * Gateway config stores no credentials; this override selects active fields.
 * using the environment declared by the ACULTA Deployer.
 */
final class MercadoPagoEnvironmentOverride implements ConfigFactoryOverrideInterface {

  private const CONFIG_NAME = 'commerce_payment.commerce_payment_gateway.mercado_pago';

  public function __construct(
    private readonly string $appRoot,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names): array {
    if (!in_array(self::CONFIG_NAME, $names, TRUE)) {
      return [];
    }

    $environment = MercadoPagoCredentials::currentEnvironment(dirname($this->appRoot));
    return [
      self::CONFIG_NAME => [
        'configuration' => MercadoPagoCredentials::configuration($environment),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix(): string {
    return 'aculta_portal_mercado_pago_environment';
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = 'default') {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name): CacheableMetadata {
    return (new CacheableMetadata())->setCacheMaxAge(Cache::PERMANENT);
  }

}
