<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Hook\ParticipationEditWindowHooks;
use Drupal\aculta_user\ParticipationEditWindow;
use Drupal\aculta_user\ParticipationRules;
use Drupal\comment\CommentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterizes how the edit window is attached to Entity Access.
 *
 * The hook is the only consumer of aculta_user.participation_edit_window. It
 * filters the entity types that carry a shared discussion and delegates every
 * other decision to the policy, so unrelated entities never pick up the
 * policy's cache contexts or max-age.
 */
#[Group('aculta_user')]
final class ParticipationEditWindowHooksTest extends TestCase {

  /**
   * Comments in other bundles are outside the shared discussion policy.
   */
  public function testNonDiscussionCommentsStayNeutral(): void {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getEntityTypeId')->willReturn('comment');
    $comment->method('bundle')->willReturn('comment');

    $result = $this->hook()->entityAccess($comment, 'update', $this->account(17));

    self::assertTrue($result->isNeutral());
    self::assertSame([], $result->getCacheContexts());
    self::assertSame(Cache::PERMANENT, $result->getCacheMaxAge());
  }

  /**
   * Entity types without a shared discussion never reach the policy.
   */
  public function testOtherEntityTypesStayNeutral(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('taxonomy_term');

    $result = $this->hook()->entityAccess($entity, 'update', $this->account(17));

    self::assertTrue($result->isNeutral());
    self::assertSame([], $result->getCacheContexts());
  }

  /**
   * A discussion comment inside its window delegates to the policy.
   */
  public function testDiscussionCommentDelegatesToThePolicy(): void {
    $comment = $this->comment(17, 'aculta_forum_topic', 900);

    $result = $this->hook()->entityAccess($comment, 'update', $this->account(17));

    self::assertTrue($result->isNeutral());
    self::assertSame(['user', 'user.permissions'], $result->getCacheContexts());
    self::assertSame(500, $result->getCacheMaxAge());
    self::assertContains('config:aculta_forum.settings', $result->getCacheTags());
    self::assertContains('comment:1', $result->getCacheTags());
  }

  /**
   * An expired discussion window is forbidden by the delegated policy.
   */
  public function testExpiredDiscussionCommentIsForbidden(): void {
    $comment = $this->comment(17, 'aculta_forum_topic', 100);

    $result = $this->hook()->entityAccess($comment, 'update', $this->account(17));

    self::assertTrue($result->isForbidden());
    self::assertSame(['user', 'user.permissions'], $result->getCacheContexts());
  }

  /**
   * LMS hosts keep their own window while sharing the same hook.
   */
  public function testLmsDiscussionCommentUsesTheLmsWindow(): void {
    $comment = $this->comment(17, 'aculta_lms_topic', 700);

    $result = $this->hook()->entityAccess($comment, 'update', $this->account(17));

    // Ten minutes after publication this is still open on the Forum policy and
    // already closed on the five-minute LMS policy.
    self::assertTrue($result->isForbidden());
    self::assertContains('config:aculta_forum_lms.settings', $result->getCacheTags());
  }

  /**
   * Forum posts are delegated as well, with their own configuration.
   */
  public function testForumPostDelegatesToThePolicy(): void {
    $post = $this->forumPost(17, 900);

    $result = $this->hook()->entityAccess($post, 'update', $this->account(17));

    self::assertTrue($result->isNeutral());
    self::assertSame(['user', 'user.permissions'], $result->getCacheContexts());
    self::assertSame(500, $result->getCacheMaxAge());
    self::assertContains('config:aculta_forum.settings', $result->getCacheTags());
  }

  /**
   * Non-update operations are decided by the base access handlers.
   */
  public function testNonUpdateOperationsStayNeutral(): void {
    $comment = $this->comment(17, 'aculta_forum_topic', 100);

    $result = $this->hook()->entityAccess($comment, 'view', $this->account(17));

    self::assertTrue($result->isNeutral());
    self::assertSame([], $result->getCacheContexts());
  }

  /**
   * Other accounts never inherit the author window restriction.
   */
  public function testOtherAccountsStayNeutral(): void {
    $comment = $this->comment(17, 'aculta_forum_topic', 100);

    $result = $this->hook()->entityAccess($comment, 'update', $this->account(18));

    self::assertTrue($result->isNeutral());
    self::assertSame([], $result->getCacheContexts());
  }

