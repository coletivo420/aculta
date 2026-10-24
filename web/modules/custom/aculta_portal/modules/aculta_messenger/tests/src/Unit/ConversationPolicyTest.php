<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The private conversation rules, administrator limits, and defaults when no value is saved.
 */
#[CoversClass(ConversationPolicy::class)]
#[Group('aculta_messenger')]
final class ConversationPolicyTest extends UnitTestCase {

  private function policy(array $settings = []): ConversationPolicy {
    return new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => $settings]));
  }

  public function testKindFollowsTheMemberCount(): void {
    $policy = $this->policy();
    $this->assertSame(ConversationPolicy::KIND_DIRECT, $policy->kindOf($this->thread(2)));
    $this->assertSame(ConversationPolicy::KIND_GROUP, $policy->kindOf($this->thread(3)));
  }

  public function testDefaultsWhenNothingIsSaved(): void {
    $policy = $this->policy();
    $account = $this->createMock(AccountInterface::class);
    $this->assertSame(10, $policy->groupMaxMembers());
    $this->assertSame(10, $policy->maxRecipients(ConversationPolicy::KIND_GROUP, $account));
    $this->assertSame(1, $policy->maxRecipients(ConversationPolicy::KIND_DIRECT, $account));
    $this->assertSame(6, $policy->retentionMonths());
    $this->assertSame(20, $policy->cleanupBatch());
    $this->assertSame(10, $policy->sendLimitPerHour());
    $this->assertTrue($policy->groupCreationEnabled());
  }

  public function testTheAdministratorsValuesAreUsed(): void {
    $policy = $this->policy([
      'group_max_members' => 25,
      'retention_months' => 12,
      'cleanup_batch' => 50,
      'send_limit_per_hour' => 30,
    ]);
    $account = $this->createMock(AccountInterface::class);
    $this->assertSame(25, $policy->maxRecipients(ConversationPolicy::KIND_GROUP, $account));
    $this->assertSame(12, $policy->retentionMonths());
    $this->assertSame(50, $policy->cleanupBatch());
    $this->assertSame(30, $policy->sendLimitPerHour());
  }

  public function testGroupCreationOffLeavesOnlyOneRecipient(): void {
    $policy = $this->policy(['group_creation_enabled' => FALSE, 'group_max_members' => 10]);
    $account = $this->createMock(AccountInterface::class);
    $this->assertSame(1, $policy->maxRecipients(ConversationPolicy::KIND_GROUP, $account));
  }

  public function testRetentionPolicyAppliesToPrivateConversations(): void {
    $policy = $this->policy();
    $this->assertSame(6, $policy->retentionMonths());
  }

  public function testValuesOutsideTheLimitsAreClamped(): void {
    $policy = $this->policy(['group_max_members' => 999, 'retention_months' => 0, 'cleanup_batch' => 0, 'send_limit_per_hour' => 99999]);
    $this->assertSame(50, $policy->groupMaxMembers());
    $this->assertSame(1, $policy->retentionMonths());
    $this->assertSame(1, $policy->cleanupBatch());
    $this->assertSame(1000, $policy->sendLimitPerHour());
  }

  private function thread(int $members): PrivateMessageThreadInterface {
    $users = [];
    for ($i = 1; $i <= $members; $i++) {
      $users[] = $this->createMock(UserInterface::class);
    }
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('getMembers')->willReturn($users);
    return $thread;
  }

}
