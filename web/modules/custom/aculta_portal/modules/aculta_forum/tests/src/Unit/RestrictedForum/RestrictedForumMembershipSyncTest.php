<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\RestrictedForum;

use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolverInterface;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumMembershipSync;
use Drupal\aculta_user\Associado\ActiveAssociateInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembershipInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests idempotent membership reconciliation for restricted forum groups.
 */
#[CoversClass(RestrictedForumMembershipSync::class)]
final class RestrictedForumMembershipSyncTest extends TestCase {

  /**
   * Active associate without membership is added.
   */
  public function testActiveAssociateWithoutMembershipIsAdded(): void {
    $group = $this->group(3, FALSE);
    $group->expects(self::once())->method('addMember');
    $group->expects(self::never())->method('removeMember');
    $sync = $this->sync([3 => $group], TRUE, $this->expectInvalidation());
    self::assertSame([3 => 'added'], $sync->syncAccount(5));
  }

  /**
   * Inactive associate with membership is removed.
   */
  public function testInactiveAssociateWithMembershipIsRemoved(): void {
    $group = $this->group(3, TRUE);
    $group->expects(self::never())->method('addMember');
    $group->expects(self::once())->method('removeMember');
    $sync = $this->sync([3 => $group], FALSE, $this->expectInvalidation());
    self::assertSame([3 => 'removed'], $sync->syncAccount(5));
  }

  /**
   * Active associate with membership is unchanged.
   */
  public function testActiveAssociateWithMembershipIsUnchanged(): void {
    $group = $this->group(3, TRUE);
    $group->expects(self::never())->method('addMember');
    $group->expects(self::never())->method('removeMember');
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects(self::never())->method('invalidateTags');
    $sync = $this->sync([3 => $group], TRUE, $invalidator);
    self::assertSame([3 => 'unchanged'], $sync->syncAccount(5));
  }

  /**
   * No configured group type does nothing.
   */
  public function testNoConfiguredGroupTypeDoesNothing(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::never())->method('acquire');
    $sync = new RestrictedForumMembershipSync(
      $this->entityTypeManager($this->user()),
      $this->resolver([]),
      $this->activeAssociate(TRUE),
      $lock,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    self::assertSame([], $sync->syncAccount(5));
  }

  /**
   * Busy lock skips without touching groups.
   */
  public function testBusyLockSkipsWithoutTouchingGroups(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(FALSE);
    $lock->expects(self::never())->method('release');
    $resolver = $this->resolver([3 => $this->group(3, FALSE)]);
    $sync = new RestrictedForumMembershipSync(
      $this->entityTypeManager($this->user()),
      $resolver,
      $this->activeAssociate(TRUE),
      $lock,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    self::assertSame([], $sync->syncAccount(5));
  }

  /**
   * Missing user releases lock and returns nothing.
   */
  public function testMissingUserReleasesLockAndReturnsNothing(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects(self::once())->method('release');
    $sync = new RestrictedForumMembershipSync(
      $this->entityTypeManager(NULL),
      $this->resolver([3 => $this->group(3, FALSE)]),
      $this->activeAssociate(TRUE),
      $lock,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    self::assertSame([], $sync->syncAccount(5));
  }

  /**
   * Zero uid is ignored.
   */
  public function testZeroUidIsIgnored(): void {
    $sync = new RestrictedForumMembershipSync(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(RestrictedForumGroupResolverInterface::class),
      $this->createMock(ActiveAssociateInterface::class),
      $this->createMock(LockBackendInterface::class),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    self::assertSame([], $sync->syncAccount(0));
  }

  /**
   * Group failure is reported without invalidating.
   */
  public function testGroupFailureIsReportedWithoutInvalidating(): void {
    $group = $this->group(3, FALSE);
    $group->method('addMember')->willThrowException(new \RuntimeException('boom'));
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects(self::never())->method('invalidateTags');
    $sync = $this->sync([3 => $group], TRUE, $invalidator);
    self::assertSame([3 => 'error'], $sync->syncAccount(5));
  }

  /**
   * Builds the synchronisation service with stubbed collaborators.
   */
  private function sync(array $groups, bool $active, CacheTagsInvalidatorInterface $invalidator): RestrictedForumMembershipSync {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects(self::once())->method('release');
    return new RestrictedForumMembershipSync(
      $this->entityTypeManager($this->user()),
      $this->resolver($groups),
      $this->activeAssociate($active),
      $lock,
      $invalidator,
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Returns an invalidator expected to run the given number of times.
   */
  private function expectInvalidation(int $times = 1): CacheTagsInvalidatorInterface {
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects(self::exactly($times))->method('invalidateTags');
    return $invalidator;
  }

  /**
   * Returns a resolver yielding the given groups or topic group ids.
   */
  private function resolver(array $groups): RestrictedForumGroupResolverInterface {
    $resolver = $this->createMock(RestrictedForumGroupResolverInterface::class);
    $resolver->method('groupsOfType')->willReturn($groups);
    return $resolver;
  }

  /**
   * Returns an associate marker reporting the given status.
   */
  private function activeAssociate(bool $active): ActiveAssociateInterface {
    $marker = $this->createMock(ActiveAssociateInterface::class);
    $marker->method('isActiveAssociate')->willReturn($active);
    return $marker;
  }

  /**
   * Returns an entity type manager whose user storage returns the given user.
   */
  private function entityTypeManager(?UserInterface $user): EntityTypeManagerInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($user);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('user')->willReturn($storage);
    return $manager;
  }

  /**
   * Returns a minimal user fixture.
   */
  private function user(): UserInterface {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn(5);
    return $user;
  }

  /**
   * Returns a group fixture whose getMember() reflects membership.
   */
  private function group(int $gid, bool $member): GroupInterface {
    $group = $this->createMock(GroupInterface::class);
    $group->method('id')->willReturn($gid);
    $group->method('getMember')->willReturn($member ? $this->createMock(GroupMembershipInterface::class) : FALSE);
    return $group;
  }

}
