<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Hook\RoleSyncHooks;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regra email_confirmed -> wiki_contributor (decisão 28 do plano aculta_user).
 *
 * A concessão acontece em hook_user_presave, sem salvar a conta: salvar dentro do hook de insert/update quebra o
 * módulo group, que compara os papéis do original.
 */
#[Group('aculta_user')]
class RoleSyncHooksTest extends UnitTestCase {

  /**
   * Conta com os papéis informados. Espera-se addRole no máximo uma vez e nenhum save.
   */
  private function account(array $roles, int $expectedAdds): UserInterface {
    $account = $this->createMock(UserInterface::class);
    $account->method('hasRole')->willReturnCallback(static fn (string $role): bool => in_array($role, $roles, TRUE));
    $account->expects($this->exactly($expectedAdds))->method('addRole')->with('wiki_contributor');
    $account->expects($this->never())->method('save');
    return $account;
  }

  /**
   * Conta com e-mail confirmado ganha wiki_contributor antes do save.
   */
  public function testConfirmadoGanhaWikiContributor(): void {
    (new RoleSyncHooks())->userPresave($this->account(['authenticated', 'email_confirmed'], 1));
  }

  /**
   * Conta que já tem wiki_contributor não recebe o papel de novo.
   */
  public function testJaTemPapelNaoConcedeDeNovo(): void {
    (new RoleSyncHooks())->userPresave($this->account(['email_confirmed', 'wiki_contributor'], 0));
  }

  /**
   * Conta sem e-mail confirmado não ganha o papel.
   */
  public function testSemConfirmacaoNaoConcede(): void {
    (new RoleSyncHooks())->userPresave($this->account(['authenticated'], 0));
  }

}
