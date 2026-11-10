<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Access\AnswerReviewAccessCheck;
use Drupal\aculta_lms\Group\GroupMembershipReaderInterface;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests contextual access for the educator's answer review workflow.
 */
#[CoversClass(AnswerReviewAccessCheck::class)]
final class AnswerReviewAccessCheckTest extends UnitTestCase {

  /**
   * Allows a grader to review an answer from an active class member.
   */
  public function testCourseGraderCanReviewAnswerFromCurrentClass(): void {
    [$check, $course, $class, $answer, $memberships, $student] = $this->scenario();
    $memberships->expects(self::once())->method('loadByGroup')->with($class)->willReturn([
      $this->relationship($student, 'group_membership'),
    ]);

    self::assertTrue($check->access($course, $class, $answer)->isAllowed());
  }

  /**
   * Denies review when the answer owner is outside the selected class.
   */
  public function testCannotReviewAnswerFromAnotherClass(): void {
    [$check, $course, $class, $answer, $memberships, $student] = $this->scenario();
    $otherStudent = $this->createMock(UserInterface::class);
    $otherStudent->method('id')->willReturn('73');
    $otherStudent->method('isActive')->willReturn(TRUE);
    $memberships->expects(self::once())->method('loadByGroup')->with($class)->willReturn([
      $this->relationship($otherStudent, 'group_membership'),
    ]);

    self::assertFalse($check->access($course, $class, $answer)->isAllowed());
  }

  /**
   * Denies review when the selected class is unrelated to the course.
   */
  public function testCannotReviewCourseAnswerThroughUnrelatedClass(): void {
    [$check, $course, , $answer, $memberships] = $this->scenario();
    $unrelatedClass = $this->group('lms_class', '44');
    $memberships->expects(self::never())->method('loadByGroup');

    self::assertFalse($check->access($course, $unrelatedClass, $answer)->isAllowed());
  }

  /**
   * Denies answers belonging to another course.
   */
  public function testCannotReviewAnswerFromAnotherCourse(): void {
    [$check, $course, $class, $answer, $memberships] = $this->scenario();
    $otherCourse = $this->group('lms_course', '99');
    $otherCourse->method('getRelationships')->willReturn([]);
    $answerCourse = $this->group('lms_course', '100');
    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->method('courseForAnswer')->with($answer)->willReturn($answerCourse);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn('5');
    $check = new AnswerReviewAccessCheck($eligibility, $currentUser, $memberships);
    $memberships->expects(self::never())->method('loadByGroup');

    self::assertFalse($check->access($otherCourse, $class, $answer)->isAllowed());
  }

  /**
   * Creates the baseline answer review fixture.
   */
  private function scenario(): array {
    $course = $this->group('lms_course', '12');
    $class = $this->group('lms_class', '27');
    $link = $this->relationship($class, 'lms_classes');
    $course->method('getRelationships')->with('lms_classes')->willReturn([$link]);
    $answer = $this->createMock(FieldableEntityInterface::class);
    $answer->method('getEntityTypeId')->willReturn('aculta_lms_answer');
    $answer->method('get')->willReturnCallback(
      fn (string $name): FieldItemListInterface => $this->field($name === 'uid' ? 72 : NULL),
    );
    $answer->method('getCacheContexts')->willReturn([]);
    $answer->method('getCacheTags')->willReturn([]);
    $answer->method('getCacheMaxAge')->willReturn(-1);
    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->method('courseForAnswer')->with($answer)->willReturn($course);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn('5');
    $course->method('hasPermission')->with('grade students', $currentUser)->willReturn(TRUE);
    $memberships = $this->createMock(GroupMembershipReaderInterface::class);
    $student = $this->createMock(UserInterface::class);
    $student->method('id')->willReturn('72');
    $student->method('isActive')->willReturn(TRUE);
    return [
      new AnswerReviewAccessCheck($eligibility, $currentUser, $memberships),
      $course,
      $class,
      $answer,
      $memberships,
      $student,
    ];
  }

  /**
   * Creates a Group mock with the requested identity.
   */
  private function group(string $bundle, string $id): GroupInterface {
    $group = $this->createMock(GroupInterface::class);
    $group->method('bundle')->willReturn($bundle);
    $group->method('id')->willReturn($id);
    $group->method('hasPermission')->willReturn(TRUE);
    $group->method('getCacheContexts')->willReturn([]);
    $group->method('getCacheTags')->willReturn([]);
    $group->method('getCacheMaxAge')->willReturn(-1);
    return $group;
  }

  /**
   * Creates a Group membership or class relationship mock.
   */
  private function relationship(object $entity, string $pluginId): GroupRelationshipInterface {
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getEntity')->willReturn($entity);
    $relationship->method('getPluginId')->willReturn($pluginId);
    $relationship->method('getCacheContexts')->willReturn([]);
    $relationship->method('getCacheTags')->willReturn([]);
    $relationship->method('getCacheMaxAge')->willReturn(-1);
    return $relationship;
  }

  /**
   * Creates a field list mock with the requested value.
   */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

}
