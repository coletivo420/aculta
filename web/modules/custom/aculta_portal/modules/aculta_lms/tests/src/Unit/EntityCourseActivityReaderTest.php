<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Learning\CourseActivity;
use Drupal\aculta_lms\Learning\EntityCourseActivityReader;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Testa a leitura e a ordenação de atividades a partir de entidades.
 */
#[Group('aculta_lms')]
final class EntityCourseActivityReaderTest extends UnitTestCase {

  /**
   * Verifica o mapeamento e a ordenação das atividades da lição.
   */
  public function testMapsAndOrdersActivities(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())
      ->method('loadByProperties')
      ->with(['lesson_id' => 11])
      ->willReturn([
        $this->activityEntity(8, 3, 'fill_blanks', TRUE, 2),
        $this->activityEntity(9, 3, 'true_false', FALSE, 0),
      ]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::once())->method('getStorage')->with('aculta_lms_activity')->willReturn($storage);

    $activities = (new EntityCourseActivityReader($manager))->forLesson(11);

    self::assertSame([9, 8], array_map(static fn(CourseActivity $activity): int => $activity->id, $activities));
    self::assertSame(['true_false', 'fill_blanks'], array_map(static fn(CourseActivity $activity): string => $activity->type, $activities));
    self::assertFalse($activities[0]->graded);
    self::assertTrue($activities[1]->graded);
  }

  /**
   * Verifica que a lista fica vazia quando o armazenamento não tem itens.
   */
  public function testReturnsNoActivitiesWhenStorageIsEmpty(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_activity')->willReturn($storage);

    self::assertSame([], (new EntityCourseActivityReader($manager))->forLesson(11));
  }

  /**
   * Cria uma entidade de atividade simulada.
   *
   * @param int $id
   *   ID da atividade.
   * @param int $lessonId
   *   ID da lição associada.
   * @param string $type
   *   Tipo da atividade.
   * @param bool $graded
   *   Indica se a atividade é avaliada.
   * @param int $weight
   *   Peso de ordenação.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Entidade simulada.
   */
  private function activityEntity(int $id, int $lessonId, string $type, bool $graded, int $weight): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn($id);
    $entity->method('get')->willReturnCallback(function (string $field) use ($lessonId, $type, $graded, $weight): FieldItemListInterface {
      $value = match ($field) {
        'lesson_id' => $lessonId,
        'type' => $type,
        'graded' => $graded,
        'weight' => $weight,
      };
      $field_list = $this->createMock(FieldItemListInterface::class);
      $field_list->method('getValue')->willReturn([['value' => $value]]);
      return $field_list;
    });
    return $entity;
  }

}
