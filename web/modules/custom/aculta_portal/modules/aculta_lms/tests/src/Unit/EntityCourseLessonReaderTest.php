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

/**
 * Leitura das aulas de um curso a partir das entidades de conteúdo.
 */
#[Group('aculta_lms')]
final class EntityCourseLessonReaderTest extends UnitTestCase {

  /**
   * Confirma que as aulas são mapeadas e ordenadas por peso e depois por ID.
   */
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

  /**
   * Confirma que nenhuma aula é retornada quando o armazenamento está vazio.
   */
  public function testReturnsNoLessonsWhenStorageIsEmpty(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_lesson')->willReturn($storage);

    self::assertSame([], (new EntityCourseLessonReader($manager))->forCourse(42));
  }

  /**
   * Cria uma entidade de aula simulada com os campos informados.
   *
   * @param int $id
   *   Identificador da aula.
   * @param int $courseId
   *   Identificador do curso da aula.
   * @param string $title
   *   Título da aula.
   * @param bool $published
   *   Se a aula está publicada.
   * @param int $weight
   *   Peso de ordenação da aula.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Entidade de aula simulada.
   */
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
