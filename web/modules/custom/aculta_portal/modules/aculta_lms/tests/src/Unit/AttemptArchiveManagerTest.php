<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_lms\AttemptArchiveManager;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Field\FieldItemInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests that ACULTA grading changes preserve prior attempts. */
#[Group('aculta_lms')]
final class AttemptArchiveManagerTest extends UnitTestCase {

  /**
   * Valores capturados na criação da entidade de arquivo.
   *
   * @var array<array-key, mixed>
   */
  private array $createdValues = [];

  /**
   * Confirma que a reavaliação arquiva a resposta anterior e a nota exata.
   */
  public function testReevaluationArchivesPreviousAnswerAndExactScore(): void {
    $original = $this->buildAnswer(['answer' => 'Resposta original'], '80.25', 'graded');
    $updated = $this->buildAnswer(['answer' => 'Resposta original'], '90', 'graded');
    [$manager, $archive_entity] = $this->managerForArchive($original);

    $manager->archivePreviousAnswer($updated);

    self::assertSame('evaluation_changed', $this->createdValues['event_type']);
    self::assertSame(80, $this->createdValues['score']);
    self::assertSame(80.25, $this->createdValues['score_precise']);
    self::assertSame(100, $this->createdValues['max_score']);
    self::assertIsString($this->createdValues['answer_data']);
    self::assertSame(['answer' => 'Resposta original'], json_decode($this->createdValues['answer_data'], TRUE));
    self::assertSame('', $this->createdValues['course_status_id']);
    self::assertSame('', $this->createdValues['lesson_status_id']);
    $archive_entity->expects(self::never())->method('delete');
  }

  /**
   * Confirma que excluir uma tentativa arquiva o payload imutável e o contexto.
   */
  public function testDeletingAttemptArchivesItsImmutablePayloadAndContext(): void {
    $answer = $this->buildAnswer(['answer' => 'Resposta da tentativa'], NULL, 'submitted');
    [$manager] = $this->managerForArchive();

    $manager->archiveAnswer($answer);

    self::assertSame('deleted', $this->createdValues['event_type']);
    self::assertSame('answer-uuid-21', $this->createdValues['answer_uuid']);
    self::assertSame('7', $this->createdValues['group_id']);
    self::assertSame('3', $this->createdValues['lesson_id']);
    self::assertSame('Aula 1', $this->createdValues['lesson_label']);
    self::assertSame('free_text', $this->createdValues['activity_label']);
    self::assertSame(1200, $this->createdValues['answered']);
    self::assertSame(2000, $this->createdValues['archived']);
    self::assertFalse($this->createdValues['evaluated']);
    self::assertSame(0, $this->createdValues['score']);
    self::assertSame(0.0, $this->createdValues['score_precise']);
    self::assertSame(0, $this->createdValues['max_score']);
  }

  /**
   * Confirma que uma nota inalterada não cria arquivo duplicado.
   */
  public function testUnchangedGradeDoesNotCreateDuplicateArchive(): void {
    $answer = $this->buildAnswer(['answer' => 'Sem alterações'], '80', 'graded');
    $answer_storage = $this->createMock(EntityStorageInterface::class);
    $answer_storage->expects(self::once())->method('loadUnchanged')->with('21')->willReturn($answer);
    $archive_storage = $this->createMock(EntityStorageInterface::class);
    $archive_storage->expects(self::never())->method('create');
    $manager = $this->manager($answer_storage, $archive_storage);

    $manager->archivePreviousAnswer($answer);
  }

