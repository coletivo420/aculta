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

  public function testMapaDerivadoDaCadeiaEDosLaterais(): void {
    $mapa = RoleHierarchy::derivarMapa(
      ['gerente_loja', 'content_editor', 'lms_teacher', 'associado', 'estudante', 'email_confirmed', 'authenticated'],
      ['wiki_contributor' => 'email_confirmed', 'lms_admin' => 'lms_teacher'],
      ['anonymous', 'authenticated', 'email_confirmed', 'estudante', 'associado', 'lms_teacher', 'content_editor', 'gerente_loja', 'wiki_contributor', 'lms_admin', 'administrator'],
    );
    $this->assertSame(['content_editor'], $mapa['gerente_loja']);
    $this->assertSame(['lms_teacher'], $mapa['content_editor']);
    $this->assertSame(['email_confirmed'], $mapa['estudante']);
    $this->assertArrayNotHasKey('authenticated', $mapa);
    $this->assertSame(['email_confirmed'], $mapa['wiki_contributor']);
    $this->assertSame(['lms_teacher'], $mapa['lms_admin']);
    $this->assertCount(10, $mapa['administrator']);
    $this->assertFalse(RoleHierarchy::temCiclo($mapa));
  }

  public function testMoverNaCadeiaMudaOPai(): void {
    $mapa = RoleHierarchy::derivarMapa(['content_editor', 'gerente_loja', 'authenticated'], [], ['authenticated', 'content_editor', 'gerente_loja', 'administrator']);
    $this->assertSame(['gerente_loja'], $mapa['content_editor']);
    $this->assertSame(['authenticated'], $mapa['gerente_loja']);
  }

}
