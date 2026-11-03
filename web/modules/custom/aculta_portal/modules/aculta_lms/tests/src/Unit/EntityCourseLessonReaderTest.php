<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\aculta_lms\Learning\CourseLesson;
use Drupal\aculta_lms\Learning\EntityCourseLessonReader;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('aculta_lms')]
final class EntityCourseLessonReaderTest extends UnitTestCase {

  public function testMapsLessonsAndOrdersByWeightThenId(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())
      ->method('loadByProperties')
      ->with(['course_id' => 42])
      ->willReturn([
        $this->lessonEntity(8, 2, 'Terceira', FALSE, 4),
        $this->lessonEntity(9, 2, 'Primeira', TRUE, 1),
        $this->lessonEntity(3, 2, 'Segunda', TRUE, 1),
      ]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::once())->method('getStorage')->with('aculta_lms_lesson')->willReturn($storage);

    $lessons = (new EntityCourseLessonReader($manager))->forCourse(42);

    self::assertSame([3, 9, 8], array_map(static fn(CourseLesson $lesson): int => $lesson->id, $lessons));
    self::assertSame(['Segunda', 'Primeira', 'Terceira'], array_map(static fn(CourseLesson $lesson): string => $lesson->title, $lessons));
    self::assertFalse($lessons[2]->published);
  }

  public function testReturnsNoLessonsWhenStorageIsEmpty(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_lesson')->willReturn($storage);

    self::assertSame([], (new EntityCourseLessonReader($manager))->forCourse(42));
  }

  private function lessonEntity(int $id, int $courseId, string $title, bool $published, int $weight): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn($id);
    $entity->method('get')->willReturnCallback(function (string $field) use ($courseId, $title, $published, $weight): FieldItemListInterface {
      $value = match ($field) {
        'course_id' => $courseId,
        'title' => $title,
        'published' => $published,
        'weight' => $weight,
      };
      $field_list = $this->createMock(FieldItemListInterface::class);
      $field_list->method('getValue')->willReturn([['value' => $value]]);
      return $field_list;
    });
    return $entity;
  }

}
