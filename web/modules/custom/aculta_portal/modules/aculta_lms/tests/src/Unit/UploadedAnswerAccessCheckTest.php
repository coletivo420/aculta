<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\aculta_lms\Access\UploadedAnswerAccessCheck;
use Drupal\aculta_lms\Account\AccountUserLoaderInterface;
use Drupal\user\UserInterface;
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
    $course_status->method('get')->with(CourseStatusInterface::COURSE_FIELD)->willReturn($this->referenceField($course));
    $lesson_status = $this->createMock(LessonStatusInterface::class);
    $lesson_status->method('getCourseStatus')->willReturn($course_status);
    $lesson_status->method('get')->with('course_status')->willReturn($this->referenceField($course_status));
    $answer = $this->createMock(AnswerInterface::class);
    $this->setCacheability($answer);
    $answer->method('access')->with('see_details', $account, TRUE)->willReturn(AccessResult::allowed());
    $answer->method('getUserId')->willReturn('27');
    $answer->method('get')->with('lesson_status')->willReturn($this->referenceField($lesson_status));

    $result = $this->accessCheck($account)->access($answer, $account);

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
    $course_status->method('get')->with(CourseStatusInterface::COURSE_FIELD)->willReturn($this->referenceField($course));
    $lesson_status = $this->createMock(LessonStatusInterface::class);
    $lesson_status->method('getCourseStatus')->willReturn($course_status);
    $lesson_status->method('get')->with('course_status')->willReturn($this->referenceField($course_status));
    $answer = $this->createMock(AnswerInterface::class);
    $this->setCacheability($answer);
    $answer->method('access')->with('see_details', $account, TRUE)->willReturn(AccessResult::allowed());
    $answer->method('getUserId')->willReturn('27');
    $answer->method('get')->with('lesson_status')->willReturn($this->referenceField($lesson_status));

    self::assertTrue($this->accessCheck($account)->access($answer, $account)->isAllowed());
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

    self::assertTrue($this->accessCheck($account)->access($answer, $account)->isAllowed());
  }

  /** @covers ::access */
  public function testInactiveSessionAccountIsDeniedBeforeLmsAccess(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('27');
    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(FALSE);
    $this->setCacheability($user);
    $loader = $this->createMock(AccountUserLoaderInterface::class);
    $loader->expects(self::once())->method('load')->with($account)->willReturn($user);
    $answer = $this->createMock(AnswerInterface::class);
    $this->setCacheability($answer);
    $answer->expects(self::never())->method('access');

    $result = (new UploadedAnswerAccessCheck($loader))->access($answer, $account);

    self::assertFalse($result->isAllowed());
    self::assertContains('user', $result->getCacheContexts());
    self::assertContains('user:27', $result->getCacheTags());
  }

  private function accessCheck(AccountInterface $account): UploadedAnswerAccessCheck {
    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(TRUE);
    $this->setCacheability($user);
    $loader = $this->createMock(AccountUserLoaderInterface::class);
    $loader->method('load')->with($account)->willReturn($user);
    return new UploadedAnswerAccessCheck($loader);
  }

  private function setCacheability(AnswerInterface|CourseInterface|UserInterface $entity): void {
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn($entity instanceof UserInterface ? ['user:27'] : []);
    $entity->method('getCacheMaxAge')->willReturn(-1);
  }

  /**
   * Creates a reference field test double exposing its referenced entity.
   */
  private function referenceField(EntityInterface $entity): EntityReferenceFieldItemListInterface {
    $field = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $field->method('__get')->with('entity')->willReturn($entity);
    return $field;
  }

}
