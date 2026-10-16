<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Associado\AssociadoMarker;
use Drupal\aculta_user\Forum\RestrictedForumGrants;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Realms de visibilidade dos fóruns: permissões por nível, regra composta do content_editor e marcador de associado.
 */
#[Group('aculta_user')]
class RestrictedForumGrantsTest extends UnitTestCase {

  /**
   * Conta com os papéis e permissões informados. $uid usado pelo marcador de associado.
   *
   * @param string[] $roles
   * @param string[] $permissions
   */
  private function account(int $uid, array $roles, array $permissions, bool $anonymous = FALSE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn($anonymous);
    $account->method('id')->willReturn($uid);
    $account->method('getRoles')->willReturn($roles);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, $permissions, TRUE),
    );
    return $account;
  }

  /**
   * Marcador de associado real (a classe é final e não pode ser duplicada), sobre um storage simulado.
   *
   * Só o uid informado tem marca: a consulta por uid devolve uma entidade apenas para ele.
   */
  private function marker(int $associadoUid = -1): AssociadoMarker {
    $uidConsultado = NULL;
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('condition')->willReturnCallback(
      static function (string $campo, int $valor) use (&$uidConsultado, $query): QueryInterface {
        $uidConsultado = $valor;
        return $query;
      },
    );
    $query->method('execute')->willReturnCallback(
      static function () use (&$uidConsultado, $associadoUid): array {
        return $uidConsultado === $associadoUid ? [1] : [];
      },
    );
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('load')->willReturn(new \stdClass());
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('aculta_associado')->willReturn($storage);
    return new AssociadoMarker($etm);
  }

  /**
   * Casos: [realms esperados, conta, marcador].
   */
  public static function casos(): array {
    $email = RestrictedForumGrants::PERM_EMAIL;
    $estudante = RestrictedForumGrants::PERM_ESTUDANTE;
    $marcador = RestrictedForumGrants::PERM_MARCADOR;
    $emailRealm = RestrictedForumGrants::REALM_EMAIL;
    $estudanteRealm = RestrictedForumGrants::REALM_ESTUDANTE;
    $marcadorRealm = RestrictedForumGrants::REALM_MARCADOR;

    return [
      'email_confirmed com permissão de e-mail' => [
        [$emailRealm], [1, ['authenticated', 'email_confirmed'], [$email]],
      ],
      'authenticated sem permissão não vê nada' => [
        [], [2, ['authenticated'], []],
      ],
      'estudante com permissão de estudante' => [
        [$estudanteRealm], [3, ['authenticated', 'email_confirmed', 'estudante'], [$estudante]],
      ],
      'associado marcado vê marcador' => [
        [$marcadorRealm], [4, ['authenticated', 'email_confirmed'], [], 4],
      ],
      'content_editor com e-mail e permissões vê todos os níveis' => [
        [$emailRealm, $estudanteRealm, $marcadorRealm],
        [5, ['authenticated', 'email_confirmed', 'content_editor'], [$email, $estudante, $marcador]],
      ],
      'content_editor sem e-mail não recebe e-mail nem estudante por permissão' => [
        [], [6, ['authenticated', 'content_editor'], [$email, $estudante, $marcador]],
      ],
      'papel associado vê marcador sem e-mail confirmado' => [
        [$marcadorRealm], [8, ['authenticated', 'associado'], []],
      ],
      'content_editor sem e-mail mantém o marcador de associado' => [
        [$marcadorRealm], [7, ['authenticated', 'content_editor'], [$email, $estudante], 7],
      ],
    ];
  }

  /**
   * Realms concedidos ao usuário, por caso.
   *
   * @param string[] $esperados
   * @param array{0: int, 1: string[], 2: string[], 3?: int} $caso
   */
  #[DataProvider('casos')]
  public function testRealmsConcedidos(array $esperados, array $caso): void {
    [$uid, $roles, $permissions] = $caso;
    $associadoUid = $caso[3] ?? -1;
    $grants = new RestrictedForumGrants($this->marker($associadoUid));

    $realms = array_keys($grants->grantsFor($this->account($uid, $roles, $permissions), 'view'));

    $this->assertEqualsCanonicalizing($esperados, $realms);
  }

  /**
   * Visitante e operação diferente de view não recebem grants.
   */
  public function testAnonimoEOperacaoNaoViewNaoRecebemGrants(): void {
    $grants = new RestrictedForumGrants($this->marker(1));
    $this->assertSame([], $grants->grantsFor($this->account(0, [], [], TRUE), 'view'));
    $this->assertSame([], $grants->grantsFor($this->account(1, ['authenticated'], [RestrictedForumGrants::PERM_EMAIL]), 'update'));
  }

}
