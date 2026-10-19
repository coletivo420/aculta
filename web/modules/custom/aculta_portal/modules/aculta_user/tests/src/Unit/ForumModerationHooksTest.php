<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_user\Hook\ForumModerationHooks;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Access\AccessResult;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Checks confirmation-gated moderation for new public Forum topics.
 */
#[Group('aculta_user')]
final class ForumModerationHooksTest extends TestCase {

  /**
   * Confirmed editors can update and delete another author's topic.
   */
  public function testConfirmedEditorCanModerateAnotherAuthorsNewTopic(): void {
    $hook = $this->hook(TRUE);
    $editor = $this->account(10, ['content_editor', 'email_confirmed']);
    $topic = $this->topic(20);

    foreach (['update', 'delete'] as $operation) {
      $result = $hook->entityAccess($topic, $operation, $editor);
      self::assertInstanceOf(AccessResult::class, $result);
      self::assertTrue($result->isAllowed());
      self::assertContains('user.roles', $result->getCacheContexts());
      self::assertContains('node:20', $result->getCacheTags());
    }
  }

  /**
   * Editors without a confirmed email cannot moderate another author's topic.
   */
  public function testUnconfirmedEditorCannotModerateAnotherAuthorsNewTopic(): void {
    $hook = $this->hook(FALSE);
    $editor = $this->account(10, ['content_editor']);
    $result = $hook->entityAccess($this->topic(20), 'update', $editor);

    self::assertInstanceOf(AccessResult::class, $result);
    self::assertTrue($result->isForbidden());
    self::assertContains('user.roles', $result->getCacheContexts());
  }

  /**
   * The email-confirmed role alone does not replace the confirmation marker.
   */
  public function testConfirmationRoleAloneDoesNotAuthorizeModeration(): void {
    $hook = $this->hook(FALSE);
    $editor = $this->account(10, ['content_editor', 'email_confirmed']);
    $result = $hook->entityAccess($this->topic(20), 'delete', $editor);

    self::assertInstanceOf(AccessResult::class, $result);
    self::assertTrue($result->isForbidden());
  }

  /**
   * Own-topic and unrelated-bundle access remains with the normal handlers.
   */
  public function testOwnTopicAndUnrelatedBundleRemainNeutral(): void {
    $hook = $this->hook(FALSE);
    $editor = $this->account(10, ['content_editor']);

    self::assertNull($hook->entityAccess($this->topic(10), 'update', $editor));

    $unrelated = $this->createMock(NodeInterface::class);
    $unrelated->method('bundle')->willReturn('article');
    self::assertNull($hook->entityAccess($unrelated, 'update', $editor));
  }

  /**
   * The existing administrative bypass remains neutral in this hook.
   */
  public function testAdministratorBypassRemainsUnaffected(): void {
    $hook = $this->hook(FALSE);
    $administrator = $this->account(1, ['administrator'], TRUE);

    self::assertNull($hook->entityAccess($this->topic(20), 'delete', $administrator));
  }

  /**
   * Builds the hook using the actual confirmation policy.
   */
  private function hook(bool $confirmed): ForumModerationHooks {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturn($confirmed);
    $policy = new EmailConfirmationPolicy(
      $userData,
      $this->createMock(EntityTypeManagerInterface::class),
    );
    return new ForumModerationHooks($policy);
  }

  /**
   * Creates an account mock with the roles and bypass permission under test.
   *
   * @param int $uid
   *   Account ID.
   * @param string[] $roles
   *   The account roles.
   * @param bool $bypass
   *   Whether the account has the core node access bypass.
   */
  private function account(int $uid, array $roles, bool $bypass = FALSE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn((string) $uid);
    $account->method('getRoles')->willReturn($roles);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => $permission === 'bypass node access' && $bypass,
    );
    return $account;
  }

  /**
   * Builds a new Forum topic owned by a different account.
   */
  private function topic(int $ownerId): NodeInterface {
    $owner = $this->createMock(UserInterface::class);
    $owner->method('id')->willReturn((string) $ownerId);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getOwner')->willReturn($owner);
    $topic->method('hasField')->with('field_autor_legado')->willReturn(FALSE);
    $topic->method('id')->willReturn('20');
    $topic->method('uuid')->willReturn('topic-uuid');
    $topic->method('getEntityTypeId')->willReturn('node');
    $topic->method('getCacheTags')->willReturn(['node:20']);
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(-1);
    return $topic;
  }

}
