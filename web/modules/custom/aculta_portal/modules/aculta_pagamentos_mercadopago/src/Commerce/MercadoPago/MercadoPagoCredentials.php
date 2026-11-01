<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Commerce\MercadoPago;

use Drupal\aculta_portal\Environment\DeployerEnvironment;

/**
 * Credenciais do Mercado Pago por ambiente, lidas do ambiente do processo.
 *
 * O ACULTA Deployer declara o ambiente em var/deployer/environment.json;
 * esta classe não o altera.
 * - Ambiente de testes: modo `test` do gateway, com as credenciais de teste.
 * - Ambiente de produção: modo `live` do gateway e credenciais de produção.
 *
 * Os valores ficam fora da configuração exportada. Campo ausente deixa o
 * checkout indisponível e fail-closed antes de chamar o provedor. Client IDs
 * só existem no formulário de configuração; Checkout Pro não os usa.
 */
final class MercadoPagoCredentials {

  public const GATEWAY_ID = 'mercado_pago';

  /**
   * Credential fields that must remain outside stored gateway configuration.
   *
   * @var string[]
   */
  private const CREDENTIAL_FIELDS = [
    'public_key_test',
    'access_token_test',
    'public_key_stage',
    'access_token_stage',
    'public_key_prod',
    'access_token_prod',
    'client_id',
    'client_secret',
  ];

  private const ENVIRONMENTS = [
    'test' => [
      'mode' => 'test',
      'public_key_variable' => 'MERCADOPAGO_TEST_PUBLIC_KEY',
      'access_token_variable' => 'MERCADOPAGO_TEST_ACCESS_TOKEN',
      'public_key_field' => 'public_key_test',
      'access_token_field' => 'access_token_test',
    ],
    'production' => [
      'mode' => 'live',
      'public_key_variable' => 'MERCADOPAGO_PRODUCTION_PUBLIC_KEY',
      'access_token_variable' => 'MERCADOPAGO_PRODUCTION_ACCESS_TOKEN',
      'public_key_field' => 'public_key_prod',
      'access_token_field' => 'access_token_prod',
    ],
  ];

  /**
   * Ambiente que o ACULTA Deployer declarou (produção quando não há declaração). */
  public static function currentEnvironment(string $projectRoot): string {
    return DeployerEnvironment::current($projectRoot, 'production');
  }

  /**
   * Campos do gateway para o ambiente, sem valores vazios.
   *
   * @return array<string, string>
   *   Gateway fields containing only available credential values.
   */
  public static function configuration(string $environment): array {
    $spec = self::spec($environment);
    $fields = ['mode' => $spec['mode']];
    $sources = [
      $spec['public_key_field'] => $spec['public_key_variable'],
      $spec['access_token_field'] => $spec['access_token_variable'],
    ];
    foreach ($sources as $field => $variable) {
      $value = self::read($variable);
      if ($value !== NULL) {
        $fields[$field] = $value;
      }
    }
    return $fields;
  }

  /**
   * Verifies the runtime credential pair for the selected environment.
   *
   * @return bool
   *   TRUE when both credentials are available.
   */
  public static function hasRuntimeCredentials(string $environment): bool {
    $spec = self::spec($environment);
    return self::read($spec['public_key_variable']) !== NULL
      && self::read($spec['access_token_variable']) !== NULL;
  }

  /**
   * Clears credential values before a gateway entity is saved.
   *
   * @param array<string, mixed> $configuration
   *   Payment gateway plugin configuration.
   *
   * @return array<string, mixed>
   *   Configuration with credential fields empty.
   */
  public static function clearCredentialValues(array $configuration): array {
    foreach (self::CREDENTIAL_FIELDS as $field) {
      if (array_key_exists($field, $configuration)) {
        $configuration[$field] = '';
      }
    }

    return $configuration;
  }

  /**
   * Returns credential configuration for an environment.
   *
   * @return array{mode: string, public_key_variable: string, access_token_variable: string, public_key_field: string, access_token_field: string}
   *   Configuration keys for the selected environment.
   */
  private static function spec(string $environment): array {
    return self::ENVIRONMENTS[$environment] ?? self::ENVIRONMENTS['production'];
  }

  /**
   * Reads an optional environment variable without returning empty values.
   */
  private static function read(string $name): ?string {
    $value = getenv($name);
    return $value === FALSE || $value === '' ? NULL : $value;
  }

}
