<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Account\EmailChangePolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * E-mail só se altera pelo formulário de Minha Conta; criação por administrador ainda oferece o campo.
 */
#[Group('aculta_portal')]
class EmailChangePolicyTest extends UnitTestCase {

  /**
   * Edição (própria ou de administrador) não oferece o campo.
   */
  public function testEdicaoNaoOferecePorEmail(): void {
    $this->assertFalse(EmailChangePolicy::mailFieldAllowed(FALSE, 'default'));
  }

  /**
   * Criação oferece o campo, mesmo com operação diferente de edição.
   */
  public function testCriacaoOferecePorEmail(): void {
    $this->assertTrue(EmailChangePolicy::mailFieldAllowed(TRUE, 'register'));
    $this->assertTrue(EmailChangePolicy::mailFieldAllowed(TRUE, 'default'));
  }

}
