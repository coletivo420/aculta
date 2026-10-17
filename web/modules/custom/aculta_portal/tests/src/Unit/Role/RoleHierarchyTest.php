<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Role;

use Drupal\aculta_portal\Role\RoleHierarchy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cadeia efetiva e detecção de ciclos da hierarquia de papéis.
 */
#[Group('aculta_portal')]
final class RoleHierarchyTest extends UnitTestCase {

  private const CADEIA = [
    'estudante' => ['email_confirmed'],
    'associado' => ['estudante'],
    'lms_teacher' => ['associado'],
    'content_editor' => ['lms_teacher'],
    'gerente_loja' => ['content_editor'],
  ];

  public function testEfetivosSaoTransitivos(): void {
    $this->assertSame(
      ['content_editor', 'lms_teacher', 'associado', 'estudante', 'email_confirmed'],
      RoleHierarchy::efetivos(self::CADEIA, 'gerente_loja'),
    );
  }

  public function testPapelSemPaiNaoTemEfetivos(): void {
    $this->assertSame([], RoleHierarchy::efetivos(self::CADEIA, 'email_confirmed'));
  }

  public function testCadeiaLinearNaoTemCiclo(): void {
    $this->assertFalse(RoleHierarchy::temCiclo(self::CADEIA));
  }

  public function testAutorreferenciaEhCiclo(): void {
    $this->assertTrue(RoleHierarchy::temCiclo(['gerente_loja' => ['gerente_loja']]));
  }

  public function testCicloIndiretoEhDetectado(): void {
    $this->assertTrue(RoleHierarchy::temCiclo(['a' => ['b'], 'b' => ['c'], 'c' => ['a']]));
  }

  public function testEfetivosNaoTravamEmCiclo(): void {
    $this->assertSame(['b'], RoleHierarchy::efetivos(['a' => ['b'], 'b' => ['a']], 'a'));
  }

}
