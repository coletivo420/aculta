<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway\CheckoutPro;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Inventário de clientes do SDK do Mercado Pago.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoClientFactoryTest extends UnitTestCase {

  /**
   * Tests the todos os clientes concretos do sdk estao mapeados behavior.
   */
  public function testTodosOsClientesConcretosDoSdkEstaoMapeados(): void {
    // dx-php 3.16.0 tem 21 arquivos *Client.php: 20 clientes concretos e a
    // classe base MercadoPagoClient.
    $this->assertCount(20, MercadoPagoClientFactory::CLIENTES);
  }

  /**
   * Tests the todas as classes existem no sdk instalado behavior.
   */
  public function testTodasAsClassesExistemNoSdkInstalado(): void {
    foreach ((new MercadoPagoClientFactory())->inventario() as $alias => $existe) {
      $this->assertTrue($existe, "Classe do cliente '$alias' ausente no SDK instalado.");
    }
  }

  /**
   * Financial operations remain fail-closed when the active token is absent.
   */
  public function testMissingActiveTokenRejectsClientConfiguration(): void {
    $plugin = $this->createMock(CheckoutPro::class);
    $plugin->method('getMode')->willReturn('test');
    $plugin->method('getConfiguration')->willReturn(['access_token_test' => '']);
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('getPlugin')->willReturn($plugin);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('O gateway do Mercado Pago não tem token para o modo ativo.');
    (new MercadoPagoClientFactory())->tokenAtivo($gateway);
  }

}
