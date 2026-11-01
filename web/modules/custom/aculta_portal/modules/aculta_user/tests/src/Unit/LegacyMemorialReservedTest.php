<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Account\AccountNames;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * O apelido do memorial do Fórum não pode ser escolhido por conta comum (ACULTA Fórum, F-D07).
 */
#[Group('aculta_user')]
class LegacyMemorialReservedTest extends UnitTestCase {

  /**
   * O literal reservado não é um apelido válido.
   */
  public function testLendasHistoricasEhReservado(): void {
    $this->assertFalse(AccountNames::isValid('lendashistoricas'));
    $this->assertNull(AccountNames::publicRouteSlug('lendashistoricas'));
  }

  /**
   * Apelidos comuns continuam válidos.
   */
  public function testApelidoComumContinuaValido(): void {
    $this->assertTrue(AccountNames::isValid('maria-souza'));
  }

}
