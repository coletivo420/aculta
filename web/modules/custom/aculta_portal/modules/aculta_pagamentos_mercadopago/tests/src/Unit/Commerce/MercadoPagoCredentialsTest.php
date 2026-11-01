<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Commerce;

use Drupal\aculta_pagamentos_mercadopago\Commerce\MercadoPago\MercadoPagoCredentials;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Mercado Pago credential selection by environment.
 *
 * No real credential values are used.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoCredentialsTest extends UnitTestCase {

  private const VARIABLES = [
    'MERCADOPAGO_TEST_PUBLIC_KEY',
    'MERCADOPAGO_TEST_ACCESS_TOKEN',
    'MERCADOPAGO_PRODUCTION_PUBLIC_KEY',
    'MERCADOPAGO_PRODUCTION_ACCESS_TOKEN',
  ];

  /**
   * Helper for the test cases.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->clear();
  }

  /**
   * Helper for the test cases.
   */
  protected function tearDown(): void {
    $this->clear();
    parent::tearDown();
  }

  /**
   * Helper for the test cases.
   */
  public function testTestEnvironmentUsesTestPairInTestMode(): void {
    $this->set('MERCADOPAGO_TEST_PUBLIC_KEY', 'test-public');
    $this->set('MERCADOPAGO_TEST_ACCESS_TOKEN', 'test-access');
    $this->set('MERCADOPAGO_PRODUCTION_PUBLIC_KEY', 'prod-public');
    $this->set('MERCADOPAGO_PRODUCTION_ACCESS_TOKEN', 'prod-access');

    $configuration = MercadoPagoCredentials::configuration('test');

    $this->assertSame('test', $configuration['mode']);
    $this->assertSame('test-public', $configuration['public_key_test']);
    $this->assertSame('test-access', $configuration['access_token_test']);
    $this->assertArrayNotHasKey('public_key_prod', $configuration);
    $this->assertArrayNotHasKey('access_token_prod', $configuration);
    $this->assertTrue(MercadoPagoCredentials::hasRuntimeCredentials('test'));
  }

  /**
   * Helper for the test cases.
   */
  public function testProductionEnvironmentUsesProductionPairInLiveMode(): void {
    $this->set('MERCADOPAGO_TEST_PUBLIC_KEY', 'test-public');
    $this->set('MERCADOPAGO_TEST_ACCESS_TOKEN', 'test-access');
    $this->set('MERCADOPAGO_PRODUCTION_PUBLIC_KEY', 'prod-public');
    $this->set('MERCADOPAGO_PRODUCTION_ACCESS_TOKEN', 'prod-access');

    $configuration = MercadoPagoCredentials::configuration('production');

    $this->assertSame('live', $configuration['mode']);
    $this->assertSame('prod-public', $configuration['public_key_prod']);
    $this->assertSame('prod-access', $configuration['access_token_prod']);
    $this->assertArrayNotHasKey('public_key_test', $configuration);
    $this->assertArrayNotHasKey('access_token_test', $configuration);
  }

  /**
   * Helper for the test cases.
   */
  public function testApplicationCredentialsAreNotRead(): void {
    $this->set('MERCADOPAGO_TEST_PUBLIC_KEY', 'test-public');
    $configuration = MercadoPagoCredentials::configuration('test');
    $this->assertArrayNotHasKey('client_id', $configuration);
    $this->assertArrayNotHasKey('client_secret', $configuration);
  }

  /**
   * Helper for the test cases.
   */
  public function testMissingPairFailsClosed(): void {
    $this->set('MERCADOPAGO_TEST_PUBLIC_KEY', 'test-public');
    $this->assertFalse(MercadoPagoCredentials::hasRuntimeCredentials('test'));
    $this->assertFalse(MercadoPagoCredentials::hasRuntimeCredentials('production'));
    $this->assertArrayNotHasKey('access_token_test', MercadoPagoCredentials::configuration('test'));
  }

  /**
   * Helper for the test cases.
   */
  public function testUnknownEnvironmentFallsBackToProduction(): void {
    $this->set('MERCADOPAGO_PRODUCTION_ACCESS_TOKEN', 'prod-access');
    $this->assertSame('live', MercadoPagoCredentials::configuration('homelab')['mode']);
  }

  /**
   * Helper for the test cases.
   */
  private function set(string $name, string $value): void {
    putenv($name . '=' . $value);
  }

  /**
   * Helper for the test cases.
   */
  private function clear(): void {
    foreach (self::VARIABLES as $name) {
      putenv($name);
    }
  }

}
