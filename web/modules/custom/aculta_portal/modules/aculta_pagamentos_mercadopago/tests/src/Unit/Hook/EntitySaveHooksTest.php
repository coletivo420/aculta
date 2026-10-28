<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Hook;

use Drupal\aculta_pagamentos_mercadopago\Hook\EntitySaveHooks;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Mercado Pago credentials stay outside stored configuration.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class EntitySaveHooksTest extends UnitTestCase {

  /**
   * Temporary project root used to select the sandbox environment.
   *
   * @var string
   */
  private string $projectRoot;

  /**
   * Drupal application root passed to the discovered hook.
   *
   * @var string
   */
  private string $appRoot;

  /**
   * Previous environment values restored after each test.
   *
   * @var array<string, string|false>
   */
  private array $previousEnvironment = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['MERCADOPAGO_TEST_PUBLIC_KEY', 'MERCADOPAGO_TEST_ACCESS_TOKEN'] as $name) {
      $this->previousEnvironment[$name] = getenv($name);
      putenv($name . '=unit-test-value');
    }
    $this->projectRoot = sys_get_temp_dir() . '/aculta-mp-presave-' . bin2hex(random_bytes(6));
    $this->appRoot = $this->projectRoot . '/web';
    mkdir($this->projectRoot . '/var/deployer', 0777, TRUE);
    file_put_contents($this->projectRoot . '/var/deployer/environment.json', json_encode([
      'schema' => 1,
      'environment' => 'test',
    ], JSON_THROW_ON_ERROR));
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->previousEnvironment as $name => $value) {
      if ($value === FALSE) {
        putenv($name);
      }
      else {
        putenv($name . '=' . $value);
      }
    }
    @unlink($this->projectRoot . '/var/deployer/environment.json');
    @rmdir($this->projectRoot . '/var/deployer');
    @rmdir($this->projectRoot . '/var');
    @rmdir($this->projectRoot);
    parent::tearDown();
  }

  /**
   * Ensures credential values are cleared before gateway configuration saves.
   */
  public function testGatewayCredentialsAreClearedBeforeSave(): void {
    $configuration = [
      'mode' => 'test',
      'country' => 'BR',
      'public_key_test' => 'test-public-key',
      'access_token_test' => 'test-access-token',
      'public_key_prod' => 'production-public-key',
      'access_token_prod' => 'production-access-token',
      'client_id' => 'client-id',
      'client_secret' => 'client-secret',
    ];
    $expected = [
      'mode' => 'test',
      'country' => 'BR',
      'public_key_test' => '',
      'access_token_test' => '',
      'public_key_prod' => '',
      'access_token_prod' => '',
      'client_id' => '',
      'client_secret' => '',
    ];
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('getEntityTypeId')->willReturn('commerce_payment_gateway');
    $gateway->method('id')->willReturn('mercado_pago');
    $gateway->method('status')->willReturn(FALSE);
    $gateway->method('getPluginConfiguration')->willReturn($configuration);
    $gateway->expects($this->once())->method('setStatus')->with(TRUE)->willReturnSelf();
    $gateway->expects($this->once())
      ->method('setPluginConfiguration')
      ->with($expected)
      ->willReturnSelf();

    (new EntitySaveHooks($this->appRoot))->entityPresave($gateway);
  }

  /**
   * Active configuration remains active while credentials are cleared.
   */
  public function testActiveGatewayCredentialsAreCleared(): void {
    $configuration = [
      'mode' => 'test',
      'public_key_test' => 'ephemeral-public-key',
      'access_token_test' => 'ephemeral-access-token',
    ];
    $expected = [
      'mode' => 'test',
      'public_key_test' => '',
      'access_token_test' => '',
    ];
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('getEntityTypeId')->willReturn('commerce_payment_gateway');
    $gateway->method('id')->willReturn('mercado_pago');
    $gateway->method('status')->willReturn(TRUE);
    $gateway->method('getPluginConfiguration')->willReturn($configuration);
    $gateway->expects($this->once())->method('setStatus')->with(TRUE)->willReturnSelf();
    $gateway->expects($this->once())
      ->method('setPluginConfiguration')
      ->with($expected)
      ->willReturnSelf();

    (new EntitySaveHooks($this->appRoot))->entityPresave($gateway);

    $this->assertTrue($gateway->status());
  }

  /**
   * Missing active-environment credentials fail closed before saving.
   */
  public function testGatewayCannotBeSavedWithoutRuntimeCredentials(): void {
    putenv('MERCADOPAGO_TEST_ACCESS_TOKEN');
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('getEntityTypeId')->willReturn('commerce_payment_gateway');
    $gateway->method('id')->willReturn('mercado_pago');
    $gateway->expects($this->never())->method('setStatus');
    $gateway->expects($this->never())->method('setPluginConfiguration');

    $this->expectException(\LogicException::class);
    (new EntitySaveHooks($this->appRoot))->entityPresave($gateway);
  }

}
