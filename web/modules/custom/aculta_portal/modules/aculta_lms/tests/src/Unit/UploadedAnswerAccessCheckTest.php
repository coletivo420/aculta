<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\aculta_lms\Access\UploadedAnswerAccessCheck;
use Drupal\Tests\UnitTestCase;

/** @coversDefaultClass \Drupal\aculta_lms\Access\UploadedAnswerAccessCheck */
final class UploadedAnswerAccessCheckTest extends UnitTestCase {

  /** @covers ::access */
  public function testOwnerNeedsCurrentCourseAccess(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('27');
    $course = $this->createMock(CourseInterface::class);
    $this->setCacheability($course);
    $course->expects(self::once())->method('takeAccess')->with($account)->willReturn(AccessResult::forbidden());
    $course_status = $this->createMock(CourseStatusInterface::class);
    $course_status->method('getCourse')->willReturn($course);
    $lesson_status = $this->createMock(LessonStatusInterface::class);
    $lesson_status->method('getCourseStatus')->willReturn($course_status);
    $answer = $this->createMock(AnswerInterface::class);
    $this->setCacheability($answer);
    $answer->method('access')->with('see_details', $account, TRUE)->willReturn(AccessResult::allowed());
    $answer->method('getUserId')->willReturn('27');
    $answer->method('getLessonStatus')->willReturn($lesson_status);

    $result = (new UploadedAnswerAccessCheck())->access($answer, $account);

    self::assertFalse($result->isAllowed());
  }

  /** @covers ::access */
  public function testEnrolledOwnerCanAccessFile(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('27');
    $course = $this->createMock(CourseInterface::class);
    $this->setCacheability($course);
    $course->method('takeAccess')->with($account)->willReturn(AccessResult::allowed());
    $course_status = $this->createMock(CourseStatusInterface::class);
    $course_status->method('getCourse')->willReturn($course);
    $lesson_status = $this->createMock(LessonStatusInterface::class);
    $lesson_status->method('getCourseStatus')->willReturn($course_status);
    $answer = $this->createMock(AnswerInterface::class);
    $this->setCacheability($answer);
    $answer->method('access')->with('see_details', $account, TRUE)->willReturn(AccessResult::allowed());
    $answer->method('getUserId')->willReturn('27');
    $answer->method('getLessonStatus')->willReturn($lesson_status);

    self::assertTrue((new UploadedAnswerAccessCheck())->access($answer, $account)->isAllowed());
  }

  /** @covers ::access */
  public function testEducatorAccessUsesLmsAnswerPermission(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('31');
    $answer = $this->createMock(AnswerInterface::class);
    $this->setCacheability($answer);
    $answer->expects(self::once())->method('access')->with('see_details', $account, TRUE)->willReturn(AccessResult::allowed());
    $answer->method('getUserId')->willReturn('27');
    $answer->expects(self::never())->method('getLessonStatus');

    self::assertTrue((new UploadedAnswerAccessCheck())->access($answer, $account)->isAllowed());
  }

  private function setCacheability(AnswerInterface|CourseInterface $entity): void {
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
  }

}
