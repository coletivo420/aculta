<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Immutable snapshot of an LMS answer removed when a lesson is restarted.
 *
 * Drupal LMS 1.2.4 currently deletes prior answers on restart. This record
 * preserves an audit copy while the live attempt remains owned by LMS.
 */
#[ContentEntityType(
  id: 'aculta_lms_attempt_archive',
  label: new TranslatableMarkup('Registro de tentativa do curso'),
  base_table: 'aculta_lms_attempt_archive',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  internal: TRUE,
)]
final class AttemptArchive extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['uid'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Estudante'))
      ->setRequired(TRUE);
    $fields['group_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Turma/curso'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE);
    foreach ([
      'course_status_id' => 'Identificador do progresso no curso',
      'lesson_status_id' => 'Identificador da tentativa da aula',
      'lesson_id' => 'Identificador da aula',
      'lesson_label' => 'Nome da aula',
      'activity_id' => 'Identificador da atividade',
      'activity_label' => 'Nome da atividade',
      'answer_uuid' => 'Identificador original da resposta',
      'event_type' => 'Tipo da alteração registrada',
    ] as $name => $label) {
      $fields[$name] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup($label))
        ->setSetting('max_length', 255)
        ->setRequired(TRUE);
    }
    $fields['answer_data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Resposta original em JSON'))
      ->setRequired(TRUE);
    foreach ([
      'score' => 'Pontuação obtida',
      'max_score' => 'Pontuação máxima',
      'started' => 'Início da tentativa',
      'finished' => 'Conclusão da tentativa',
      'answered' => 'Envio da resposta',
      'archived' => 'Registro do histórico',
    ] as $name => $label) {
      $fields[$name] = BaseFieldDefinition::create('integer')
        ->setLabel(new TranslatableMarkup($label))
        ->setRequired(TRUE)
        ->setDefaultValue(0);
    }
    $fields['evaluated'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Avaliada'))
      ->setRequired(TRUE)
      ->setDefaultValue(FALSE);
    $fields['answer_comments'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Comentários da resposta anterior'));

    return $fields;
  }

}
