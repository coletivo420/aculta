<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Event\CourseContentReleasedEvent;
use Drupal\aculta_lms\Hook\CourseContentEventHooks;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\Group;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Tests learner notices for content stored in ACULTA's own entities. */
#[CoversClass(CourseContentEventHooks::class)]
#[TestGroup('aculta_lms')]
final class CourseContentEventHooksTest extends UnitTestCase {

  /**
   *
   */
  public function testPublishedCourseInsertPublishesRelease(): void {
    $course = $this->createMock(Group::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('getEntityTypeId')->willReturn('group');
    $course->method('isPublished')->willReturn(TRUE);
    $course->method('uuid')->willReturn('course-uuid');
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('dispatch')->with($this->callback(static fn(object $event): bool =>
      $event instanceof CourseContentReleasedEvent
      && $event->noticeType() === 'course_release'
      && $event->contentUuid === 'course-uuid'
    ));

    (new CourseContentEventHooks($dispatcher, $this->createMock(EntityTypeManagerInterface::class)))->entityInsert($course);
  }

  /**
   *
   */
  public function testPublishedOwnLessonInsertPublishesRelease(): void {
    $lesson = $this->contentEntity('aculta_lms_lesson', 'lesson-uuid', ['published' => TRUE]);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('dispatch')->with($this->callback(static fn(object $event): bool =>
      $event instanceof CourseContentReleasedEvent
      && $event->noticeType() === 'lesson_release'
      && $event->contentUuid === 'lesson-uuid'
    ));

    (new CourseContentEventHooks($dispatcher, $this->createMock(EntityTypeManagerInterface::class)))->entityInsert($lesson);
  }

  /**
   *
   */
  public function testActivityAvailabilityInheritsPublishedLesson(): void {
    $lesson = $this->contentEntity('aculta_lms_lesson', 'lesson-uuid', ['published' => TRUE]);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with(4)->willReturn($lesson);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_lesson')->willReturn($storage);
    $activity = $this->contentEntity('aculta_lms_activity', 'activity-uuid', ['lesson_id' => 4]);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('dispatch')->with($this->callback(static fn(object $event): bool =>
      $event instanceof CourseContentReleasedEvent
      && $event->noticeType() === 'activity_release'
      && $event->contentUuid === 'activity-uuid'
    ));

    (new CourseContentEventHooks($dispatcher, $manager))->entityInsert($activity);
  }

  /**
   *
   */
  public function testActivityUnderUnpublishedLessonDoesNotPublish(): void {
    $lesson = $this->contentEntity('aculta_lms_lesson', 'lesson-uuid', ['published' => FALSE]);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(4)->willReturn($lesson);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_lesson')->willReturn($storage);
    $activity = $this->contentEntity('aculta_lms_activity', 'activity-uuid', ['lesson_id' => 4]);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::never())->method('dispatch');

    (new CourseContentEventHooks($dispatcher, $manager))->entityInsert($activity);
  }

  /**
   *
   */
  public function testEditorialUpdateOfAvailableLessonDoesNotPublish(): void {
    $original = $this->contentEntity('aculta_lms_lesson', 'lesson-uuid', ['published' => TRUE]);
    $lesson = $this->contentEntity('aculta_lms_lesson', 'lesson-uuid', ['published' => TRUE], $original);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::never())->method('dispatch');

    (new CourseContentEventHooks($dispatcher, $this->createMock(EntityTypeManagerInterface::class)))->entityUpdate($lesson);
  }

  /**
   *
   */
  private function contentEntity(string $type, string $uuid, array $fields, ?ContentEntityBase $original = NULL): ContentEntityBase {
    $entity = $this->createMock(ContentEntityBase::class);
    $entity->method('getEntityTypeId')->willReturn($type);
    $entity->method('uuid')->willReturn($uuid);
    $entity->method('getOriginal')->willReturn($original);
    $entity->method('get')->willReturnCallback(static function (string $name) use ($fields): object {
      return (object) ['value' => $fields[$name] ?? NULL];
    });
    return $entity;
  }

}
