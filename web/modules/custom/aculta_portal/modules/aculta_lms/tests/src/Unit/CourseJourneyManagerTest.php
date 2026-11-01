<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\Url;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\Lesson;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\lms\TrainingManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the canonical course journey assembled for Ganjalab.
 */
#[CoversClass(LearningDashboardManager::class)]
#[Group('aculta_lms')]
final class CourseJourneyManagerTest extends UnitTestCase {

  /**
   * Uses persisted LMS position and published lessons without writing state.
   */
  public function testJourneyExposesCurrentLessonStatesAndAllowedDestination(): void {
    $account = new UserSession(['uid' => 42]);

    $lesson_one = $this->createMock(Lesson::class);
    $lesson_one->method('id')->willReturn('lesson-1');
    $lesson_one->method('label')->willReturn('Proibicionismo');
    $lesson_one->method('isPublished')->willReturn(TRUE);
    $lesson_one->method('getCacheContexts')->willReturn([]);
    $lesson_one->method('getCacheTags')->willReturn(['lms_lesson:lesson-1']);
    $lesson_one->method('getCacheMaxAge')->willReturn(-1);

    $lesson_two = $this->createMock(Lesson::class);
    $lesson_two->method('id')->willReturn('lesson-2');
    $lesson_two->method('label')->willReturn('Redução de danos');
    $lesson_two->method('isPublished')->willReturn(TRUE);
    $lesson_two->method('getCacheContexts')->willReturn([]);
    $lesson_two->method('getCacheTags')->willReturn(['lms_lesson:lesson-2']);
    $lesson_two->method('getCacheMaxAge')->willReturn(-1);

    $lesson_items = new \ArrayIterator([
      (object) ['entity' => $lesson_one],
      (object) ['entity' => $lesson_two],
    ]);

    $course = $this->createMock(CourseInterface::class);
    $course->method('id')->willReturn('course-1');
    $course->method('label')->willReturn('Curso');
    $course->method('takeAccess')->with($account)->willReturn(
      AccessResult::allowed()->addCacheContexts(['user.group_permissions']),
    );
    $course->method('get')->with(CourseInterface::LESSONS)->willReturn($lesson_items);
    $course->method('hasField')->with('field_description')->willReturn(FALSE);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn(['group:course-1']);
    $course->method('getCacheMaxAge')->willReturn(-1);

    $lesson_status = $this->createMock(LessonStatusInterface::class);
    $lesson_status->method('id')->willReturn('lesson-status-1');
    $lesson_status->method('getLessonId')->willReturn('lesson-1');
    $lesson_status->method('getCurrentLessonDelta')->willReturn(0);
    $lesson_status->method('getCurrentActivityDelta')->willReturn(1);
    $lesson_status->method('isFinished')->willReturn(TRUE);
    $lesson_status->method('getLesson')->willReturn($lesson_one);
    $lesson_status->method('getCacheContexts')->willReturn([]);
    $lesson_status->method('getCacheTags')->willReturn(['lms_lesson_status:lesson-status-1']);
    $lesson_status->method('getCacheMaxAge')->willReturn(-1);

    $course_status = $this->createMock(CourseStatusInterface::class);
    $course_status->method('id')->willReturn('course-status-1');
    $course_status->method('getStatus')->willReturn(CourseStatusInterface::STATUS_PROGRESS);
    $course_status->method('isFinished')->willReturn(FALSE);
    $course_status->method('getCurrentLessonStatus')->willReturn($lesson_status);
    $course_status->method('getCacheContexts')->willReturn([]);
    $course_status->method('getCacheTags')->willReturn(['lms_course_status:course-status-1']);
    $course_status->method('getCacheMaxAge')->willReturn(-1);

    $training_manager = $this->createMock(TrainingManager::class);
    $training_manager->expects(self::once())
      ->method('loadCourseStatus')
      ->with($course, $account, ['current' => TRUE])
      ->willReturn($course_status);
    $training_manager->method('loadLessonStatus')
      ->willReturnCallback(static function (string $course_status_id, string $lesson_id) use ($lesson_status): ?LessonStatusInterface {
        return $lesson_id === 'lesson-1' ? $lesson_status : NULL;
      });
    $training_manager->method('checkActivityAccess')->willReturn(NULL);

    $manager = new LearningDashboardManager(
      NULL,
      $this->createMock(CourseCertificateManagerInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $training_manager,
    );

    $journey = $manager->courseJourney($course, $account);

    self::assertTrue($journey->hasAccess());
    self::assertCount(2, $journey->lessons);
    self::assertSame('finished', $journey->lessons[0]->state);
    self::assertSame('not_started', $journey->lessons[1]->state);
    self::assertNull($journey->currentLessonIndex);
    self::assertNull($journey->currentLessonLabel);
    self::assertSame(1, $journey->completedLessons);
    self::assertSame(50, $journey->progressPercent);
    self::assertInstanceOf(Url::class, $journey->lessons[1]->url);
    self::assertSame('lms.group.answer_form', $journey->lessons[1]->url->getRouteName());
    self::assertSame('lms.course.start', $journey->continueUrl->getRouteName());
    self::assertSame(1, $journey->lessonPosition(0));
    self::assertSame(2, $journey->lessonPosition(1));
    self::assertSame('Redução de danos', $journey->lessonByDelta(1)?->label);
  }

  /**
   * Resolves a course identifier through the ACULTA journey contract.
   *
   * The contract never exposes the Group or LMS entity to its consumers. A
   * non-course group and a missing identifier return NULL instead of a partial
   * journey.
   */
  public function testCourseJourneyReaderResolvesOnlyCourseGroups(): void {
    $account = new UserSession(['uid' => 42]);
    $course = $this->createMock(CourseInterface::class);
    $course->method('id')->willReturn('course-1');
    $course->method('label')->willReturn('Curso');
    $course->method('takeAccess')->with($account)->willReturn(AccessResult::forbidden());
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn(['group:course-1']);
    $course->method('getCacheMaxAge')->willReturn(-1);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(
      static fn(string $id): ?CourseInterface => $id === 'course-1' ? $course : NULL,
    );
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('group')->willReturn($storage);

    $manager = new LearningDashboardManager(
      NULL,
      $this->createMock(CourseCertificateManagerInterface::class),
      $entity_type_manager,
      NULL,
    );

    $journey = $manager->getCourseJourney('course-1', $account);
    self::assertNotNull($journey);
    self::assertFalse($journey->hasAccess());
    self::assertSame('course-1', $journey->courseId);

    self::assertNull($manager->getCourseJourney('missing', $account));
  }

}
