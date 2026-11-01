<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Hook;

use Drupal\aculta_user\Hook\RoleInheritanceHooks;
use Drupal\aculta_user\Role\HerancaDePapeis;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Hooks de herança: carregar (estende), salvar (retira o não gravado) e formulário (não marca o herdado).
 */
#[Group('aculta_user')]
final class RoleInheritanceHooksTest extends UnitTestCase {

  public function testCarregarAdicionaOsHerdados(): void {
    $heranca = $this->createMock(HerancaDePapeis::class);
    $heranca->method('herdados')->willReturn(['associado', 'estudante']);
    $conta = $this->createMock(UserInterface::class);
    $conta->method('getRoles')->willReturn(['authenticated', 'lms_teacher']);
    $conta->method('hasRole')->willReturn(FALSE);
    $conta->expects($this->exactly(2))->method('addRole')->willReturnSelf();

    (new RoleInheritanceHooks($heranca))->userLoad([$conta]);
  }

  public function testSalvarRetiraSoOHerdadoNaoGravado(): void {
    $heranca = $this->createMock(HerancaDePapeis::class);
    $heranca->method('herdadosNaoGravados')->willReturn(['associado']);
    $conta = $this->createMock(UserInterface::class);
    $conta->method('id')->willReturn(7);
    $conta->method('hasRole')->willReturnCallback(fn (string $papel) => in_array($papel, ['associado', 'gerente_loja'], TRUE));
    $conta->expects($this->once())->method('removeRole')->with('associado')->willReturnSelf();

    (new RoleInheritanceHooks($heranca))->userPresave($conta);
  }

}
