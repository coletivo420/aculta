<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\lms\Entity\Activity;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\Lesson;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests deadline lookup against LMS course content references. */
#[CoversClass(ClassActivityDeadlineManager::class)]
#[Group('aculta_lms')]
final class ClassActivityDeadlineManagerTest extends UnitTestCase {

  /**
   * A published activity referenced by a published course lesson is valid. */
  public function testActivityBelongsToCourseThroughPublishedLesson(): void {
    [$manager, $course, $activity] = $this->managerFixture(TRUE);
    self::assertTrue($manager->activityBelongsToCourse($activity, $course));
  }

  /**
   * Unpublished lessons do not count as an activity's course context. */
  public function testUnpublishedLessonDoesNotEstablishCourseContext(): void {
    [$manager, $course, $activity] = $this->managerFixture(FALSE);
    self::assertFalse($manager->activityBelongsToCourse($activity, $course));
  }

  /**
   * Creates a real manager with LMS entity test doubles.
   *
   * @return array{0: \Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager, 1: \Drupal\lms\Entity\Bundle\CourseInterface, 2: \Drupal\lms\Entity\ActivityInterface}
   *   Manager, course, and activity.
   */
  private function managerFixture(bool $lessonPublished): array {
    $activity = $this->createMock(Activity::class);
    $activity->method('id')->willReturn('44');
    $activity->method('isPublished')->willReturn(TRUE);
    $lesson = $this->createMock(Lesson::class);
    $lesson->method('isPublished')->willReturn($lessonPublished);
    $lesson->method('hasField')->with('activities')->willReturn(TRUE);
    $lessonActivities = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $lessonActivities->method('referencedEntities')->willReturn([$activity]);
    $lesson->method('get')->with('activities')->willReturn($lessonActivities);
    $courseLessons = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $courseLessons->method('referencedEntities')->willReturn([$lesson]);
    $course = $this->createMock(CourseInterface::class);
    $course->method('get')->with(CourseInterface::LESSONS)->willReturn($courseLessons);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $account = $this->createMock(AccountProxyInterface::class);
    $access = new ClassActivityDeadlineAccessCheck($entityTypeManager, $account);
    return [new ClassActivityDeadlineManager($entityTypeManager, $access), $course, $activity];
  }

}
