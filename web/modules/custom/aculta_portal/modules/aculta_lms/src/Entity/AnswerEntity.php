<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Entity;

use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[ContentEntityType(
  id: 'aculta_lms_answer',
  label: new TranslatableMarkup('Resposta'),
  base_table: 'aculta_lms_answer',
  entity_keys: ['id' => 'id', 'uuid' => 'uuid'],
  handlers: ['access' => \Drupal\aculta_lms\Access\InternalLearningEntityAccessControlHandler::class, 'storage_schema' => LearningEntityStorageSchema::class],
  internal: TRUE,
)]
final class AnswerEntity extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['activity_id'] = BaseFieldDefinition::create('integer')->setLabel(new TranslatableMarkup('Atividade'))->setRequired(TRUE);
    $fields['uid'] = BaseFieldDefinition::create('integer')->setLabel(new TranslatableMarkup('Estudante'))->setRequired(TRUE);
    $fields['state'] = BaseFieldDefinition::create('list_string')->setLabel(new TranslatableMarkup('Estado'))->setSetting('allowed_values', [CourseAnswer::STATE_SUBMITTED => 'Enviada', CourseAnswer::STATE_GRADED => 'Avaliada'])->setRequired(TRUE);
    $fields['score'] = BaseFieldDefinition::create('float')->setLabel(new TranslatableMarkup('Nota'))->addConstraint('Range', ['min' => 0, 'max' => 100]);
    $fields['created'] = BaseFieldDefinition::create('created')->setLabel(new TranslatableMarkup('Criada em'));
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel(new TranslatableMarkup('Alterada em'));
    return $fields;
  }

  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    self::assertAnswerRules((string) $this->get('state')->value, $this->get('score')->isEmpty() ? NULL : (float) $this->get('score')->value);
  }

  /** Applies the same state and score invariants as the ACULTA answer contract. */
  public static function assertAnswerRules(string $state, ?float $score): void {
    new CourseAnswer(1, 1, 1, $state, $score);
  }

}
