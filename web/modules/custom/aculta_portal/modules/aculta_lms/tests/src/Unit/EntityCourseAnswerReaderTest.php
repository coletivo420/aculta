<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\aculta_lms\Learning\EntityCourseAnswerReader;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Testa a leitura de respostas de curso a partir das entidades.
 */
#[Group('aculta_lms')]
final class EntityCourseAnswerReaderTest extends UnitTestCase {

  /**
   * Confirma que mapeia as respostas e as ordena pelo ID.
   */
  public function testMapsAnswersAndSortsById(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())
      ->method('loadByProperties')
      ->with(['activity_id' => 22, 'uid' => 7])
      ->willReturn([
        $this->answerEntity(9, 22, 7, CourseAnswer::STATE_GRADED, '75'),
        $this->answerEntity(4, 22, 7, CourseAnswer::STATE_SUBMITTED, NULL),
      ]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::once())->method('getStorage')->with('aculta_lms_answer')->willReturn($storage);

    $answers = (new EntityCourseAnswerReader($manager))->forStudent(22, 7);

    self::assertSame([4, 9], array_map(static fn(CourseAnswer $answer): int => $answer->id, $answers));
    self::assertSame(CourseAnswer::STATE_SUBMITTED, $answers[0]->state);
    self::assertNull($answers[0]->score);
    self::assertSame(75.0, $answers[1]->score);
  }

  /**
   * Confirma que não devolve respostas quando o storage está vazio.
   */
  public function testReturnsNoAnswersWhenStorageIsEmpty(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_answer')->willReturn($storage);

    self::assertSame([], (new EntityCourseAnswerReader($manager))->forStudent(22, 7));
  }

  /**
   * Cria uma entidade de resposta simulada.
   *
   * @param int $id
   *   ID da resposta.
   * @param int $activityId
   *   ID da atividade.
   * @param int $uid
   *   ID da conta da pessoa.
   * @param string $state
   *   Estado da resposta.
   * @param mixed $score
   *   Nota da resposta, ou NULL.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Entidade de resposta simulada.
   */
  private function answerEntity(int $id, int $activityId, int $uid, string $state, mixed $score): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn($id);
    $entity->method('get')->willReturnCallback(function (string $field) use ($activityId, $uid, $state, $score): FieldItemListInterface {
      $value = match ($field) {
        'activity_id' => $activityId,
        'uid' => $uid,
        'state' => $state,
        'score' => $score,
        default => throw new \LogicException(sprintf('Unexpected answer field "%s".', $field)),
      };
      $field_list = $this->createMock(FieldItemListInterface::class);
      $field_list->method('getValue')->willReturn($value === NULL ? [] : [['value' => $value]]);
      return $field_list;
    });
    return $entity;
  }

}