  /**
   * Administrative permissions keep the base handlers in charge.
   */
  public function testAdministrativePermissionStaysNeutral(): void {
    $comment = $this->comment(17, 'aculta_forum_topic', 100);

    $result = $this->hook()->entityAccess($comment, 'update', $this->account(17, 'administer comments'));

    self::assertTrue($result->isNeutral());
    self::assertSame([], $result->getCacheContexts());
  }

  /**
   * Builds the hook around the real policy and a fixed clock.
   */
  private function hook(int $now = 1000): ParticipationEditWindowHooks {
    $minutes = [
      'aculta_forum.settings' => 10,
      'aculta_forum_lms.settings' => 5,
    ];
    $configs = [];
    foreach ($minutes as $name => $value) {
      $configs[$name] = $this->config($value, $name);
    }
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnCallback(
      static fn (string $name): ImmutableConfig => $configs[$name] ?? $configs['aculta_forum.settings'],
    );

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($now);

    return new ParticipationEditWindowHooks(
      new ParticipationEditWindow($configFactory, $time),
    );
  }

  /**
   * Builds a config object exposing only the edit window.
   */
  private function config(int $minutes, string $name): ImmutableConfig {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(
      static fn (string $key): ?int => $key === 'edit_window_minutes' ? $minutes : NULL,
    );
    $config->method('getCacheTags')->willReturn(['config:' . $name]);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $config;
  }

  /**
   * Creates a discussion comment hosted by a node.
   *
   * @param int $owner
   *   Owner account ID.
   * @param string $hostBundle
   *   Host node bundle.
   * @param int $publishedAt
   *   First-publication timestamp.
   */
  private function comment(int $owner, string $hostBundle, int $publishedAt): CommentInterface {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn($hostBundle);

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getEntityTypeId')->willReturn('comment');
    $comment->method('bundle')->willReturn(ParticipationRules::DISCUSSION_COMMENT_TYPE);
    $comment->method('getOwnerId')->willReturn($owner);
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('hasField')->willReturnCallback(
      static fn (string $field): bool => $field === 'field_aculta_published_at',
    );
    $comment->method('get')->with('field_aculta_published_at')->willReturn(
      new ParticipationEditWindowHooksFieldList($publishedAt),
    );
    $comment->method('getCacheTags')->willReturn(['comment:1']);
    $comment->method('getCacheContexts')->willReturn([]);
    $comment->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $comment;
  }

  /**
   * Creates a ForumPost-shaped owner entity.
   *
   * @param int $owner
   *   Owner account ID.
   * @param int $firstPublished
   *   First-publication timestamp.
   */
  private function forumPost(int $owner, int $firstPublished): FieldableEntityInterface&EntityOwnerInterface {
    $post = $this->createMockForIntersectionOfInterfaces([
      FieldableEntityInterface::class,
      EntityOwnerInterface::class,
    ]);
    $post->method('getEntityTypeId')->willReturn('aculta_forum_post');
    $post->method('getOwnerId')->willReturn($owner);
    $post->method('hasField')->willReturnCallback(
      static fn (string $field): bool => $field === 'first_published',
    );
    $post->method('get')->with('first_published')->willReturn(
      new ParticipationEditWindowHooksFieldList($firstPublished),
    );
    $post->method('getCacheTags')->willReturn(['aculta_forum_post:17']);
    $post->method('getCacheContexts')->willReturn([]);
    $post->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $post;
  }

  /**
   * Creates an account with an optional administrative permission.
   */
  private function account(int $uid, ?string $permission = NULL): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $candidate): bool => $permission !== NULL && $candidate === $permission,
    );
    return $account;
  }

}

/**
 * Minimal field list exposing a timestamp.
 */
final class ParticipationEditWindowHooksFieldList extends FieldItemList {

  public function __construct(private readonly int $timestamp) {}

  /**
   * {@inheritdoc}
   */
  public function __get($property_name): mixed {
    return $property_name === 'value' ? $this->timestamp : parent::__get($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return FALSE;
  }

}
