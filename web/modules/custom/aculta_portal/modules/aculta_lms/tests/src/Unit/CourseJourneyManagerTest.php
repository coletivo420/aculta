<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\Learning\CourseLesson;
use Drupal\aculta_lms\Learning\CourseLessonReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\UserSession;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ACULTA-backed course journey projection.
 */
#[CoversClass(LearningDashboardManager::class)]
#[Group('aculta_lms')]
final class CourseJourneyManagerTest extends UnitTestCase {

  /**
   * Checks that only published course lessons appear in the journey.
   */
  public function testJourneyListsOwnPublishedLessonsWithoutInventingLessonProgress(): void {
    $account = new UserSession(['uid' => 42]);
    $course = $this->course($account, '1');
    $progressReader = $this->createMock(CourseProgressReaderInterface::class);
    $progressReader->expects(self::once())->method('current')->with($course, $account)->willReturn(
      new CourseProgress(CourseProgress::STATUS_PROGRESS, FALSE, NULL),
    );
    $lessonReader = $this->createMock(CourseLessonReaderInterface::class);
    $lessonReader->expects(self::once())->method('forCourse')->with(1)->willReturn([
      new CourseLesson(1, 1, 'Proibicionismo', TRUE, 0),
      new CourseLesson(2, 1, 'Redução de danos', TRUE, 1),
      new CourseLesson(3, 1, 'Rascunho', FALSE, 2),
    ]);
    $manager = new LearningDashboardManager(
      NULL,
      $this->createMock(CourseCertificateManagerInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $progressReader,
      $lessonReader,
    );

    $journey = $manager->courseJourney($course, $account);

    self::assertTrue($journey->hasAccess());
    self::assertCount(2, $journey->lessons);
    self::assertSame('not_started', $journey->lessons[0]->state);
    self::assertNull($journey->currentLessonIndex);
    self::assertNull($journey->currentLessonLabel);
    self::assertSame(0, $journey->completedLessons);
    self::assertSame(0, $journey->progressPercent);
    $lessonUrl = $journey->lessons[1]->url;
    self::assertInstanceOf(Url::class, $lessonUrl);
    self::assertSame('aculta_lms.lesson_view', $lessonUrl->getRouteName());
    self::assertSame(['group' => '1', 'lesson_delta' => 1], $lessonUrl->getRouteParameters());
    self::assertInstanceOf(Url::class, $journey->continueUrl);
    self::assertSame('entity.group.canonical', $journey->continueUrl->getRouteName());
    self::assertSame(['group' => '1'], $journey->continueUrl->getRouteParameters());
    self::assertSame(1, $journey->lessonPosition(0));
    self::assertSame(2, $journey->lessonPosition(1));
    self::assertSame('Redução de danos', $journey->lessonByDelta(1)?->label);
  }

  /**
   * Checks course lookup and take permission behavior.
   */
  public function testCourseJourneyReaderResolvesOnlyCourseGroupsAndChecksTakePermission(): void {
    $account = new UserSession(['uid' => 42]);
    $course = $this->course($account, 'course-1', FALSE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(static fn(string $id): ?GroupInterface => $id === 'course-1' ? $course : NULL);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('group')->willReturn($storage);
    $manager = new LearningDashboardManager(
      NULL,
      $this->createMock(CourseCertificateManagerInterface::class),
      $entityTypeManager,
      $this->createMock(CourseProgressReaderInterface::class),
      $this->createMock(CourseLessonReaderInterface::class),
    );

    $journey = $manager->getCourseJourney('course-1', $account);
    self::assertNotNull($journey);
    self::assertFalse($journey->hasAccess());
    self::assertSame('course-1', $journey->courseId);
    self::assertNull($manager->getCourseJourney('missing', $account));
  }

  /**
   * Creates a course entity double with the requested access result.
   */
  private function course(UserSession $account, string $id, bool $takePermission = TRUE): GroupInterface {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn($id);
    $course->method('label')->willReturn('Curso');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('isPublished')->willReturn(TRUE);
    $course->method('hasPermission')->with('take course', $account)->willReturn($takePermission);
    $course->method('hasField')->with('field_description')->willReturn(FALSE);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn(['group:' . $id]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    return $course;
  }

}
