<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Entity;

use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[ContentEntityType(
  id: 'aculta_lms_progress',
  label: new TranslatableMarkup('Progresso do curso'),
  base_table: 'aculta_lms_progress',
  entity_keys: ['id' => 'id', 'uuid' => 'uuid'],
  handlers: ['access' => \Drupal\aculta_lms\Access\InternalLearningEntityAccessControlHandler::class, 'storage_schema' => LearningEntityStorageSchema::class],
  internal: TRUE,
)]
final class ProgressEntity extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['course_id'] = BaseFieldDefinition::create('integer')->setLabel(new TranslatableMarkup('Curso (referência opaca)'))->setRequired(TRUE);
    $fields['uid'] = BaseFieldDefinition::create('integer')->setLabel(new TranslatableMarkup('Estudante'))->setRequired(TRUE);
    $fields['status'] = BaseFieldDefinition::create('list_string')->setLabel(new TranslatableMarkup('Estado'))->setSetting('allowed_values', [CourseProgress::STATUS_PROGRESS => 'Em andamento', CourseProgress::STATUS_PASSED => 'Aprovado', CourseProgress::STATUS_FAILED => 'Reprovado', CourseProgress::STATUS_NEEDS_EVALUATION => 'Aguardando avaliação'])->setRequired(TRUE);
    $fields['finished'] = BaseFieldDefinition::create('boolean')->setLabel(new TranslatableMarkup('Concluído'))->setRequired(TRUE)->setDefaultValue(FALSE);
    $fields['score'] = BaseFieldDefinition::create('float')->setLabel(new TranslatableMarkup('Nota'))->addConstraint('Range', ['min' => 0, 'max' => 100]);
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel(new TranslatableMarkup('Alterado em'));
    return $fields;
  }

  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    self::assertValidStatus((string) $this->get('status')->value);
  }

  /** Rejects statuses outside the ACULTA progress vocabulary. */
  public static function assertValidStatus(string $status): void {
    if (!in_array($status, [CourseProgress::STATUS_PROGRESS, CourseProgress::STATUS_PASSED, CourseProgress::STATUS_FAILED, CourseProgress::STATUS_NEEDS_EVALUATION], TRUE)) {
      throw new \InvalidArgumentException('Estado de progresso desconhecido.');
    }
  }

}
