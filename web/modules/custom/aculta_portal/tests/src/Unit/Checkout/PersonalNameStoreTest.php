<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Checkout;

use Drupal\aculta_portal\Checkout\PersonalNameStore;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Nome do checkout: visitante não tem perfil, então nada é lido nem gravado.
 */
#[CoversClass(PersonalNameStore::class)]
#[Group('aculta_portal')]
final class PersonalNameStoreTest extends UnitTestCase {

  /**
   * Visitante devolve nome vazio sem consultar o armazenamento de perfis.
   */
  public function testAnonymousHasNoName(): void {
    $storage = $this->createMock(EntityTypeManagerInterface::class);
    $storage->expects($this->never())->method('getStorage');
    $conta = $this->createMock(AccountInterface::class);
    $conta->method('isAuthenticated')->willReturn(FALSE);

    $store = new PersonalNameStore($storage);

    $this->assertSame(['first' => '', 'last' => ''], $store->forAccount($conta));
  }

  /**
   * Conta sem perfil principal devolve nome vazio.
   */
  public function testAccountWithoutProfileHasNoName(): void {
    $profiles = $this->createMock(\Drupal\profile\ProfileStorageInterface::class);
    $profiles->method('loadByUser')->willReturn(NULL);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('profile')->willReturn($profiles);
    $conta = $this->createMock(AccountInterface::class);
    $conta->method('isAuthenticated')->willReturn(TRUE);
    $conta->method('getRoles')->willReturn(['authenticated']);

    $store = new PersonalNameStore($entities);

    $this->assertSame(['first' => '', 'last' => ''], $store->forAccount($conta));
  }

}
