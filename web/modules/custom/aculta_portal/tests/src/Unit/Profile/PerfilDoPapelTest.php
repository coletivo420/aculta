<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Profile;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tipo de perfil pelo papel mais alto da conta.
 */
#[Group('aculta_portal')]
final class PerfilDoPapelTest extends UnitTestCase {

  /**
   * Cria um mock de conta com os papéis informados.
   *
   * @param array $papeis
   *   Lista de papéis atribuídos à conta.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Conta simulada.
   */
  private function conta(array $papeis): AccountInterface {
    $conta = $this->createMock(AccountInterface::class);
    $conta->method('getRoles')->willReturn($papeis);
    return $conta;
  }

  /**
   * Confirma que o associado tem o tipo associado.
   */
  public function testAssociadoTemTipoAssociado(): void {
    $this->assertSame('associado', PerfilDoPapel::tipoDaConta($this->conta([
      'authenticated',
      'email_confirmed',
      'associado',
    ])));
  }

  /**
   * Confirma que quem confirmou o e-mail sem associação tem o tipo de e-mail confirmado.
   */
  public function testEmailConfirmadoSemAssociadoTemTipoEmailConfirmado(): void {
    $this->assertSame('email_confirmed', PerfilDoPapel::tipoDaConta($this->conta([
      'authenticated',
      'email_confirmed',
      'wiki_contributor',
    ])));
  }

  /**
   * Confirma que o professor tem o tipo professor.
   */
  public function testProfessorTemTipoProfessor(): void {
    $this->assertSame('lms_teacher', PerfilDoPapel::tipoDaConta($this->conta([
      'authenticated',
      'associado',
      'lms_teacher',
    ])));
  }

  /**
   * Confirma que o gerente de loja tem o tipo gerente.
   */
  public function testGerenteDeLojaTemTipoGerente(): void {
    $this->assertSame('gerente_loja', PerfilDoPapel::tipoDaConta($this->conta([
      'authenticated',
      'associado',
      'gerente_loja',
    ])));
  }

  /**
   * Confirma que o administrador tem o tipo administrador.
   */
  public function testAdministradorTemTipoAdministrador(): void {
    $this->assertSame('administrator', PerfilDoPapel::tipoDaConta($this->conta([
      'authenticated',
      'administrator',
      'gerente_loja',
    ])));
  }

  /**
   * Confirma que só o papel autenticado resulta no tipo autenticado.
   */
  public function testSoAutenticadoTemTipoAutenticado(): void {
    $this->assertSame('authenticated', PerfilDoPapel::tipoDaConta($this->conta(['authenticated'])));
  }

  /**
   * Confirma que um papel sem tipo corresponde cai no autenticado.
   */
  public function testPapelSemTipoCaiNoAutenticado(): void {
    $this->assertSame('authenticated', PerfilDoPapel::tipoDaConta($this->conta(['wiki_contributor'])));
  }

}
