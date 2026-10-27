<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
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

}
