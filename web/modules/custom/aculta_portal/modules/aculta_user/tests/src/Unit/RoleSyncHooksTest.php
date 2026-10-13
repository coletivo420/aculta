<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Hook\RoleSyncHooks;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regra email_confirmed -> wiki_contributor (decisão 28 do plano aculta_user).
 */
#[Group('aculta_user')]
class RoleSyncHooksTest extends UnitTestCase {

  /**
   * Conta com os papéis informados e contador de gravações.
   */
  private function account(array $roles, int $expectedSaves, int $expectedAdds): UserInterface {
    $account = $this->createMock(UserInterface::class);
    $account->method('hasRole')->willReturnCallback(static fn (string $role): bool => in_array($role, $roles, TRUE));
    $account->expects($this->exactly($expectedAdds))->method('addRole')->with('wiki_contributor');
    $account->expects($this->exactly($expectedSaves))->method('save');
    return $account;
  }

  /**
   * Conta com e-mail confirmado ganha wiki_contributor e é gravada.
   */
  public function testConfirmadoGanhaWikiContributor(): void {
    (new RoleSyncHooks())->userUpdate($this->account(['authenticated', 'email_confirmed'], 1, 1));
  }

  /**
   * Conta que já tem wiki_contributor não é regravada.
   */
  public function testJaTemPapelNaoGravaDeNovo(): void {
    (new RoleSyncHooks())->userUpdate($this->account(['email_confirmed', 'wiki_contributor'], 0, 0));
  }

  /**
   * Conta sem e-mail confirmado não ganha o papel.
   */
  public function testSemConfirmacaoNaoConcede(): void {
    (new RoleSyncHooks())->userInsert($this->account(['authenticated'], 0, 0));
  }

}
