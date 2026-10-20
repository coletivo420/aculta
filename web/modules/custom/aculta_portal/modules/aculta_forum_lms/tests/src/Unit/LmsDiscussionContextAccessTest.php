<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
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
   * Provides cacheability metadata on a mocked entity or field list.
   */
  private function addCacheability(object $object): void {
    $object->method('getCacheContexts')->willReturn([]);
    $object->method('getCacheTags')->willReturn([]);
    $object->method('getCacheMaxAge')->willReturn(-1);
  }

}
