<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\RestrictedForum;

use Drupal\aculta_forum\RestrictedForum\RestrictedForumAccessPolicy;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolverInterface;
use Drupal\aculta_user\Associado\ActiveAssociateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembershipInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the restricted forum access policy.
 */
#[CoversClass(RestrictedForumAccessPolicy::class)]
final class RestrictedForumAccessPolicyTest extends TestCase {

  /**
   * Topic without restricted group is neutral.
   */
  public function testTopicWithoutRestrictedGroupIsNeutral(): void {
    $policy = $this->policy([], FALSE, []);
    $result = $policy->access($this->topic(), $this->account(5), 'view');
    self::assertTrue($result->isNeutral());
  }

  /**
   * Active associate with membership is neutral.
   */
  public function testActiveAssociateWithMembershipIsNeutral(): void {
    $policy = $this->policy([7], TRUE, [7]);
    $result = $policy->access($this->topic(), $this->account(5), 'view');
    self::assertTrue($result->isNeutral());
    self::assertFalse($result->isForbidden());
  }

  /**
   * Active associate without membership is forbidden.
   */
  public function testActiveAssociateWithoutMembershipIsForbidden(): void {
    $policy = $this->policy([7], TRUE, []);
    $result = $policy->access($this->topic(), $this->account(5), 'view');
    self::assertTrue($result->isForbidden());
  }

  /**
   * Membership in another restricted group does not grant this topic.
   */
  public function testMembershipInAnotherGroupIsForbidden(): void {
    $policy = $this->policy([7], TRUE, [8], [7, 8]);
    $result = $policy->access($this->topic(), $this->account(5), 'view');
    self::assertTrue($result->isForbidden());
  }

  /**
   * Inactive associate with remaining membership is forbidden.
   */
  public function testInactiveAssociateWithRemainingMembershipIsForbidden(): void {
    $policy = $this->policy([7], FALSE, [7]);
    $result = $policy->access($this->topic(), $this->account(5), 'view');
    self::assertTrue($result->isForbidden());
  }

  /**
   * Anonymous account is forbidden.
   */
  public function testAnonymousAccountIsForbidden(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(NULL);
    $account->method('isAnonymous')->willReturn(TRUE);
    $policy = $this->policy([7], FALSE, [7]);
    $result = $policy->access($this->topic(), $account, 'view');
    self::assertTrue($result->isForbidden());
  }

  /**
   * Other operations are neutral.
   */
  public function testOtherOperationsAreNeutral(): void {
    $policy = $this->policy([7], FALSE, []);
    self::assertTrue($policy->access($this->topic(), $this->account(5), 'update')->isNeutral());
  }

  /**
   * Resolver failure fails closed without caching.
   */
  public function testResolverFailureFailsClosedWithoutCaching(): void {
    $resolver = $this->createMock(RestrictedForumGroupResolverInterface::class);
    $resolver->method('groupIdsForTopic')->willThrowException(new \RuntimeException('boom'));
    $policy = new RestrictedForumAccessPolicy(
      $resolver,
      $this->activeAssociate(TRUE),
      $this->createMock(LoggerInterface::class),
    );
    $result = $policy->access($this->topic(), $this->account(5), 'view');
    self::assertTrue($result->isForbidden());
    self::assertSame(0, $result->getCacheMaxAge());
  }

  /**
   * Membership check failure fails closed without caching.
   */
  public function testMembershipCheckFailureFailsClosedWithoutCaching(): void {
    $resolver = $this->createMock(RestrictedForumGroupResolverInterface::class);
    $resolver->method('groupIdsForTopic')->willReturn([7]);
    $resolver->method('groupsOfType')->willThrowException(new \RuntimeException('boom'));
    $policy = new RestrictedForumAccessPolicy(
      $resolver,
      $this->activeAssociate(TRUE),
      $this->createMock(LoggerInterface::class),
    );
    $result = $policy->access($this->topic(), $this->account(5), 'view');
    self::assertTrue($result->isForbidden());
    self::assertSame(0, $result->getCacheMaxAge());
  }

  /**
   * Builds the access policy with stubbed collaborators.
   *
   * @param int[] $restrictedGroupIds
   *   Groups that restrict the topic.
   * @param bool $active
   *   Situação ativa da conta.
   * @param int[] $memberGroupIds
   *   Groups em que a conta é membro.
   * @param int[]|null $groupsOfTypeIds
   *   Groups existentes do tipo configurado; padrão: união dos demais.
   */
  private function policy(array $restrictedGroupIds, bool $active, array $memberGroupIds, ?array $groupsOfTypeIds = NULL): RestrictedForumAccessPolicy {
    return new RestrictedForumAccessPolicy(
      $this->resolver($restrictedGroupIds, $memberGroupIds, $groupsOfTypeIds),
      $this->activeAssociate($active),
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Returns a resolver yielding topic groups and membership fixtures.
   */
  private function resolver(array $restrictedGroupIds, array $memberGroupIds, ?array $groupsOfTypeIds = NULL): RestrictedForumGroupResolverInterface {
    $groups = [];
    foreach ($groupsOfTypeIds ?? array_unique(array_merge($restrictedGroupIds, $memberGroupIds)) as $gid) {
      $groups[$gid] = $this->group($gid, in_array($gid, $memberGroupIds, TRUE));
    }
    $resolver = $this->createMock(RestrictedForumGroupResolverInterface::class);
    $resolver->method('groupIdsForTopic')->willReturn($restrictedGroupIds);
    $resolver->method('groupsOfType')->willReturn($groups);
    return $resolver;
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

  /**
   * Returns an associate marker reporting the given status.
   */
  private function activeAssociate(bool $active): ActiveAssociateInterface {
    $marker = $this->createMock(ActiveAssociateInterface::class);
    $marker->method('isActiveAssociate')->willReturn($active);
    $marker->method('associateCacheTags')->willReturn(['aculta_associado_list', 'user:5']);
    return $marker;
  }

  /**
   * Returns a forum topic fixture.
   */
  private function topic(): NodeInterface {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('id')->willReturn(11);
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
