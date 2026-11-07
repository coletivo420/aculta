<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Hook\EntityHooks;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Impede que membros comuns usem o formulário genérico de edição de usuário.
 *
 * Nem a própria conta pode, enquanto administradores mantêm a decisão do Core
 * baseada em permissão.
 */
#[Group('aculta_portal')]
final class EntityAccessCrossUserTest extends UnitTestCase {

  /**
   * Monta o hook de entidade com a rota informada.
   *
   * @param string $route
   *   Nome da rota simulada.
   *
   * @return \Drupal\aculta_portal\Hook\EntityHooks
   *   Hook de entidade pronto para o teste.
   */
  private function hooks(string $route): EntityHooks {
    $routeMatch = new class($route) extends CurrentRouteMatch {

      public function __construct(private readonly string $name) {}

      /**
       * Retorna o nome da rota atual.
       *
       * @return string
       *   Nome da rota armazenada.
       */
      public function getRouteName() {
        return $this->name;
      }

    };
    // DomainPurposeManager is final and is not reached for user entities; it is
    // built without its constructor so the Domain branch stays untouched.
    $domain = (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor();
    $emailConfirmation = new EmailConfirmationPolicy(
      $this->createMock(UserDataInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
    );
    return new EntityHooks($domain, $routeMatch, new RequestStack(), $emailConfirmation);
  }

  /**
   * Cria um mock de entidade de usuário com o identificador informado.
   *
   * @param int $uid
   *   Identificador do usuário.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Entidade de usuário simulada.
   */
  private function userEntity(int $uid): EntityInterface {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('user');
    $entity->method('id')->willReturn($uid);
    return $entity;
  }

  /**
   * Cria um mock de conta com o identificador e a permissão informados.
   *
   * @param int $uid
   *   Identificador do usuário.
   * @param bool $administers
   *   Se a conta tem a permissão de administrar usuários.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Conta simulada.
   */
  private function account(int $uid, bool $administers): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('hasPermission')->willReturnCallback(fn(string $p): bool => $administers && $p === 'administer users');
    return $account;
  }

  /**
   * Confirma que uma conta comum não pode editar outra conta.
   */
  public function testRegularAccountCannotEditAnotherAccount(): void {
    $result = $this->hooks('entity.user.edit_form')->entityAccess($this->userEntity(1), 'update', $this->account(999991, FALSE));
    $this->assertTrue($result->isForbidden());
  }

  /**
   * Confirma que uma conta comum não pode usar o formulário genérico na própria conta.
   */
  public function testRegularAccountCannotUseGenericFormOnItself(): void {
    $result = $this->hooks('entity.user.edit_form')->entityAccess($this->userEntity(999991), 'update', $this->account(999991, FALSE));
    $this->assertTrue($result->isForbidden());
  }

  /**
   * Confirma que a decisão para administradores fica com o Core.
   */
  public function testAdministratorIsDecidedByCore(): void {
    $result = $this->hooks('entity.user.edit_form')->entityAccess($this->userEntity(1), 'update', $this->account(1, TRUE));
    $this->assertTrue($result->isNeutral());
  }

  /**
   * Confirma que a restrição vale apenas para o formulário genérico de edição.
   */
  public function testRestrictionOnlyAppliesToTheGenericEditForm(): void {
    $result = $this->hooks('user.page')->entityAccess($this->userEntity(1), 'update', $this->account(999991, FALSE));
    $this->assertTrue($result->isNeutral());
  }

}
