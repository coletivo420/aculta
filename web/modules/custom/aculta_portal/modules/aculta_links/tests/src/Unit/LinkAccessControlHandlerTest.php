<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_links\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\aculta_links\Access\LinkAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\EntityOwnerInterface;

/**
 * @coversDefaultClass \Drupal\aculta_links\Access\LinkAccessControlHandler
 * @group aculta_links
 */
final class LinkAccessControlHandlerTest extends UnitTestCase {

  /**
   * Tests owner, other-user, administrator, and create access decisions.
   *
   * @covers ::checkAccess
   * @covers ::checkCreateAccess
   */
  public function testAccessIsPrivateAndPermissionGated(): void {
    $handler = new TestableLinkAccessControlHandler($this->createMock(EntityTypeInterface::class));
    $entity = $this->createMockForIntersectionOfInterfaces([
      EntityInterface::class,
      EntityOwnerInterface::class,
    ]);
    $entity->method('getOwnerId')->willReturn(17);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(3600);

    $owner = $this->account(17, ['manage own aculta links']);
    self::assertTrue($handler->checkEntityAccess($entity, 'update', $owner)->isAllowed());
    self::assertTrue($handler->checkEntityAccess($entity, 'view', $owner)->isAllowed());
    self::assertFalse($handler->checkEntityAccess($entity, 'delete', $this->account(18, ['manage own aculta links']))->isAllowed());
    self::assertFalse($handler->checkEntityAccess($entity, 'view', $this->account(17, []))->isAllowed());
    self::assertTrue($handler->checkEntityAccess($entity, 'delete', $this->account(18, ['administer aculta links']))->isAllowed());
    self::assertTrue($handler->checkEntityCreateAccess($this->account(18, ['create aculta links']))->isAllowed());
    self::assertFalse($handler->checkEntityCreateAccess($this->account(18, []))->isAllowed());
  }

  /**
   * Creates a user account with a fixed permission set.
   *
   * @param int $uid
   *   User ID.
   * @param string[] $permissions
   *   Permissions granted to the test account.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Mock account.
   */
  private function account(int $uid, array $permissions): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, $permissions, TRUE),
    );
    return $account;
  }

}

/**
 * Exposes entity access methods for isolated unit tests.
 */
final class TestableLinkAccessControlHandler extends LinkAccessControlHandler {

  /**
   * Exposes entity access for the test.
   */
  public function checkEntityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult {
    return $this->checkAccess($entity, $operation, $account);
  }

  /**
   * Exposes create access for the test.
   */
  public function checkEntityCreateAccess(AccountInterface $account): AccessResult {
    return $this->checkCreateAccess($account, []);
  }

}
