<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonInterface;
use Drupal\lms\Entity\LessonStatus;
use Drupal\aculta_lms\AttemptArchiveManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that changed responses and evaluations preserve the old LMS state.
 */
#[Group('aculta_lms')]
final class AttemptArchiveManagerTest extends UnitTestCase {

  public function testReevaluationArchivesPreviousAnswerAndScore(): void {
    $original = $this->buildAnswer(['answer' => 'Resposta original'], 4, TRUE);
    $updated = $this->buildAnswer(['answer' => 'Resposta original'], 2, TRUE);
    $archive_entity = $this->createMock(EntityInterface::class);
    $archive_entity->expects(self::once())->method('save');

    $created_values = NULL;
    $archive_storage = $this->createMock(EntityStorageInterface::class);
    $archive_storage->expects(self::once())->method('create')->willReturnCallback(
      function (array $values) use (&$created_values, $archive_entity): EntityInterface {
        $created_values = $values;
        return $archive_entity;
      },
    );
    $answer_storage = $this->createMock(EntityStorageInterface::class);
    $answer_storage->expects(self::once())->method('loadUnchanged')->with('21')->willReturn($original);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['lms_answer', $answer_storage],
      ['aculta_lms_attempt_archive', $archive_storage],
    ]);

    $manager = new AttemptArchiveManager(
      $entity_type_manager,
      $this->createMock(AccountInterface::class),
      $this->buildTime(),
    );
    $manager->archivePreviousAnswer($updated);

    self::assertSame('evaluation_changed', $created_values['event_type']);
    self::assertSame(4, $created_values['score']);
    self::assertSame(4, $created_values['max_score']);
    self::assertSame(['answer' => 'Resposta original'], json_decode($created_values['answer_data'], TRUE));
  }

  public function testAnswerEditArchivesPriorText(): void {
    $original = $this->buildAnswer(['answer' => 'Versão anterior'], 3, TRUE);
    $updated = $this->buildAnswer(['answer' => 'Nova versão'], 3, TRUE);
    $archive_entity = $this->createMock(EntityInterface::class);
    $archive_entity->expects(self::once())->method('save');

    $created_values = NULL;
    $archive_storage = $this->createMock(EntityStorageInterface::class);
    $archive_storage->expects(self::once())->method('create')->willReturnCallback(
      function (array $values) use (&$created_values, $archive_entity): EntityInterface {
        $created_values = $values;
        return $archive_entity;
      },
    );
    $answer_storage = $this->createMock(EntityStorageInterface::class);
    $answer_storage->expects(self::once())->method('loadUnchanged')->with('21')->willReturn($original);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['lms_answer', $answer_storage],
      ['aculta_lms_attempt_archive', $archive_storage],
    ]);

    $manager = new AttemptArchiveManager(
      $entity_type_manager,
      $this->createMock(AccountInterface::class),
      $this->buildTime(),
    );
    $manager->archivePreviousAnswer($updated);

    self::assertSame('answer_changed', $created_values['event_type']);
    self::assertSame(['answer' => 'Versão anterior'], json_decode($created_values['answer_data'], TRUE));
  }

  public function testRestartArchivesDeletedAnswerWithTimestamps(): void {
    $answer = $this->buildAnswer(['answer' => 'Resposta da tentativa anterior'], 2, FALSE);
    $archive_entity = $this->createMock(EntityInterface::class);
    $archive_entity->expects(self::once())->method('save');
    $created_values = NULL;
    $archive_storage = $this->createMock(EntityStorageInterface::class);
    $archive_storage->expects(self::once())->method('create')->willReturnCallback(
      function (array $values) use (&$created_values, $archive_entity): EntityInterface {
        $created_values = $values;
        return $archive_entity;
      },
    );
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::once())->method('getStorage')
      ->with('aculta_lms_attempt_archive')->willReturn($archive_storage);

    $manager = new AttemptArchiveManager(
      $entity_type_manager,
      $this->createMock(AccountInterface::class),
      $this->buildTime(),
    );
    $manager->archiveAnswer($answer);

    self::assertSame('deleted', $created_values['event_type']);
    self::assertSame('answer-uuid-21', $created_values['answer_uuid']);
    self::assertSame(1200, $created_values['answered']);
    self::assertSame(2000, $created_values['archived']);
    self::assertFalse($created_values['evaluated']);
  }

  public function testUnchangedAnswerDoesNotCreateDuplicateArchive(): void {
    $answer = $this->buildAnswer(['answer' => 'Sem alterações'], 4, TRUE);
    $archive_storage = $this->createMock(EntityStorageInterface::class);
    $archive_storage->expects(self::never())->method('create');
    $answer_storage = $this->createMock(EntityStorageInterface::class);
    $answer_storage->expects(self::once())->method('loadUnchanged')->with('21')->willReturn($answer);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['lms_answer', $answer_storage],
      ['aculta_lms_attempt_archive', $archive_storage],
    ]);
    $manager = new AttemptArchiveManager(
      $entity_type_manager,
      $this->createMock(AccountInterface::class),
      $this->buildTime(),
    );

    $manager->archivePreviousAnswer($answer);
  }

  public function testStaleReferencesSkipArchivingAndHistoryQuery(): void {
    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('hasField')->willReturnMap([
      ['lesson_status', TRUE],
      ['activity_revision', TRUE],
    ]);
    $answer->method('get')->willReturnMap([
      ['lesson_status', $this->referenceField(NULL)],
      ['activity_revision', $this->referenceField(NULL)],
    ]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::never())->method('getStorage');
    $manager = new AttemptArchiveManager(
      $entity_type_manager,
      $this->createMock(AccountInterface::class),
      $this->buildTime(),
    );

    $manager->archiveAnswer($answer);
    self::assertSame([], $manager->loadPreviousAttempts($answer, new \Drupal\Core\Cache\CacheableMetadata()));
  }

  private function buildAnswer(array $data, int $score, bool $evaluated): AnswerInterface {
    $group = $this->createMock(CourseInterface::class);
    $group->method('id')->willReturn('course-7');
    $course_status = $this->createMock(CourseStatusInterface::class);
    $course_status->method('id')->willReturn('14');
    $course_status->method('hasField')->with('course')->willReturn(TRUE);
    $course_status->method('get')->with('course')->willReturn($this->referenceField($group));
    $course_status->method('getCourseId')->willReturn('course-7');
    $lesson = $this->createMock(LessonInterface::class);
    $lesson->method('label')->willReturn('Aula 1');
    $lesson->method('get')->with(LessonInterface::ACTIVITIES)->willReturn($this->buildActivityList());
    $lesson_status = $this->createMock(LessonStatus::class);
    $lesson_status->method('id')->willReturn('18');
    $lesson_status->method('hasField')->willReturnMap([
      ['course_status', TRUE],
      ['lesson_revision', TRUE],
    ]);
    $lesson_status->method('get')->willReturnMap([
      ['course_status', $this->referenceField($course_status)],
      ['lesson_revision', $this->referenceField($lesson)],
    ]);
    $lesson_status->method('getLessonId')->willReturn('3');
    $lesson_status->method('getCreatedTime')->willReturn(1000);
    $lesson_status->method('getFinishedTime')->willReturn(0);
    $activity = $this->createMock(ActivityInterface::class);
    $activity->method('id')->willReturn('5');
    $activity->method('label')->willReturn('Questão discursiva');

    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('isNew')->willReturn(FALSE);
    $answer->method('id')->willReturn('21');
    $answer->method('getUserId')->willReturn('9');
    $answer->method('uuid')->willReturn('answer-uuid-21');
    $answer->method('getData')->willReturn($data);
    $answer->method('getScore')->willReturn($score);
    $answer->method('isEvaluated')->willReturn($evaluated);
    $answer->method('hasField')->willReturnCallback(static fn (string $field): bool => $field !== 'comments');
    $answer->method('get')->willReturnMap([
      ['lesson_status', $this->referenceField($lesson_status)],
      ['activity_revision', $this->referenceField($activity)],
      ['comments', $this->referenceField(NULL)],
    ]);
    $answer->method('getChangedTime')->willReturn(1200);
    return $answer;
  }

  private function referenceField(?object $entity): FieldItemListInterface {
    return new class($entity) extends FieldItemList {

      public function __construct(private readonly ?object $referencedEntity) {}

      public function __get($property_name) {
        if ($property_name === 'entity') {
          return $this->referencedEntity;
        }
        return parent::__get($property_name);
      }

    };
  }

  private function buildTime(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(2000);
    return $time;
  }

  private function buildActivityList(): FieldItemListInterface {
    $activity_item = new class {

      public string $target_id = '5';

      public function getMaxScore(): int {
        return 4;
      }

    };
    return new class($activity_item) extends FieldItemList {

      public function __construct(object $activity_item) {
        parent::__construct(DataDefinition::create('field_item_list'));
        $this->list = [$activity_item];
      }

    };
  }

}
