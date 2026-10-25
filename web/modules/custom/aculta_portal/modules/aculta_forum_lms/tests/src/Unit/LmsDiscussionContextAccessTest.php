<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests role and comment access decisions for a course discussion.
 */
#[Group('aculta_forum_lms')]
final class LmsDiscussionContextAccessTest extends UnitTestCase {

  /**
   * Course members may write; only the contextual Educator may moderate.
   */
  public function testStudentCanWriteAndEducatorCanModerate(): void {
    $student = $this->createMock(AccountInterface::class);
    $student->method('hasPermission')->with('post comments')->willReturn(TRUE);
    $educator = $this->createMock(AccountInterface::class);
    $educator->method('hasPermission')->with('post comments')->willReturn(TRUE);

    $course = $this->createMock(CourseInterface::class);
    $this->addCacheability($course);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('access')->with('view', self::anything(), TRUE)->willReturn(AccessResult::allowed());
    $course->method('takeAccess')->willReturnCallback(
      static fn (AccountInterface $account): AccessResult => $account === $student
        ? AccessResult::allowed()
        : AccessResult::forbidden(),
    );
    $course->method('hasPermission')->willReturnCallback(
      static fn (string $permission, AccountInterface $account): bool => $permission === 'grade students'
        && $account === $educator,
    );

    $provider = $this->providerForCourse($course);
    $topic = $this->createTopic($this->createCommentField(2));
    $studentContext = $provider->getContext($topic, $student);
    $educatorContext = $provider->getContext($topic, $educator);

    self::assertTrue($topic->access('view', $student, TRUE)->isAllowed());
    self::assertTrue($course->access('view', $student, TRUE)->isAllowed());
    self::assertTrue($course->takeAccess($student)->isAllowed());
    self::assertTrue($course->hasPermission('grade students', $student) === FALSE);
    self::assertTrue($course->hasPermission('grade students', $educator));

    self::assertTrue($studentContext->getReadAccess()->isAllowed());
    self::assertTrue($studentContext->getWriteAccess()->isAllowed());
    self::assertTrue($studentContext->getModerationAccess()->isForbidden());
    self::assertTrue($educatorContext->getReadAccess()->isAllowed());
    self::assertTrue($educatorContext->getWriteAccess()->isAllowed());
    self::assertTrue($educatorContext->getModerationAccess()->isAllowed());
    self::assertContains('user.group_permissions', $educatorContext->getCacheContexts());
  }

  /**
   * A closed Comment field blocks new participation for an enrolled student.
   */
  public function testClosedCommentFieldBlocksStudentWriting(): void {
    $student = $this->createMock(AccountInterface::class);
    $student->method('hasPermission')->with('post comments')->willReturn(TRUE);
    $course = $this->createMock(CourseInterface::class);
    $this->addCacheability($course);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('access')->willReturn(AccessResult::allowed());
    $course->method('takeAccess')->willReturn(AccessResult::allowed());
    $course->method('hasPermission')->willReturn(FALSE);

    $provider = $this->providerForCourse($course);
    $context = $provider->getContext($this->createTopic($this->createCommentField(1)), $student);

    self::assertTrue($context->getReadAccess()->isAllowed());
    self::assertFalse($context->getWriteAccess()->isAllowed());
  }

  /**
   * A course Educator may access a class discussion without class membership.
   */
  public function testClassMembershipAndCourseEducatorAreAlternativeAccess(): void {
    $student = $this->account(31);
    $educator = $this->account(32);
    $outsider = $this->account(33);
    $studentUser = $this->user();
    $educatorUser = $this->user();
    $outsiderUser = $this->user();

    $course = $this->createMock(CourseInterface::class);
    $this->addCacheability($course);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasPermission')->willReturnCallback(
      static fn (string $permission, AccountInterface $account): bool => $permission === 'grade students'
        && $account === $educator,
    );
    $class = $this->createMock(GroupInterface::class);
    $this->addCacheability($class);
    $class->method('bundle')->willReturn('lms_class');
    $class->method('id')->willReturn('2');
    $class->method('access')->willReturn(AccessResult::allowed());

    $topicRelationship = $this->relationship($class);
    $parentRelationship = $this->relationship($course);
    $studentMembership = $this->createMock(GroupRelationshipInterface::class);
    $this->addCacheability($studentMembership);
    $studentMembership->method('getGroupId')->willReturn('2');

