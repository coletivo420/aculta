<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests deadline lookup against ACULTA lesson and activity entities.
 */
#[CoversClass(ClassActivityDeadlineManager::class)]
#[Group('aculta_lms')]
final class ClassActivityDeadlineManagerTest extends UnitTestCase {

  /**
   * A published activity in a published lesson for the course is valid.
   */
  public function testActivityBelongsToCourseThroughPublishedLesson(): void {
    [$manager, $course, $activity] = $this->managerFixture(TRUE, 12, 12);
    self::assertTrue($manager->activityBelongsToCourse($activity, $course));
  }

  /**
   * Unpublished lessons do not establish an activity's course context.
   */
  public function testUnpublishedLessonDoesNotEstablishCourseContext(): void {
    [$manager, $course, $activity] = $this->managerFixture(FALSE, 12, 12);
    self::assertFalse($manager->activityBelongsToCourse($activity, $course));
  }

  /**
   * An activity attached to a different course is rejected.
   */
  public function testActivityFromAnotherCourseIsRejected(): void {
    [$manager, $course, $activity] = $this->managerFixture(TRUE, 17, 12);
    self::assertFalse($manager->activityBelongsToCourse($activity, $course));
  }

  /**
   * Creates a manager and field-backed entity doubles.
   *
   * @return array{ClassActivityDeadlineManager, GroupInterface, ContentEntityInterface}
   *   Manager, course, and activity.
   */
  private function managerFixture(bool $lessonPublished, int $lessonCourseId, int $courseId): array {
    $activity = $this->createMock(ContentEntityInterface::class);
    $activity->method('getEntityTypeId')->willReturn('aculta_lms_activity');
    $activity->method('hasField')->with('lesson_id')->willReturn(TRUE);
    $activity->method('get')->with('lesson_id')->willReturn($this->fieldList(44));

    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('hasField')->willReturnMap([['published', TRUE], ['course_id', TRUE]]);
    $lesson->method('get')->willReturnMap([
      ['published', $this->fieldList($lessonPublished)],
      ['course_id', $this->fieldList($lessonCourseId)],
    ]);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with(44)->willReturn($lesson);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())->method('getStorage')->with('aculta_lms_lesson')->willReturn($storage);
    $account = $this->createMock(AccountProxyInterface::class);
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn((string) $courseId);
    $access = new ClassActivityDeadlineAccessCheck($entityTypeManager, $account);
    return [new ClassActivityDeadlineManager($entityTypeManager, $access), $course, $activity];
  }

  /**
   * Creates a field item list with one value.
   *
   * @return FieldItemListInterface<FieldItemInterface>
   *   List containing the supplied field item.
   */
  private function fieldList(mixed $value): FieldItemListInterface {
    $item = $this->createMock(FieldItemInterface::class);
    $item->method('getValue')->willReturn(['value' => $value]);
    $list = $this->createMock(FieldItemListInterface::class);
    $list->method('first')->willReturn($item);
    return $list;
  }

}
