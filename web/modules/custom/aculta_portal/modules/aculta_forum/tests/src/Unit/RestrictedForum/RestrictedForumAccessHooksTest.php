<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\RestrictedForum;

use Drupal\aculta_forum\Hook\RestrictedForumAccessHooks;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumAccessPolicy;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolverInterface;
use Drupal\aculta_user\Associado\ActiveAssociateInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembershipInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests entity access and node grants for restricted forum topics.
 */
#[CoversClass(RestrictedForumAccessHooks::class)]
final class RestrictedForumAccessHooksTest extends TestCase {

  /**
   * Entity access ignores other bundles and operations.
   */
  public function testEntityAccessIgnoresOtherBundlesAndOperations(): void {
    $policy = $this->createMock(RestrictedForumAccessPolicy::class);
    $policy->expects(self::never())->method('access');
    $hooks = $this->hooks(policy: $policy);

    $other = $this->createMock(NodeInterface::class);
    $other->method('bundle')->willReturn('aculta_forum_post');
    self::assertNull($hooks->entityAccess($other, 'view', $this->account(5)));
    self::assertNull($hooks->entityAccess($this->topic(), 'update', $this->account(5)));
    self::assertNull($hooks->entityAccess($this->createMock(EntityInterface::class), 'view', $this->account(5)));
  }

  /**
   * Entity access delegates topic view to policy.
   */
  public function testEntityAccessDelegatesTopicViewToPolicy(): void {
    $expected = AccessResult::forbidden('restrito');
    $policy = $this->createMock(RestrictedForumAccessPolicy::class);
    $policy->expects(self::once())->method('access')->willReturn($expected);
    $hooks = $this->hooks(policy: $policy);
    self::assertSame($expected, $hooks->entityAccess($this->topic(), 'view', $this->account(5)));
  }

  /**
   * Anonymous gets no grants.
   */
  public function testAnonymousGetsNoGrants(): void {
    $hooks = $this->hooks();
    $anonymous = $this->createMock(AccountInterface::class);
    $anonymous->method('isAnonymous')->willReturn(TRUE);
    self::assertSame([], $hooks->nodeGrants($anonymous, 'view'));
  }

  /**
   * Non view operations get no grants.
   */
  public function testNonViewOperationsGetNoGrants(): void {
    self::assertSame([], $this->hooks()->nodeGrants($this->account(5), 'update'));
  }

  /**
   * Inactive associate gets no grants, even with remaining membership.
   */
  public function testInactiveAssociateGetsNoGrants(): void {
    $hooks = $this->hooks(active: FALSE, groups: [3 => $this->group(3, TRUE)]);
    self::assertSame([], $hooks->nodeGrants($this->account(5), 'view'));
  }

  /**
   * No configured groups means no grants.
   */
  public function testNoConfiguredGroupsMeansNoGrants(): void {
    self::assertSame([], $this->hooks(groups: [])->nodeGrants($this->account(5), 'view'));
  }

  /**
   * Only groups where the account is a member become grants.
   */
  public function testOnlyMemberGroupsBecomeGrants(): void {
    $hooks = $this->hooks(groups: [3 => $this->group(3, TRUE), 8 => $this->group(8, FALSE)]);
    self::assertSame(['aculta_forum_restricted' => [3]], $hooks->nodeGrants($this->account(5), 'view'));
  }

  /**
   * Membership in a non-configured group never becomes a grant.
   */
  public function testMembershipOutsideConfiguredGroupsIsIgnored(): void {
    $hooks = $this->hooks(groups: [3 => $this->group(3, FALSE)]);
    self::assertSame([], $hooks->nodeGrants($this->account(5), 'view'));
  }

  /**
   * Resolver failure during grants does not grant.
   */
  public function testResolverFailureDuringGrantsDoesNotGrant(): void {
    $resolver = $this->createMock(RestrictedForumGroupResolverInterface::class);
    $resolver->method('groupsOfType')->willThrowException(new \RuntimeException('boom'));
    $hooks = $this->hooks(resolver: $resolver);
    self::assertSame([], $hooks->nodeGrants($this->account(5), 'view'));
  }

  /**
   * Public topic gets no restricted grants.
   */
  public function testPublicTopicGetsNoRestrictedGrants(): void {
    self::assertSame([], $this->hooks(groupIds: [])->nodeAccessRecords($this->topic()));
  }

  /**
   * Restricted topic gets view only grant per group.
   */
  public function testRestrictedTopicGetsViewOnlyGrantPerGroup(): void {
    $grants = $this->hooks(groupIds: [3, 8])->nodeAccessRecords($this->topic());
    self::assertSame([
      ['realm' => 'aculta_forum_restricted', 'gid' => 3, 'grant_view' => 1, 'grant_update' => 0, 'grant_delete' => 0],
      ['realm' => 'aculta_forum_restricted', 'gid' => 8, 'grant_view' => 1, 'grant_update' => 0, 'grant_delete' => 0],
    ], $grants);
  }

  /**
   * Non topic node gets no grants.
   */
  public function testNonTopicNodeGetsNoGrants(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('page');
    self::assertSame([], $this->hooks(groupIds: [3])->nodeAccessRecords($node));
  }

  /**
   * Resolver failure while writing grants fails closed.
   */
  public function testResolverFailureWhileWritingGrantsFailsClosed(): void {
    $resolver = $this->createMock(RestrictedForumGroupResolverInterface::class);
    $resolver->method('groupIdsForTopic')->willThrowException(new \RuntimeException('boom'));
    $grants = $this->hooks(resolver: $resolver)->nodeAccessRecords($this->topic());
    self::assertSame([[
      'realm' => 'aculta_forum_restricted',
      'gid' => 0,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ],
    ], $grants);
  }

  /**
   * Builds the hooks object with stubbed collaborators.
   */
  private function hooks(
    ?RestrictedForumGroupResolverInterface $resolver = NULL,
    ?RestrictedForumAccessPolicy $policy = NULL,
    bool $active = TRUE,
    array $groups = [],
    ?array $groupIds = NULL,
  ): RestrictedForumAccessHooks {
    $resolver ??= $this->resolver($groupIds ?? [], $groups);
    $marker = $this->createMock(ActiveAssociateInterface::class);
    $marker->method('isActiveAssociate')->willReturn($active);
    return new RestrictedForumAccessHooks(
      $resolver,
      $policy ?? $this->createMock(RestrictedForumAccessPolicy::class),
      $marker,
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Returns a resolver yielding the given groups or topic group ids.
   */
  private function resolver(array $groupIds = [], array $groups = []): RestrictedForumGroupResolverInterface {
    $resolver = $this->createMock(RestrictedForumGroupResolverInterface::class);
    $resolver->method('groupIdsForTopic')->willReturn($groupIds);
    $resolver->method('groupsOfType')->willReturn($groups);
    return $resolver;
  }

  /**
   * Returns a group fixture whose getMember() reflects membership.
   */
  private function group(int $gid, bool $member): GroupInterface {
    $group = $this->createMock(GroupInterface::class);
    $group->method('id')->willReturn($gid);
    $group->method('bundle')->willReturn('forum_associados');
    $group->method('getMember')->willReturn($member ? $this->createMock(GroupMembershipInterface::class) : FALSE);
    return $group;
  }

  /**
   * Returns a forum topic fixture.
   */
  private function topic(int $nid = 11): NodeInterface {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('id')->willReturn($nid);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheTags')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $topic;
  }

  /**
   * Returns an authenticated account fixture.
   */
  private function account(int $uid): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('isAnonymous')->willReturn(FALSE);
    return $account;
  }

}
