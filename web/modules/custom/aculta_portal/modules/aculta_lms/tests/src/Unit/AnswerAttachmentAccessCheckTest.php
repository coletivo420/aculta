<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Access\AnswerAttachmentAccessCheck;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests per-request authorization of ACULTA answer file references. */
#[CoversClass(AnswerAttachmentAccessCheck::class)]
final class AnswerAttachmentAccessCheckTest extends UnitTestCase {

  /**
   * The enrolled owner can download a permanent file from their answer. */
  public function testEnrolledOwnerCanDownload(): void {
    [$answer, $account, $user, $activity, $file, $course] = $this->entities(27, 27, FALSE);
    [$check, $eligibility] = $this->checker($answer, $user, $activity, $file, $course);
    $eligibility->expects(self::once())->method('hasCurrentEnrollment')->with($course, $user)->willReturn(TRUE);

    self::assertTrue($check->access($answer, $account)->isAllowed());
  }

  /**
   * A grader can download another learner's file in the same course. */
  public function testCourseGraderCanDownload(): void {
    [$answer, $account, $user, $activity, $file, $course] = $this->entities(27, 31, TRUE);
    [$check, $eligibility] = $this->checker($answer, $user, $activity, $file, $course);
    $eligibility->expects(self::never())->method('hasCurrentEnrollment');

    self::assertTrue($check->access($answer, $account)->isAllowed());
  }

  /**
   * A former course member cannot download their attachment. */
  public function testOwnerWithoutCurrentEnrollmentIsDenied(): void {
    [$answer, $account, $user, $activity, $file, $course] = $this->entities(27, 27, FALSE);
    [$check, $eligibility] = $this->checker($answer, $user, $activity, $file, $course);
    $eligibility->expects(self::once())->method('hasCurrentEnrollment')->with($course, $user)->willReturn(FALSE);

    $result = $check->access($answer, $account);
    self::assertFalse($result->isAllowed());
    self::assertInstanceOf(CacheableDependencyInterface::class, $result);
    self::assertContains('group_relationship_list:plugin:group_membership:group:89', $result->getCacheTags());
  }

  /**
   * Builds all entity doubles for one answer access check.
   *
   * @return array{0: \Drupal\Core\Entity\FieldableEntityInterface&MockObject, 1: \Drupal\Core\Session\AccountInterface&MockObject, 2: \Drupal\user\UserInterface&MockObject, 3: \Drupal\Core\Entity\FieldableEntityInterface&MockObject, 4: \Drupal\file\FileInterface&MockObject, 5: \Drupal\group\Entity\GroupInterface&MockObject}
   *   Answer, account, owner, activity, file and course fixtures.
   */
  private function entities(int $ownerId, int $accountId, bool $canGrade): array {
    $answer = $this->cacheableEntity('aculta_lms_answer', [
      'uid' => $ownerId,
      'state' => 'submitted',
      'activity_id' => 19,
      'answer_data' => '{"file_upload":{"fids":[71]}}',
    ]);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn((string) $accountId);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(TRUE);
    $user->method('getCacheContexts')->willReturn([]);
    $user->method('getCacheTags')->willReturn([]);
    $user->method('getCacheMaxAge')->willReturn(-1);
    $activity = $this->cacheableEntity('aculta_lms_activity', ['type' => 'file_upload']);
    $file = $this->createMock(FileInterface::class);
    $file->method('isTemporary')->willReturn(FALSE);
    $file->method('getFileUri')->willReturn('private://lms_file_upload/answer.txt');
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheTags')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasPermission')->with('grade students', $account)->willReturn($canGrade);
    $course->method('id')->willReturn('12');
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    return [$answer, $account, $user, $activity, $file, $course];
  }

  /**
   * Constructs the checker and its storage doubles.
   *
   * @return array{0: \Drupal\aculta_lms\Access\AnswerAttachmentAccessCheck, 1: \Drupal\aculta_lms\Notice\LearningEligibilityInterface&MockObject}
   *   The access checker and the enrollment eligibility mock.
   */
  private function checker(EntityInterface $answer, UserInterface $user, EntityInterface $activity, FileInterface $file, GroupInterface $course): array {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnMap([
      [27, $user],
      [19, $activity],
      [71, $file],
    ]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);
    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->method('courseForAnswer')->with($answer)->willReturn($course);
    $eligibility->method('courseEnrollmentCacheTags')->willReturn([
      'group_relationship_list:plugin:lms_classes:group:12',
      'group_relationship_list:plugin:group_membership:group:89',
    ]);
    return [new AnswerAttachmentAccessCheck($entityTypeManager, $eligibility), $eligibility];
  }

  /**
   * Creates an entity mock with field values and cache metadata.
   *
   * @param array<string, int|string> $values
   *   Entity field values used by the access check.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface&MockObject
   *   The cacheable entity mock.
   */
  private function cacheableEntity(string $type, array $values): FieldableEntityInterface {
    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($type);
    $entity->method('get')->willReturnCallback(fn(string $name): FieldItemListInterface => $this->field($values[$name] ?? NULL));
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
    return $entity;
  }

  /**
   * Creates a field list double with a value.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>&MockObject
   *   The field item list mock.
   */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

}
