<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\aculta_forum_lms\Access\LmsEditWindowExemption;
use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterizes the only EditWindowExemptionInterface implementation.
 *
 * The exemption lets the LMS context decide who keeps moderating rights after
 * the author window closes. These tests pin its boundaries against the real
 * LmsDiscussionContextProvider, including the conservative ungrouped path.
 */
#[Group('aculta_forum_lms')]
final class LmsEditWindowExemptionTest extends TestCase {

  private const NODE_RELATIONSHIP = 'group_node:aculta_lms_topic';

  /**
   * Entities that are not comments never touch the contextual decision.
   */
  public function testNonCommentEntitiesAreNeutral(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');

    $result = $this->exemption($entityTypeManager)->access($entity, $this->account());

    self::assertTrue($result->isNeutral());
    self::assertSame([], $result->getCacheContexts());
  }

  /**
   * Comments hosted outside a node stay neutral with the comment dependency.
   */
  public function testCommentsOnNonNodeHostsAreNeutral(): void {
    $comment = $this->comment($this->createMock(EntityInterface::class));

    $result = $this->exemption()->access($comment, $this->account());

    self::assertTrue($result->isNeutral());
    self::assertSame(['comment:1'], $result->getCacheTags());
    self::assertSame([], $result->getCacheContexts());
  }

  /**
   * Comments on nodes outside the LMS bundle keep the author window.
   */
  public function testCommentsOnNonLmsHostsAreNeutral(): void {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn('aculta_forum_topic');

    $result = $this->exemption($this->entityTypeManager([]))->access($this->comment($host), $this->account());

    self::assertTrue($result->isNeutral());
    self::assertSame(['comment:1'], $result->getCacheTags());
    self::assertSame([], $result->getCacheContexts());
  }

  /**
   * An ungrouped LMS topic keeps the window and never gains moderation.
   *
   * The provider fails closed when the topic has no unique Group relationship,
   * so the exemption reports neutral with the Group permission context.
   */
  public function testPersistedTopicWithoutGroupRelationshipStaysNeutral(): void {
    $topic = $this->topic();

    $result = $this->exemption($this->entityTypeManager([]))->access($this->comment($topic), $this->account());

    self::assertTrue($result->isNeutral());
    self::assertContains('user.group_permissions', $result->getCacheContexts());
    self::assertContains('aculta_lms_topic:5', $result->getCacheTags());
  }

  /**
   * A saved LMS topic without a UUID cannot resolve a context.
   *
   * The provider guard is mirrored here, so the exemption fails closed with a
   * neutral result instead of letting a LogicException escape the access
   * pipeline. The author window therefore still applies.
   */
  public function testTopicWithoutAnIdentifierIsNeutral(): void {
    $topic = $this->topic(uuid: '');

    $result = $this->exemption($this->entityTypeManager([]))->access($this->comment($topic), $this->account());

    self::assertTrue($result->isNeutral());
    self::assertContains('comment:1', $result->getCacheTags());
    self::assertContains('aculta_lms_topic:5', $result->getCacheTags());
  }

  /**
   * A topic that has not been saved yet is also neutral.
   *
   * Unsaved topics have no context to resolve and must never grant moderation.
   */
  public function testNewTopicIsNeutral(): void {
    $topic = $this->topic(uuid: '', isNew: TRUE);

    $result = $this->exemption($this->entityTypeManager([]))->access($this->comment($topic), $this->account());

    self::assertTrue($result->isNeutral());
    self::assertContains('comment:1', $result->getCacheTags());
  }

  /**
   * Contextual educators keep moderating after the window closes.
   *
   * The course decides through the contextual "grade students" permission; the
   * exemption only reuses that decision and its cacheability.
   */
  public function testContextualEducatorModerationExemptsTheWindow(): void {
    $course = $this->course();
    $topic = $this->topic();

    $result = $this->exemption($this->entityTypeManager([$this->relationship($course)]))
      ->access($this->comment($topic), $this->account());

    self::assertTrue($result->isAllowed());
    self::assertContains('user.group_permissions', $result->getCacheContexts());
    self::assertContains('group:3', $result->getCacheTags());
  }

