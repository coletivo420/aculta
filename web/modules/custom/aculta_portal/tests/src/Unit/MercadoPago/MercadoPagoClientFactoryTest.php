<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\MercadoPago;

use Drupal\aculta_portal\MercadoPago\MercadoPagoClientFactory;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Inventário de clientes do SDK do Mercado Pago.
 */
#[Group('aculta_portal')]
final class MercadoPagoClientFactoryTest extends UnitTestCase {

  public function testTodosOsClientesConcretosDoSdkEstaoMapeados(): void {
    // dx-php 3.16.0 tem 21 arquivos *Client.php: 20 clientes concretos e a classe base MercadoPagoClient.
    $this->assertCount(20, MercadoPagoClientFactory::CLIENTES);
  }

  public function testTodasAsClassesExistemNoSdkInstalado(): void {
    foreach ((new MercadoPagoClientFactory())->inventario() as $alias => $existe) {
      $this->assertTrue($existe, "Classe do cliente '$alias' ausente no SDK instalado.");
    }
  }

}
