<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Role;

use Drupal\aculta_portal\Role\RoleHierarchy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cadeia de papéis: herança transitiva, ciclos, administrador, papel novo, exclusão e herdados.
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

  private const CADEIA_ATUAL = ['gerente_loja', 'content_editor', 'lms_teacher', 'associado', 'estudante', 'email_confirmed', 'authenticated'];

  private const TODOS = ['anonymous', 'authenticated', 'email_confirmed', 'estudante', 'associado', 'lms_teacher', 'content_editor', 'gerente_loja', 'administrator'];

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

  public function testMapaDerivadoDaCadeia(): void {
    $mapa = RoleHierarchy::derivarMapa(self::CADEIA_ATUAL, self::TODOS);
    $this->assertSame(['content_editor'], $mapa['gerente_loja']);
    $this->assertSame(['lms_teacher'], $mapa['content_editor']);
    $this->assertSame(['email_confirmed'], $mapa['estudante']);
    $this->assertSame(['authenticated'], $mapa['email_confirmed']);
    $this->assertArrayNotHasKey('authenticated', $mapa);
    $this->assertFalse(RoleHierarchy::temCiclo($mapa));
  }

  public function testAdministradorHerdaDeTodosOsOutrosPapeis(): void {
    $mapa = RoleHierarchy::derivarMapa(self::CADEIA_ATUAL, self::TODOS);
    $this->assertContains('anonymous', $mapa['administrator']);
    $this->assertNotContains('administrator', $mapa['administrator']);
    $this->assertCount(count(self::TODOS) - 1, $mapa['administrator']);
  }

  public function testReordenarMudaOPai(): void {
    $mapa = RoleHierarchy::derivarMapa(['content_editor', 'gerente_loja', 'authenticated'], ['authenticated', 'content_editor', 'gerente_loja', 'administrator']);
    $this->assertSame(['gerente_loja'], $mapa['content_editor']);
    $this->assertSame(['authenticated'], $mapa['gerente_loja']);
  }

  public function testPapelForaDaCadeiaHerdaDoAutenticado(): void {
    $mapa = RoleHierarchy::derivarMapa(self::CADEIA_ATUAL, array_merge(self::TODOS, ['papel_novo']));
    $this->assertSame(['authenticated'], $mapa['papel_novo']);
  }

  public function testHerdadosNaoIncluemOsPapeisDados(): void {
    $this->assertSame(['associado', 'estudante', 'email_confirmed'], RoleHierarchy::herdados(['lms_teacher'], self::CADEIA));
    $this->assertSame([], RoleHierarchy::herdados(['authenticated'], self::CADEIA));
  }

  public function testCompletarRemoveOPapelExcluido(): void {
    $existentes = ['gerente_loja', 'content_editor', 'lms_teacher', 'authenticated'];
    $this->assertSame(['gerente_loja', 'lms_teacher', 'authenticated'], RoleHierarchy::completar(['gerente_loja', 'content_editor', 'lms_teacher', 'authenticated'], array_diff($existentes, ['content_editor'])));
  }

  public function testCompletarNaoRepeteNemInventaPapel(): void {
    $this->assertSame(['authenticated'], RoleHierarchy::completar(['authenticated', 'authenticated', 'fantasma'], ['authenticated']));
  }

  public function testPapelExcluidoNaoAparecePeloMapa(): void {
    // Papel excluído sai da cadeia e não tem entrada no mapa; o mapa não fica com referências órfãs.
    $cadeia = RoleHierarchy::completar(self::CADEIA_ATUAL, array_diff(self::TODOS, ['content_editor']));
    $mapa = RoleHierarchy::derivarMapa($cadeia, array_diff(self::TODOS, ['content_editor']));
    $this->assertNotContains('content_editor', $cadeia);
    $this->assertSame(['lms_teacher'], $mapa['gerente_loja']);
  }

}
