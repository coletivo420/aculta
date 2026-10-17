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

  private function conta(array $papeis): AccountInterface {
    $conta = $this->createMock(AccountInterface::class);
    $conta->method('getRoles')->willReturn($papeis);
    return $conta;
  }

  public function testAssociadoTemTipoAssociado(): void {
    $this->assertSame('associado', PerfilDoPapel::tipoDaConta($this->conta(['authenticated', 'email_confirmed', 'associado'])));
  }

  public function testEmailConfirmadoSemAssociadoTemTipoEmailConfirmado(): void {
    $this->assertSame('email_confirmed', PerfilDoPapel::tipoDaConta($this->conta(['authenticated', 'email_confirmed', 'wiki_contributor'])));
  }

  public function testSoAutenticadoTemTipoAutenticado(): void {
    $this->assertSame('authenticated', PerfilDoPapel::tipoDaConta($this->conta(['authenticated'])));
  }

  public function testPapelSemTipoCaiNoAutenticado(): void {
    $this->assertSame('authenticated', PerfilDoPapel::tipoDaConta($this->conta(['wiki_contributor'])));
  }

}