  /**
   * A course without the educator permission stays neutral.
   */
  public function testCourseWithoutEducatorPermissionStaysNeutral(): void {
    $course = $this->course(['grade students' => FALSE]);

    $result = $this->exemption($this->entityTypeManager([$this->relationship($course)]))
      ->access($this->comment($this->topic()), $this->account());

    self::assertTrue($result->isNeutral());
    self::assertContains('user.group_permissions', $result->getCacheContexts());
  }

  /**
   * Builds the exemption around the real provider.
   */
  private function exemption(?EntityTypeManagerInterface $entityTypeManager = NULL): LmsEditWindowExemption {
    return new LmsEditWindowExemption(
      new LmsDiscussionContextProvider($entityTypeManager ?? $this->createMock(EntityTypeManagerInterface::class)),
    );
  }

  /**
   * Builds an entity type manager returning a relationship storage.
   *
   * @param \Drupal\group\Entity\GroupRelationshipInterface[] $relationships
   *   Relationships returned for the LMS topic.
   */
  private function entityTypeManager(array $relationships): EntityTypeManagerInterface {
    $storage = $this->createMock(GroupRelationshipStorageInterface::class);
    $storage->method('loadByEntity')->willReturn($relationships);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('group_relationship')->willReturn($storage);
    return $entityTypeManager;
  }

  /**
   * Creates a Group relationship to a course.
   */
  private function relationship(CourseInterface $course): GroupRelationshipInterface {
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getGroup')->willReturn($course);
    $relationship->method('getCacheTags')->willReturn(['group_relationship:1']);
    $relationship->method('getCacheContexts')->willReturn([]);
    $relationship->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $relationship;
  }

  /**
   * Creates a course Group granting contextual educator rights.
   *
   * @param array<string, bool> $permissions
   *   Permission decisions keyed by permission name.
   */
  private function course(array $permissions = ['grade students' => TRUE]): CourseInterface {
    $course = $this->createMock(CourseInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('3');
    $course->method('access')->willReturn(AccessResult::allowed());
    $course->method('takeAccess')->willReturn(AccessResult::allowed());
    $course->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => (bool) ($permissions[$permission] ?? FALSE),
    );
    $course->method('getCacheTags')->willReturn(['group:3']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $course;
  }

  /**
   * Creates a persisted LMS topic shaped like a group host.
   */
  private function topic(string $uuid = 'topic-uuid', bool $isNew = FALSE): NodeInterface {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_lms_topic');
    $topic->method('isNew')->willReturn($isNew);
    $topic->method('uuid')->willReturn($uuid);
    $topic->method('id')->willReturn('5');
    $topic->method('access')->willReturn(AccessResult::allowed());
    $topic->method('hasField')->willReturnCallback(
      static fn (string $field): bool => $field === 'field_aculta_discussion',
    );
    $discussion = $this->createMock(FieldItemListInterface::class);
    $discussion->method('getValue')->willReturn([['status' => 2]]);
    $topic->method('get')->with('field_aculta_discussion')->willReturn($discussion);
    $topic->method('toUrl')->willReturn($this->createMock(Url::class));
    $topic->method('getCacheTags')->willReturn(['aculta_lms_topic:5']);
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $topic;
  }

  /**
   * Creates a discussion comment hosted by the given entity.
   */
  private function comment(EntityInterface $host): CommentInterface {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('getCacheTags')->willReturn(['comment:1']);
    $comment->method('getCacheContexts')->willReturn([]);
    $comment->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $comment;
  }

  /**
   * Creates an account able to post comments.
   */
  private function account(): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('10');
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => $permission === 'post comments',
    );
    return $account;
  }

}
