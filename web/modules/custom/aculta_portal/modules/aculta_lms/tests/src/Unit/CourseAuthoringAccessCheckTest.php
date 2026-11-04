<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests educator authorization for course authoring.
 *
 * @coversDefaultClass \Drupal\aculta_lms\Access\CourseAuthoringAccessCheck
 */
final class CourseAuthoringAccessCheckTest extends UnitTestCase {

  /**
   * @covers ::access
   */
  public function testCourseEducatorCanManageItsCurriculum(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('15');
    $this->makeCacheable($course);
    $course->method('hasPermission')->with('grade students', $account)->willReturn(TRUE);

    self::assertTrue((new CourseAuthoringAccessCheck($account, $this->createMock(EntityTypeManagerInterface::class)))->access($course)->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testEducatorCannotManageAnotherCoursesLesson(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('15');
    $this->makeCacheable($course);
    $course->method('hasPermission')->willReturn(TRUE);
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('getEntityTypeId')->willReturn('aculta_lms_lesson');
    $this->makeCacheable($lesson);
    $lesson->method('get')->with('course_id')->willReturn((object) ['value' => 16]);

    self::assertFalse((new CourseAuthoringAccessCheck($account, $this->createMock(EntityTypeManagerInterface::class)))->access($course, $lesson)->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testNonCourseGroupIsDenied(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_class');
    $this->makeCacheable($course);

    self::assertFalse((new CourseAuthoringAccessCheck($account, $this->createMock(EntityTypeManagerInterface::class)))->access($course)->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testActivityWithAnswersCannotBeEditedInPlace(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('15');
    $this->makeCacheable($course);
    $course->method('hasPermission')->willReturn(TRUE);
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('getEntityTypeId')->willReturn('aculta_lms_lesson');
    $lesson->method('id')->willReturn('25');
    $this->makeCacheable($lesson);
    $lesson->method('get')->with('course_id')->willReturn((object) ['value' => 15]);
    $activity = $this->createMock(ContentEntityInterface::class);
    $activity->method('getEntityTypeId')->willReturn('aculta_lms_activity');
    $activity->method('id')->willReturn('35');
    $this->makeCacheable($activity);
    $activity->method('get')->with('lesson_id')->willReturn((object) ['value' => 25]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(1);
    $answerStorage = $this->createMock(EntityStorageInterface::class);
    $answerStorage->method('getQuery')->willReturn($query);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('aculta_lms_answer')->willReturn($answerStorage);

    $result = (new CourseAuthoringAccessCheck($account, $entityTypeManager))->access($course, $lesson, $activity);
    self::assertFalse($result->isAllowed());
    self::assertSame(0, $result->getCacheMaxAge());
  }

  /**
   * Adds cacheability methods to an entity mock.
   */
  private function makeCacheable(ContentEntityInterface $entity): void {
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
  }

}
