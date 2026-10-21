<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\aculta_lms\Event\CourseContentReleasedEvent;
use Drupal\aculta_lms\Hook\CourseContentEventHooks;
use Drupal\group\Entity\Group;
use Drupal\lms\Entity\Activity;
use Drupal\lms\Entity\Lesson;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Tests learner notice events for published LMS content transitions.
 */
#[CoversClass(CourseContentEventHooks::class)]
#[TestGroup('aculta_lms')]
final class CourseContentEventHooksTest extends UnitTestCase {

  /**
   * Publishes an event when a course is first created published.
   */
  public function testPublishedCourseInsertPublishesRelease(): void {
    $course = $this->createMock(Group::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('getEntityTypeId')->willReturn('group');
    $course->method('isPublished')->willReturn(TRUE);
    $course->method('uuid')->willReturn('course-uuid');
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(static fn (object $event): bool =>
        $event instanceof CourseContentReleasedEvent
        && $event->noticeType() === 'course_release'
        && $event->contentUuid === 'course-uuid'
        && preg_match('/^[a-f0-9]{32}$/', $event->publicationVersion) === 1
      ));

    (new CourseContentEventHooks($dispatcher))->entityInsert($course);
  }

  /**
   * Publishes when an existing activity becomes published.
   */
  public function testActivityPublicationTransitionPublishesRelease(): void {
    $original = $this->createMock(Activity::class);
    $original->method('isPublished')->willReturn(FALSE);
    $activity = $this->createMock(Activity::class);
    $activity->method('isPublished')->willReturn(TRUE);
    $activity->method('getOriginal')->willReturn($original);
    $activity->method('getEntityTypeId')->willReturn('lms_activity');
    $activity->method('uuid')->willReturn('activity-uuid');
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(static fn (object $event): bool =>
        $event instanceof CourseContentReleasedEvent && $event->noticeType() === 'activity_release'
      ));

    (new CourseContentEventHooks($dispatcher))->entityUpdate($activity);
  }

  /**
   * Adding a published activity to a published lesson creates a release event.
   */
  public function testAddingPublishedActivityToLessonPublishesRelease(): void {
    $oldActivity = $this->createMock(Activity::class);
    $oldActivity->method('id')->willReturn('12');
    $newActivity = $this->createMock(Activity::class);
    $newActivity->method('id')->willReturn('13');
    $newActivity->method('isPublished')->willReturn(TRUE);
    $newActivity->method('getEntityTypeId')->willReturn('lms_activity');
    $newActivity->method('uuid')->willReturn('activity-13-uuid');

    $original = $this->createMock(Lesson::class);
    $original->method('isPublished')->willReturn(TRUE);
    $original->method('hasField')->with('activities')->willReturn(TRUE);
    $original->method('get')->with('activities')->willReturn($this->referenceList([$oldActivity]));
    $lesson = $this->createMock(Lesson::class);
    $lesson->method('isPublished')->willReturn(TRUE);
    $lesson->method('getOriginal')->willReturn($original);
    $lesson->method('hasField')->with('activities')->willReturn(TRUE);
    $lesson->method('get')->with('activities')->willReturn($this->referenceList([$oldActivity, $newActivity]));

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(static fn (object $event): bool =>
        $event instanceof CourseContentReleasedEvent && $event->contentUuid === 'activity-13-uuid'
      ));

    (new CourseContentEventHooks($dispatcher))->entityUpdate($lesson);
  }

  /**
   * Creates an LMS reference list for a content entity mock.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Referenced content entities.
   */
  private function referenceList(array $entities): object {
    $field = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $field->method('referencedEntities')->willReturn($entities);
    return $field;
  }

}