    $relationshipStorage = $this->createMock(GroupRelationshipStorageInterface::class);
    $relationshipStorage->method('loadByEntity')->willReturnCallback(
      static function ($entity, ?string $plugin_id = NULL) use ($class, $topicRelationship, $parentRelationship, $studentUser, $studentMembership): array {
        if ($entity instanceof NodeInterface && $plugin_id === 'group_node:aculta_lms_topic') {
          return [$topicRelationship];
        }
        if ($entity === $class && $plugin_id === 'lms_classes') {
          return [$parentRelationship];
        }
        if ($entity === $studentUser && $plugin_id === 'group_membership') {
          return [$studentMembership];
        }
        return [];
      },
    );

    $users = [31 => $studentUser, 32 => $educatorUser, 33 => $outsiderUser];
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->willReturnCallback(static fn (int $uid): ?UserInterface => $users[$uid] ?? NULL);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnCallback(
      static fn (string $entity_type): EntityStorageInterface|GroupRelationshipStorageInterface => $entity_type === 'group_relationship'
        ? $relationshipStorage
        : $userStorage,
    );

    $provider = new LmsDiscussionContextProvider($manager);
    $topic = $this->createTopic($this->createCommentField(2));
    $studentContext = $provider->getContext($topic, $student);
    $educatorContext = $provider->getContext($topic, $educator);
    $outsiderContext = $provider->getContext($topic, $outsider);

    self::assertTrue($studentContext->getReadAccess()->isAllowed());
    self::assertTrue($educatorContext->getReadAccess()->isAllowed());
    self::assertTrue($educatorContext->getModerationAccess()->isAllowed());
    self::assertTrue($outsiderContext->getReadAccess()->isForbidden());
  }

  /**
   * Builds a provider with one Group Node course relationship.
   */
  private function providerForCourse(CourseInterface $course): LmsDiscussionContextProvider {
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $this->addCacheability($relationship);
    $relationship->method('getGroup')->willReturn($course);
    $storage = $this->createMock(GroupRelationshipStorageInterface::class);
    $storage->method('loadByEntity')->willReturn([$relationship]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('group_relationship')->willReturn($storage);
    return new LmsDiscussionContextProvider($manager);
  }

  /**
   * Creates a persisted topic with cacheable view access and canonical URL.
   */
  private function createTopic(FieldItemListInterface $commentField): NodeInterface {
    $topic = $this->createMock(NodeInterface::class);
    $this->addCacheability($topic);
    $topic->method('bundle')->willReturn('aculta_lms_topic');
    $topic->method('isNew')->willReturn(FALSE);
    $topic->method('uuid')->willReturn('topic-uuid');
    $topic->method('id')->willReturn('21');
    $topic->method('access')->with('view', self::anything(), TRUE)->willReturn(AccessResult::allowed());
    $topic->method('hasField')->with('field_aculta_discussion')->willReturn(TRUE);
    $topic->method('get')->with('field_aculta_discussion')->willReturn($commentField);
    $topic->method('toUrl')->with('canonical')->willReturn(Url::fromRoute('<front>'));
    return $topic;
  }

  /**
   * Creates the shared discussion field's status value.
   */
  private function createCommentField(int $status): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['status' => $status]]);
    return $field;
  }

  /**
   * Creates a test account with permission to submit a Comment.
   */
  private function account(int $uid): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('hasPermission')->with('post comments')->willReturn(TRUE);
    return $account;
  }

  /**
   * Creates a cacheable user entity.
   */
  private function user(): UserInterface {
    $user = $this->createMock(UserInterface::class);
    $this->addCacheability($user);
    return $user;
  }

  /**
   * Creates a cacheable Group relationship with its owning Group.
   */
  private function relationship(GroupInterface $group): GroupRelationshipInterface {
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $this->addCacheability($relationship);
    $relationship->method('getGroup')->willReturn($group);
    return $relationship;
  }

  /**
   * Provides cacheability metadata on a mocked entity or field list.
   */
  private function addCacheability(object $object): void {
    $object->method('getCacheContexts')->willReturn([]);
    $object->method('getCacheTags')->willReturn([]);
    $object->method('getCacheMaxAge')->willReturn(-1);
  }

}