  /**
   * Confirma que uma referência de atividade ausente ignora o arquivo e o histórico.
   */
  public function testMissingActivityReferenceSkipsArchiveAndHistoryQuery(): void {
    $answer = $this->buildAnswer(['answer' => 'Sem atividade'], NULL, 'submitted');
    $activity_storage = $this->createMock(EntityStorageInterface::class);
    $activity_storage->expects(self::exactly(2))->method('load')->with('5')->willReturn(NULL);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['aculta_lms_activity', $activity_storage],
    ]);
    $manager = new AttemptArchiveManager($entity_type_manager, $this->createMock(AccountInterface::class), $this->buildTime());

    $manager->archiveAnswer($answer);
    self::assertSame([], $manager->loadPreviousAttempts($answer, new CacheableMetadata()));
  }

  /**
   * Creates the manager and captures the archive entity values.
   *
   * @return array{AttemptArchiveManager, EntityInterface&MockObject}
   *   Gerenciador criado e entidade de arquivo simulada.
   */
  private function managerForArchive(?ContentEntityInterface $original = NULL): array {
    $archive_entity = $this->createMock(EntityInterface::class);
    $archive_entity->expects(self::once())->method('save');
    $archive_storage = $this->createMock(EntityStorageInterface::class);
    $archive_storage->expects(self::once())->method('create')->willReturnCallback(
      function (array $values) use ($archive_entity): EntityInterface {
        $this->createdValues = $values;
        return $archive_entity;
      },
    );
    $answer_storage = $this->createMock(EntityStorageInterface::class);
    if ($original !== NULL) {
      $answer_storage->expects(self::once())->method('loadUnchanged')->with('21')->willReturn($original);
    }
    $activity_storage = $this->entityStorage('5', $this->activity());
    $lesson_storage = $this->entityStorage('3', $this->lesson());
    $group_storage = $this->entityStorage('7', $this->course());
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['aculta_lms_answer', $answer_storage],
      ['aculta_lms_activity', $activity_storage],
      ['aculta_lms_lesson', $lesson_storage],
      ['group', $group_storage],
      ['aculta_lms_attempt_archive', $archive_storage],
    ]);
    return [
      new AttemptArchiveManager($entity_type_manager, $this->createMock(AccountInterface::class), $this->buildTime()),
      $archive_entity,
    ];
  }

  /**
   * Cria o gerenciador com storages simulados de resposta e arquivo.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $answer_storage
   *   Storage simulado das respostas.
   * @param \Drupal\Core\Entity\EntityStorageInterface $archive_storage
   *   Storage simulado dos arquivos.
   *
   * @return \Drupal\aculta_lms\AttemptArchiveManager
   *   Gerenciador de arquivamento de tentativas.
   */
  private function manager(EntityStorageInterface $answer_storage, EntityStorageInterface $archive_storage): AttemptArchiveManager {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['aculta_lms_answer', $answer_storage],
      ['aculta_lms_attempt_archive', $archive_storage],
    ]);
    return new AttemptArchiveManager($entity_type_manager, $this->createMock(AccountInterface::class), $this->buildTime());
  }

  /**
   * Cria um storage de entidade que devolve a entidade para o ID informado.
   *
   * @param string $id
   *   Identificador esperado na carga.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entidade devolvida pela carga.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   Storage simulado.
   */
  private function entityStorage(string $id, ContentEntityInterface $entity): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with($id)->willReturn($entity);
    return $storage;
  }

  /**
   * Monta uma entidade de resposta simulada.
   *
   * @param array<string, mixed> $data
   *   Dados da resposta.
   * @param string|null $score
   *   Nota da resposta, ou NULL se não avaliada.
   * @param string $state
   *   Estado da resposta.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Entidade de resposta simulada.
   */
  private function buildAnswer(array $data, ?string $score, string $state): ContentEntityInterface {
    return $this->contentEntity('aculta_lms_answer', '21', [
      'activity_id' => '5',
      'uid' => '9',
      'state' => $state,
      'score' => $score,
      'answer_data' => json_encode((object) $data, JSON_THROW_ON_ERROR),
      'created' => '1200',
    ], 'answer-uuid-21', FALSE);
  }

  /**
   * Monta uma entidade de atividade simulada.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Entidade de atividade simulada.
   */
  private function activity(): ContentEntityInterface {
    return $this->contentEntity('aculta_lms_activity', '5', [
      'lesson_id' => '3',
      'type' => 'free_text',
    ]);
  }

  /**
   * Monta uma entidade de aula simulada.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Entidade de aula simulada.
   */
  private function lesson(): ContentEntityInterface {
    return $this->contentEntity('aculta_lms_lesson', '3', [
      'course_id' => '7',
      'title' => 'Aula 1',
    ]);
  }

  /**
   * Monta um grupo de curso simulado.
   *
   * @return \Drupal\group\Entity\GroupInterface
   *   Grupo de curso simulado.
   */
  private function course(): GroupInterface {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('7');
    $course->method('bundle')->willReturn('lms_course');
    return $course;
  }

  /**
   * Creates a content entity double backed by simple scalar field lists.
   *
   * @param array<string, mixed> $values
   *   Scalar entity field values.
   */
  private function contentEntity(string $type, string $id, array $values, string $uuid = 'entity-uuid', bool $new = FALSE): ContentEntityInterface {
    $fields = [];
    foreach ($values as $name => $value) {
      $fields[$name] = $this->valueField($value);
    }
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($type);
    $entity->method('id')->willReturn($id);
    $entity->method('uuid')->willReturn($uuid);
    $entity->method('isNew')->willReturn($new);
    $entity->method('hasField')->willReturnCallback(static fn (string $field): bool => isset($fields[$field]));
    $entity->method('get')->willReturnCallback(static fn (string $field): FieldItemListInterface => $fields[$field]);
    return $entity;
  }

  /**
   * Cria um item de campo simulado com o valor informado.
   *
   * @param mixed $value
   *   Valor armazenado no campo.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<FieldItemInterface>
   *   Item de campo simulado.
   */
  private function valueField(mixed $value): FieldItemListInterface {
    return new class($value) extends FieldItemList {

      public function __construct(protected readonly mixed $fieldValue) {}

      /**
       * Informa se o valor do campo está vazio.
       *
       * @return bool
       *   TRUE se o valor for NULL.
       */
      public function isEmpty(): bool {
        return $this->fieldValue === NULL;
      }

      /**
       * Devolve o valor do campo para a propriedade solicitada.
       *
       * @param string $property_name
       *   Nome da propriedade solicitada.
       *
       * @return mixed
       *   Valor da propriedade.
       */
      public function __get(mixed $property_name): mixed {
        if ($property_name === 'value') {
          return $this->fieldValue;
        }
        return parent::__get($property_name);
      }

    };
  }

  /**
   * Cria um serviço de tempo simulado com tempo fixo.
   *
   * @return \Drupal\Component\Datetime\TimeInterface
   *   Serviço de tempo simulado.
   */
  private function buildTime(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(2000);
    return $time;
  }

}
