<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Access\CourseLessonPreviewAccessCheck;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests private lesson preview authorization.
 *
 */
#[CoversClass(CourseLessonPreviewAccessCheck::class)]
final class CourseLessonPreviewAccessCheckTest extends UnitTestCase {

  /**
   */
  public function testAllowsMatchingLessonThroughEducatorPolicy(): void {
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('17');
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('getEntityTypeId')->willReturn('aculta_lms_lesson');
    $lesson->method('get')->with('course_id')->willReturn(self::field(17));
    $lesson->method('getCacheContexts')->willReturn([]);
    $lesson->method('getCacheTags')->willReturn([]);
    $lesson->method('getCacheMaxAge')->willReturn(-1);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->with('administer lms')->willReturn(TRUE);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $access = new CourseAuthoringAccessCheck($account, $manager);

    $result = (new CourseLessonPreviewAccessCheck($access))->access($course, $lesson);

    self::assertTrue($result->isAllowed());
  }

  /**
   */
  public function testDeniesUserWithoutEducatorPermission(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->with('administer lms')->willReturn(FALSE);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('17');
    $course->method('hasPermission')->with('grade students', $account)->willReturn(FALSE);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('getEntityTypeId')->willReturn('aculta_lms_lesson');
    $lesson->method('get')->with('course_id')->willReturn(self::field(17));
    $lesson->method('getCacheContexts')->willReturn([]);
    $lesson->method('getCacheTags')->willReturn([]);
    $lesson->method('getCacheMaxAge')->willReturn(-1);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $access = new CourseAuthoringAccessCheck($account, $manager);

    $result = (new CourseLessonPreviewAccessCheck($access))->access($course, $lesson);

    self::assertFalse($result->isAllowed());
  }

  /**
   * Creates a field item fixture for the lesson's course reference.
   */
  private static function field(int $value): FieldItemListInterface {
    return new class($value) extends FieldItemList {

      public function __construct(protected readonly int $fieldValue) {}

      /**
       * Returns the fixture field value.
       */
      public function __get($property_name) {
        return $property_name === 'value' ? $this->fieldValue : NULL;
      }

    };
  }

}
