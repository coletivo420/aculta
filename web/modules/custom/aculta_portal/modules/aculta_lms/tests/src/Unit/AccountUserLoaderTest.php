<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Account\AccountUserLoader;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests resolving session accounts to their cacheable user entity.
 */
#[Group('aculta_lms')]
final class AccountUserLoaderTest extends UnitTestCase {

  /**
   * Resolves a session object through User storage.
   */
  public function testSessionAccountLoadsUserEntity(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('27');
    $user = $this->createMock(UserInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with('27')->willReturn($user);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::once())->method('getStorage')->with('user')->willReturn($storage);

    self::assertSame($user, (new AccountUserLoader($entity_type_manager))->load($account));
  }

  /**
   * Reuses an entity already passed as the session account.
   */
  public function testUserEntityDoesNotReloadItself(): void {
    $user = $this->createMock(UserInterface::class);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::never())->method('getStorage');

    self::assertSame($user, (new AccountUserLoader($entity_type_manager))->load($user));
  }

  /**
   * Returns no entity for the anonymous account.
   */
  public function testAnonymousSessionHasNoUserEntity(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(NULL);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::never())->method('getStorage');

    self::assertNull((new AccountUserLoader($entity_type_manager))->load($account));
  }

}
