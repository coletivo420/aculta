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

#[CoversClass(ConversationPolicy::class)]
#[Group('aculta_messenger')]
final class ConversationPolicyTest extends UnitTestCase {

  private ConversationPolicy $policy;

  protected function setUp(): void {
    parent::setUp();
    $this->policy = new ConversationPolicy();
  }

  public function testKindFollowsTheMemberCount(): void {
    $this->assertSame(ConversationPolicy::KIND_DIRECT, $this->policy->kindOf($this->thread(2)));
    $this->assertSame(ConversationPolicy::KIND_GROUP, $this->policy->kindOf($this->thread(3)));
    $this->assertSame(ConversationPolicy::KIND_GROUP, $this->policy->kindOf($this->thread(11)));
  }

  public function testCommonMembersMayAddUpToTenOthersToAGroup(): void {
    $account = $this->createMock(AccountInterface::class);
    $this->assertSame(1, $this->policy->maxRecipients(ConversationPolicy::KIND_DIRECT, $account));
    $this->assertSame(10, $this->policy->maxRecipients(ConversationPolicy::KIND_GROUP, $account));
    $this->assertSame(10, ConversationPolicy::GROUP_MAX_RECIPIENTS);
  }

  public function testCourseChatsArePersistentAndOthersAreKeptSixMonths(): void {
    $this->assertNull($this->policy->retentionMonths(ConversationPolicy::KIND_COURSE));
    $this->assertSame(6, $this->policy->retentionMonths(ConversationPolicy::KIND_DIRECT));
    $this->assertSame(6, $this->policy->retentionMonths(ConversationPolicy::KIND_GROUP));
    $this->assertSame(6, $this->policy->retentionMonths(ConversationPolicy::KIND_FORUM));
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
