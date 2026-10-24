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
 * Tests the private conversation rules and administrator limits.
 */
#[CoversClass(ConversationPolicy::class)]
#[Group('aculta_messenger')]
final class ConversationPolicyTest extends UnitTestCase {

  /**
   * Creates the conversation policy with test settings.
   */
  private function policy(array $settings = []): ConversationPolicy {
    return new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => $settings]));
  }

  /**
   * Tests the kind follows the member count behavior.
   */
  public function testKindFollowsTheMemberCount(): void {
    $policy = $this->policy();
    $this->assertSame(ConversationPolicy::KIND_DIRECT, $policy->kindOf($this->thread(2)));
    $this->assertSame(ConversationPolicy::KIND_GROUP, $policy->kindOf($this->thread(3)));
  }

  /**
   * Tests the defaults when nothing is saved behavior.
   */
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

  /**
   * Tests the the administrators values are used behavior.
   */
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

  /**
   * Tests the group creation off leaves only one recipient behavior.
   */
  public function testGroupCreationOffLeavesOnlyOneRecipient(): void {
    $policy = $this->policy([
      'group_creation_enabled' => FALSE,
      'group_max_members' => 10,
    ]);
    $account = $this->createMock(AccountInterface::class);
    $this->assertSame(1, $policy->maxRecipients(ConversationPolicy::KIND_GROUP, $account));
  }

  /**
   * Tests the retention policy applies to private conversations behavior.
   */
  public function testRetentionPolicyAppliesToPrivateConversations(): void {
    $policy = $this->policy();
    $this->assertSame(6, $policy->retentionMonths());
  }

  /**
   * Tests the values outside the limits are clamped behavior.
   */
  public function testValuesOutsideTheLimitsAreClamped(): void {
    $policy = $this->policy([
      'group_max_members' => 999,
      'retention_months' => 0,
      'cleanup_batch' => 0,
      'send_limit_per_hour' => 99999,
    ]);
    $this->assertSame(50, $policy->groupMaxMembers());
    $this->assertSame(1, $policy->retentionMonths());
    $this->assertSame(1, $policy->cleanupBatch());
    $this->assertSame(1000, $policy->sendLimitPerHour());
  }

  /**
   * Creates a thread fixture with the requested member count.
   */
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
