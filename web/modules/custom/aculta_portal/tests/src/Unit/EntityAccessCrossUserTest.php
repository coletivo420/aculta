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
 * A regular account can never use the generic user edit form, not even on its
 * own record; administrators keep the Core permission-based decision.
 */
#[Group('aculta_portal')]
final class EntityAccessCrossUserTest extends UnitTestCase {

  private function hooks(string $route): EntityHooks {
    $routeMatch = new class($route) extends CurrentRouteMatch {
      public function __construct(private readonly string $name) {}
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

  private function userEntity(int $uid): EntityInterface {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('user');
    $entity->method('id')->willReturn($uid);
    return $entity;
  }

  private function account(int $uid, bool $administers): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('hasPermission')->willReturnCallback(fn(string $p): bool => $administers && $p === 'administer users');
    return $account;
  }

  public function testRegularAccountCannotEditAnotherAccount(): void {
    $result = $this->hooks('entity.user.edit_form')->entityAccess($this->userEntity(1), 'update', $this->account(999991, FALSE));
    $this->assertTrue($result->isForbidden());
  }

  public function testRegularAccountCannotUseGenericFormOnItself(): void {
    $result = $this->hooks('entity.user.edit_form')->entityAccess($this->userEntity(999991), 'update', $this->account(999991, FALSE));
    $this->assertTrue($result->isForbidden());
  }

  public function testAdministratorIsDecidedByCore(): void {
    $result = $this->hooks('entity.user.edit_form')->entityAccess($this->userEntity(1), 'update', $this->account(1, TRUE));
    $this->assertTrue($result->isNeutral());
  }

  public function testRestrictionOnlyAppliesToTheGenericEditForm(): void {
    $result = $this->hooks('user.page')->entityAccess($this->userEntity(1), 'update', $this->account(999991, FALSE));
    $this->assertTrue($result->isNeutral());
  }

}
